<?php

namespace App\Actions\Notifications;

use App\Actions\Business\DebitBusinessSmsWalletAction;
use App\Actions\Invoices\CreateInvoiceShareLinkAction;
use App\Enums\Accounting\InvoiceNotificationChannel;
use App\Enums\Accounting\InvoiceNotificationType;
use App\Exceptions\SmsSendException;
use App\Mail\InvoiceNotificationMail;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceNotification;
use App\Models\BusinessInvoiceSetting;
use App\Services\Mail\BusinessMailManager;
use App\Services\Notifications\InvoicePlaceholderRenderer;
use App\Services\Sms\SetareganSmsClient;
use App\Settings\InvoiceSystemSettings;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendInvoiceNotificationAction
{
    public function __construct(
        private InvoicePlaceholderRenderer $renderer,
        private BusinessMailManager $mailManager,
        private CreateInvoiceShareLinkAction $shareLinkAction,
        private SetareganSmsClient $smsClient,
        private DebitBusinessSmsWalletAction $debitWallet,
        private InvoiceSystemSettings $systemSettings,
    ) {}

    /**
     * @param  list<InvoiceNotificationChannel>|null  $channels
     * @return array{email: bool, sms: bool, errors: list<string>}
     */
    public function handle(
        Invoice $invoice,
        InvoiceNotificationType $type,
        ?array $channels = null,
        bool $force = false,
    ): array {
        $invoice->loadMissing(['party', 'business']);
        $business = $invoice->business;

        if ($business === null) {
            return ['email' => false, 'sms' => false, 'errors' => ['Business missing.']];
        }

        $settings = BusinessInvoiceSetting::forBusiness($business);
        $channels ??= $this->defaultChannels($settings, $type);

        $link = '';

        try {
            if ($invoice->isFinalized()) {
                $link = $this->shareLinkAction->handle($invoice);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to create invoice share link for notification', [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage(),
            ]);
        }

        $result = ['email' => false, 'sms' => false, 'errors' => []];

        foreach ($channels as $channel) {
            if ($channel === InvoiceNotificationChannel::Email) {
                $sent = $this->sendEmail($invoice, $settings, $type, $link, $force);
                $result['email'] = $sent['ok'];
                if ($sent['error'] !== null) {
                    $result['errors'][] = $sent['error'];
                }
            }

            if ($channel === InvoiceNotificationChannel::Sms) {
                $sent = $this->sendSms($invoice, $settings, $type, $link, $force);
                $result['sms'] = $sent['ok'];
                if ($sent['error'] !== null) {
                    $result['errors'][] = $sent['error'];
                }
            }
        }

        return $result;
    }

    /**
     * @return list<InvoiceNotificationChannel>
     */
    protected function defaultChannels(BusinessInvoiceSetting $settings, InvoiceNotificationType $type): array
    {
        $channels = [];

        $emailOn = match ($type) {
            InvoiceNotificationType::ReminderBefore,
            InvoiceNotificationType::ReminderDue,
            InvoiceNotificationType::ReminderAfter,
            InvoiceNotificationType::PaymentReminder => $settings->email_enabled && $settings->reminder_email_enabled,
            default => $settings->email_enabled,
        };

        $smsOn = match ($type) {
            InvoiceNotificationType::ReminderBefore,
            InvoiceNotificationType::ReminderDue,
            InvoiceNotificationType::ReminderAfter,
            InvoiceNotificationType::PaymentReminder => $settings->sms_enabled && $settings->reminder_sms_enabled,
            default => $settings->sms_enabled,
        };

        if ($emailOn) {
            $channels[] = InvoiceNotificationChannel::Email;
        }

        if ($smsOn) {
            $channels[] = InvoiceNotificationChannel::Sms;
        }

        return $channels;
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    protected function sendEmail(
        Invoice $invoice,
        BusinessInvoiceSetting $settings,
        InvoiceNotificationType $type,
        string $link,
        bool $force,
    ): array {
        if (! $settings->email_enabled || ! $settings->hasCompleteSmtp()) {
            return ['ok' => false, 'error' => __('general.invoice_email_smtp_incomplete')];
        }

        $email = $invoice->party?->email;

        if (! filled($email)) {
            return ['ok' => false, 'error' => __('general.invoice_email_party_missing')];
        }

        if (! $force && $type->isIdempotent() && $this->alreadySent($invoice, InvoiceNotificationChannel::Email, $type)) {
            return ['ok' => false, 'error' => null];
        }

        $templateKey = $type->templateKey();
        $subject = $this->renderer->render($settings->emailSubjectFor($templateKey), $invoice, ['link' => $link]);
        $body = $this->renderer->render($settings->emailBodyFor($templateKey), $invoice, ['link' => $link]);

        try {
            $mailer = $this->mailManager->mailerFor($settings);
            $mailer->to($email)->send(new InvoiceNotificationMail(
                emailSubject: $subject,
                emailBody: $body,
                fromAddress: (string) $settings->smtp_from_address,
                fromName: (string) ($settings->smtp_from_name ?: $invoice->business?->name),
            ));

            $this->record($invoice, InvoiceNotificationChannel::Email, $type, [
                'to' => $email,
            ]);

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            Log::error('Invoice email notification failed', [
                'invoice_id' => $invoice->id,
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => __('general.invoice_email_send_failed')];
        }
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    protected function sendSms(
        Invoice $invoice,
        BusinessInvoiceSetting $settings,
        InvoiceNotificationType $type,
        string $link,
        bool $force,
    ): array {
        if (! $settings->sms_enabled) {
            return ['ok' => false, 'error' => __('general.invoice_sms_disabled')];
        }

        $mobile = $invoice->party?->mobile ?: $invoice->party?->phone;

        if (! filled($mobile)) {
            return ['ok' => false, 'error' => __('general.invoice_sms_party_missing')];
        }

        if (! $force && $type->isIdempotent() && $this->alreadySent($invoice, InvoiceNotificationChannel::Sms, $type)) {
            return ['ok' => false, 'error' => null];
        }

        $cost = (string) $this->systemSettings->sms_unit_cost;

        if (bccomp((string) $invoice->business->sms_wallet_balance, $cost, 2) === -1) {
            return ['ok' => false, 'error' => __('general.sms_wallet_insufficient_balance')];
        }

        $templateKey = $type->templateKey();
        $message = $this->renderer->render($settings->smsBodyFor($templateKey), $invoice, ['link' => $link]);

        try {
            $this->smsClient->send((string) $mobile, $message);

            $notification = $this->record($invoice, InvoiceNotificationChannel::Sms, $type, [
                'to' => $mobile,
            ]);

            $this->debitWallet->handle(
                $invoice->business,
                $cost,
                __('general.sms_wallet_sms_debit'),
                $notification,
            );

            return ['ok' => true, 'error' => null];
        } catch (SmsSendException|RuntimeException $e) {
            Log::error('Invoice SMS notification failed', [
                'invoice_id' => $invoice->id,
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Invoice SMS notification failed unexpectedly', [
                'invoice_id' => $invoice->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => __('general.invoice_sms_send_failed')];
        }
    }

    protected function alreadySent(
        Invoice $invoice,
        InvoiceNotificationChannel $channel,
        InvoiceNotificationType $type,
    ): bool {
        return InvoiceNotification::query()
            ->where('invoice_id', $invoice->id)
            ->where('channel', $channel->value)
            ->where('type', $type->value)
            ->whereNotNull('sent_at')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function record(
        Invoice $invoice,
        InvoiceNotificationChannel $channel,
        InvoiceNotificationType $type,
        array $meta = [],
    ): InvoiceNotification {
        return InvoiceNotification::query()->create([
            'invoice_id' => $invoice->id,
            'channel' => $channel,
            'type' => $type,
            'sent_at' => now(),
            'meta' => $meta,
        ]);
    }
}

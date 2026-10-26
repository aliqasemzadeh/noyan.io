<?php

namespace App\Actions\Business;

use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Illuminate\Validation\ValidationException;

class UpdateBusinessLedgerSettingsAction
{
    public function __construct(
        private BusinessLedgerSettingsService $settingsService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Business $business, array $data): BusinessLedgerSetting
    {
        $numberingMode = $data['voucher_numbering_mode'] instanceof VoucherNumberingMode
            ? $data['voucher_numbering_mode']
            : VoucherNumberingMode::from((string) $data['voucher_numbering_mode']);

        $defaultStatus = $data['default_voucher_status'] instanceof JournalEntryStatus
            ? $data['default_voucher_status']
            : JournalEntryStatus::from((string) $data['default_voucher_status']);

        $autoPostMode = $data['auto_post_mode'] instanceof AutoPostMode
            ? $data['auto_post_mode']
            : AutoPostMode::from((string) $data['auto_post_mode']);

        if (! in_array($defaultStatus, [JournalEntryStatus::Draft, JournalEntryStatus::Posted], true)) {
            $defaultStatus = JournalEntryStatus::Draft;
        }

        $payload = [
            'voucher_numbering_mode' => $numberingMode,
            'voucher_prefix' => filled($data['voucher_prefix'] ?? null)
                ? strtoupper(trim((string) $data['voucher_prefix']))
                : null,
            'voucher_number_pad' => max(1, min(10, (int) ($data['voucher_number_pad'] ?? 4))),
            'default_voucher_status' => $defaultStatus,
            'auto_post_mode' => $autoPostMode,
        ];

        $current = BusinessLedgerSetting::forBusiness($business);
        $this->applyCodeLengths($current, $data, $payload);

        foreach (BusinessLedgerSetting::defaultAccountColumns() as $column) {
            if (! array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];

            if ($value === null || $value === '' || $value === false) {
                $payload[$column] = null;

                continue;
            }

            $accountId = (int) $value;
            $exists = GlAccount::query()
                ->where('business_id', $business->id)
                ->whereKey($accountId)
                ->where('is_postable', true)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    $column => [__('general.ledger_default_account_invalid')],
                ]);
            }

            $payload[$column] = $accountId;
        }

        $this->assertDefaultsForAutoPost($business, $current, $autoPostMode, $payload);

        return $this->settingsService->update($business, $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    protected function applyCodeLengths(BusinessLedgerSetting $current, array $data, array &$payload): void
    {
        $columns = [
            'group_code_length',
            'control_code_length',
            'subsidiary_code_length',
        ];

        $submitted = false;

        foreach ($columns as $column) {
            if (array_key_exists($column, $data)) {
                $submitted = true;
                break;
            }
        }

        if (! $submitted) {
            return;
        }

        if ($current->codeLengthsLocked()) {
            foreach ($columns as $column) {
                if (! array_key_exists($column, $data)) {
                    continue;
                }

                if ((int) $data[$column] !== (int) $current->{$column}) {
                    throw ValidationException::withMessages([
                        $column => [__('general.gl_code_length_locked')],
                    ]);
                }
            }

            return;
        }

        foreach ($columns as $column) {
            $payload[$column] = max(1, min(8, (int) ($data[$column] ?? $current->{$column})));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertDefaultsForAutoPost(
        Business $business,
        BusinessLedgerSetting $current,
        AutoPostMode $mode,
        array $payload,
    ): void {
        if ($mode === AutoPostMode::Off) {
            return;
        }

        $required = [
            'default_accounts_receivable_id',
            'default_accounts_payable_id',
            'default_sales_id',
            'default_cash_and_bank_id',
            'default_fx_gain_id',
            'default_fx_loss_id',
        ];

        if (GlAccount::findBySystemKey($business, 'inventory') !== null) {
            $required[] = 'default_inventory_id';
        }

        if (GlAccount::findBySystemKey($business, 'cogs') !== null) {
            $required[] = 'default_cogs_id';
        }

        $errors = [];

        foreach ($required as $column) {
            $value = array_key_exists($column, $payload) ? $payload[$column] : $current->{$column};

            if ($value === null || $value === '') {
                $errors[$column] = [__('general.auto_post_requires_default_account')];
            }
        }

        if ($errors !== []) {
            $errors['auto_post_mode'] = [__('general.auto_post_requires_default_accounts')];

            throw ValidationException::withMessages($errors);
        }
    }
}

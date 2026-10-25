<?php

namespace App\Actions\Business;

use App\Actions\Accounting\SyncBusinessLedgerDefaultAccountsAction;
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

        return $this->settingsService->update($business, $payload);
    }
}

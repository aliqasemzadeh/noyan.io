<?php

namespace App\Actions\Business;

use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Services\Accounting\BusinessLedgerSettingsService;

class UpdateBusinessLedgerSettingsAction
{
    public function __construct(
        private BusinessLedgerSettingsService $settingsService,
    ) {}

    /**
     * @param  array{
     *     voucher_numbering_mode: string|VoucherNumberingMode,
     *     voucher_prefix?: string|null,
     *     voucher_number_pad?: int,
     *     default_voucher_status: string|JournalEntryStatus,
     *     auto_post_mode: string|AutoPostMode
     * }  $data
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

        return $this->settingsService->update($business, [
            'voucher_numbering_mode' => $numberingMode,
            'voucher_prefix' => filled($data['voucher_prefix'] ?? null)
                ? strtoupper(trim((string) $data['voucher_prefix']))
                : null,
            'voucher_number_pad' => max(1, min(10, (int) ($data['voucher_number_pad'] ?? 4))),
            'default_voucher_status' => $defaultStatus,
            'auto_post_mode' => $autoPostMode,
        ]);
    }
}

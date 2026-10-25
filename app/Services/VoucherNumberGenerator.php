<?php

namespace App\Services;

use App\Enums\Accounting\VoucherNumberingMode;
use App\Models\Accounting\JournalEntry;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use App\Services\Accounting\BusinessLedgerSettingsService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

class VoucherNumberGenerator
{
    public function __construct(
        private BusinessLedgerSettingsService $ledgerSettings,
    ) {}

    /**
     * @return array{voucher_number: string, sequence_number: int, sequence_scope: string}
     */
    public function generate(
        int $businessId,
        int $fiscalYearId,
        string $entryDate,
        ?string $manualNumber = null,
    ): array {
        $settings = $this->settingsFor($businessId);
        $mode = $settings->voucher_numbering_mode ?? VoucherNumberingMode::Yearly;
        $pad = $settings->numberPad();
        $prefix = $settings->normalizedPrefix();
        $jalali = Jalalian::fromCarbon(Carbon::parse($entryDate)->startOfDay());
        $scope = $this->sequenceScope($mode, $fiscalYearId, $jalali);

        if ($mode === VoucherNumberingMode::Manual) {
            return $this->manualResult($manualNumber, $scope, $pad, $prefix);
        }

        $latestSequence = JournalEntry::query()
            ->where('business_id', $businessId)
            ->where('sequence_scope', $scope)
            ->whereNotNull('sequence_number')
            ->where('voucher_number', 'not like', '%__del_%')
            ->lockForUpdate()
            ->orderByDesc('sequence_number')
            ->value('sequence_number');

        $next = is_numeric($latestSequence) ? ((int) $latestSequence) + 1 : 1;
        $voucherNumber = $this->formatVoucherNumber($mode, $next, $pad, $prefix, $jalali);

        return [
            'voucher_number' => $voucherNumber,
            'sequence_number' => $next,
            'sequence_scope' => $scope,
        ];
    }

    protected function settingsFor(int $businessId): BusinessLedgerSetting
    {
        $business = Business::query()->find($businessId);

        if ($business === null) {
            return new BusinessLedgerSetting(BusinessLedgerSetting::defaultAttributes());
        }

        return $this->ledgerSettings->get($business);
    }

    protected function sequenceScope(
        VoucherNumberingMode $mode,
        int $fiscalYearId,
        Jalalian $jalali,
    ): string {
        return match ($mode) {
            VoucherNumberingMode::Yearly => 'fy:'.$fiscalYearId,
            VoucherNumberingMode::Monthly => 'fy:'.$fiscalYearId.':'.$jalali->format('Y-m'),
            VoucherNumberingMode::Daily => 'fy:'.$fiscalYearId.':'.$jalali->format('Y-m-d'),
            VoucherNumberingMode::Manual => 'fy:'.$fiscalYearId.':manual',
        };
    }

    protected function formatVoucherNumber(
        VoucherNumberingMode $mode,
        int $sequence,
        int $pad,
        ?string $prefix,
        Jalalian $jalali,
    ): string {
        $padded = str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT);

        $period = match ($mode) {
            VoucherNumberingMode::Yearly => null,
            VoucherNumberingMode::Monthly => $jalali->format('Y-m'),
            VoucherNumberingMode::Daily => $jalali->format('Y-m-d'),
            VoucherNumberingMode::Manual => null,
        };

        $parts = array_values(array_filter([$prefix, $period, $padded], fn (?string $part): bool => filled($part)));

        return implode('-', $parts);
    }

    /**
     * @return array{voucher_number: string, sequence_number: int, sequence_scope: string}
     */
    protected function manualResult(?string $manualNumber, string $scope, int $pad, ?string $prefix): array
    {
        $number = trim((string) $manualNumber);

        if ($number === '') {
            throw ValidationException::withMessages([
                'voucher_number' => [__('general.voucher_manual_number_required')],
            ]);
        }

        if (str_contains($number, '__del_')) {
            throw ValidationException::withMessages([
                'voucher_number' => [__('general.voucher_manual_number_invalid')],
            ]);
        }

        $sequence = ctype_digit($number) ? (int) $number : 0;
        $voucherNumber = $number;

        if ($prefix !== null && ! str_starts_with(strtoupper($voucherNumber), $prefix.'-') && ! str_starts_with(strtoupper($voucherNumber), $prefix)) {
            $voucherNumber = $prefix.'-'.(ctype_digit($number)
                ? str_pad($number, $pad, '0', STR_PAD_LEFT)
                : $number);
            $sequence = ctype_digit($number) ? (int) $number : 0;
        } elseif (ctype_digit($number)) {
            $voucherNumber = ($prefix !== null ? $prefix.'-' : '').str_pad($number, $pad, '0', STR_PAD_LEFT);
        }

        return [
            'voucher_number' => $voucherNumber,
            'sequence_number' => $sequence > 0 ? $sequence : 0,
            'sequence_scope' => $scope,
        ];
    }
}

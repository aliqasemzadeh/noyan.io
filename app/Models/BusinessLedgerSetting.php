<?php

namespace App\Models;

use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id',
    'voucher_numbering_mode',
    'voucher_prefix',
    'voucher_number_pad',
    'default_voucher_status',
    'auto_post_mode',
])]
class BusinessLedgerSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'voucher_numbering_mode' => VoucherNumberingMode::class,
            'voucher_number_pad' => 'integer',
            'default_voucher_status' => JournalEntryStatus::class,
            'auto_post_mode' => AutoPostMode::class,
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'voucher_numbering_mode' => VoucherNumberingMode::Yearly->value,
            'voucher_prefix' => null,
            'voucher_number_pad' => 4,
            'default_voucher_status' => JournalEntryStatus::Draft->value,
            'auto_post_mode' => AutoPostMode::Off->value,
        ];
    }

    public static function forBusiness(Business $business): self
    {
        return $business->ledgerSettings()->firstOrCreate(
            ['business_id' => $business->id],
            self::defaultAttributes(),
        );
    }

    public function normalizedPrefix(): ?string
    {
        if (! filled($this->voucher_prefix)) {
            return null;
        }

        return strtoupper(trim((string) $this->voucher_prefix));
    }

    public function numberPad(): int
    {
        return max(1, min(10, (int) $this->voucher_number_pad));
    }
}

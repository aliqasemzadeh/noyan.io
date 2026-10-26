<?php

namespace App\Models;

use App\Enums\Accounting\AccountLevel;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use App\Models\Accounting\GlAccount;
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
    'group_code_length',
    'control_code_length',
    'subsidiary_code_length',
    'default_accounts_receivable_id',
    'default_accounts_payable_id',
    'default_sales_id',
    'default_sales_discount_id',
    'default_vat_payable_id',
    'default_vat_receivable_id',
    'default_inventory_id',
    'default_cogs_id',
    'default_cash_and_bank_id',
    'default_fx_gain_id',
    'default_fx_loss_id',
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
            'group_code_length' => 'integer',
            'control_code_length' => 'integer',
            'subsidiary_code_length' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function defaultAccountsReceivable(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_accounts_receivable_id');
    }

    public function defaultAccountsPayable(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_accounts_payable_id');
    }

    public function defaultSales(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_sales_id');
    }

    public function defaultSalesDiscount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_sales_discount_id');
    }

    public function defaultVatPayable(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_vat_payable_id');
    }

    public function defaultVatReceivable(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_vat_receivable_id');
    }

    public function defaultInventory(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_inventory_id');
    }

    public function defaultCogs(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_cogs_id');
    }

    public function defaultCashAndBank(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_cash_and_bank_id');
    }

    public function defaultFxGain(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_fx_gain_id');
    }

    public function defaultFxLoss(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'default_fx_loss_id');
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
            'group_code_length' => 1,
            'control_code_length' => 1,
            'subsidiary_code_length' => 2,
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultAccountColumns(): array
    {
        return [
            'default_accounts_receivable_id',
            'default_accounts_payable_id',
            'default_sales_id',
            'default_sales_discount_id',
            'default_vat_payable_id',
            'default_vat_receivable_id',
            'default_inventory_id',
            'default_cogs_id',
            'default_cash_and_bank_id',
            'default_fx_gain_id',
            'default_fx_loss_id',
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

    public function codeLength(AccountLevel $level): int
    {
        $length = match ($level) {
            AccountLevel::Group => (int) $this->group_code_length,
            AccountLevel::Control => (int) $this->control_code_length,
            AccountLevel::Subsidiary => (int) $this->subsidiary_code_length,
        };

        return max(1, min(8, $length > 0 ? $length : match ($level) {
            AccountLevel::Group, AccountLevel::Control => 1,
            AccountLevel::Subsidiary => 2,
        }));
    }

    public function codeLengthsLocked(): bool
    {
        if ($this->business_id === null) {
            return false;
        }

        return GlAccount::withTrashed()
            ->where('business_id', $this->business_id)
            ->exists();
    }

    public function requireDefaultAccount(string $column): GlAccount
    {
        $account = $this->{$column} !== null
            ? GlAccount::query()->find($this->{$column})
            : null;

        if ($account === null) {
            throw new \RuntimeException("Ledger default account [{$column}] is not configured.");
        }

        return $account;
    }
}

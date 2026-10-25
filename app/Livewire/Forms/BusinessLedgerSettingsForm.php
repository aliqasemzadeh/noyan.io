<?php

namespace App\Livewire\Forms;

use App\Actions\Business\UpdateBusinessLedgerSettingsAction;
use App\Enums\Accounting\AutoPostMode;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\VoucherNumberingMode;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use Illuminate\Validation\Rule;
use Livewire\Form;

class BusinessLedgerSettingsForm extends Form
{
    public ?Business $business = null;

    public string $voucher_numbering_mode = 'yearly';

    public string $voucher_prefix = '';

    public int $voucher_number_pad = 4;

    public string $default_voucher_status = 'draft';

    public string $auto_post_mode = 'off';

    public ?int $default_accounts_receivable_id = null;

    public ?int $default_accounts_payable_id = null;

    public ?int $default_sales_id = null;

    public ?int $default_sales_discount_id = null;

    public ?int $default_vat_payable_id = null;

    public ?int $default_vat_receivable_id = null;

    public ?int $default_inventory_id = null;

    public ?int $default_cogs_id = null;

    public ?int $default_cash_and_bank_id = null;

    public ?int $default_fx_gain_id = null;

    public ?int $default_fx_loss_id = null;

    public function setModel(Business $business): void
    {
        $this->business = $business;
        $settings = BusinessLedgerSetting::forBusiness($business);

        $this->voucher_numbering_mode = $settings->voucher_numbering_mode?->value
            ?? VoucherNumberingMode::Yearly->value;
        $this->voucher_prefix = (string) ($settings->voucher_prefix ?? '');
        $this->voucher_number_pad = (int) $settings->voucher_number_pad;
        $this->default_voucher_status = $settings->default_voucher_status?->value
            ?? JournalEntryStatus::Draft->value;
        $this->auto_post_mode = $settings->auto_post_mode?->value
            ?? AutoPostMode::Off->value;

        foreach (BusinessLedgerSetting::defaultAccountColumns() as $column) {
            $this->{$column} = $settings->{$column} !== null ? (int) $settings->{$column} : null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->business?->id;

        $accountRule = [
            'nullable',
            'integer',
            Rule::exists('gl_accounts', 'id')->where(
                fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->where('is_postable', true)
                    ->whereNull('deleted_at'),
            ),
        ];

        return [
            'voucher_numbering_mode' => ['required', Rule::enum(VoucherNumberingMode::class)],
            'voucher_prefix' => ['nullable', 'string', 'max:20'],
            'voucher_number_pad' => ['required', 'integer', 'min:1', 'max:10'],
            'default_voucher_status' => ['required', Rule::in([
                JournalEntryStatus::Draft->value,
                JournalEntryStatus::Posted->value,
            ])],
            'auto_post_mode' => ['required', Rule::enum(AutoPostMode::class)],
            'default_accounts_receivable_id' => $accountRule,
            'default_accounts_payable_id' => $accountRule,
            'default_sales_id' => $accountRule,
            'default_sales_discount_id' => $accountRule,
            'default_vat_payable_id' => $accountRule,
            'default_vat_receivable_id' => $accountRule,
            'default_inventory_id' => $accountRule,
            'default_cogs_id' => $accountRule,
            'default_cash_and_bank_id' => $accountRule,
            'default_fx_gain_id' => $accountRule,
            'default_fx_loss_id' => $accountRule,
        ];
    }

    public function update(UpdateBusinessLedgerSettingsAction $action): BusinessLedgerSetting
    {
        $this->validate();

        if ($this->business === null) {
            throw new \RuntimeException('Business is required to update ledger settings.');
        }

        return $action->handle($this->business, [
            'voucher_numbering_mode' => $this->voucher_numbering_mode,
            'voucher_prefix' => $this->voucher_prefix,
            'voucher_number_pad' => $this->voucher_number_pad,
            'default_voucher_status' => $this->default_voucher_status,
            'auto_post_mode' => $this->auto_post_mode,
            'default_accounts_receivable_id' => $this->default_accounts_receivable_id,
            'default_accounts_payable_id' => $this->default_accounts_payable_id,
            'default_sales_id' => $this->default_sales_id,
            'default_sales_discount_id' => $this->default_sales_discount_id,
            'default_vat_payable_id' => $this->default_vat_payable_id,
            'default_vat_receivable_id' => $this->default_vat_receivable_id,
            'default_inventory_id' => $this->default_inventory_id,
            'default_cogs_id' => $this->default_cogs_id,
            'default_cash_and_bank_id' => $this->default_cash_and_bank_id,
            'default_fx_gain_id' => $this->default_fx_gain_id,
            'default_fx_loss_id' => $this->default_fx_loss_id,
        ]);
    }
}

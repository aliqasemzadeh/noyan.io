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
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'voucher_numbering_mode' => ['required', Rule::enum(VoucherNumberingMode::class)],
            'voucher_prefix' => ['nullable', 'string', 'max:20'],
            'voucher_number_pad' => ['required', 'integer', 'min:1', 'max:10'],
            'default_voucher_status' => ['required', Rule::in([
                JournalEntryStatus::Draft->value,
                JournalEntryStatus::Posted->value,
            ])],
            'auto_post_mode' => ['required', Rule::enum(AutoPostMode::class)],
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
        ]);
    }
}

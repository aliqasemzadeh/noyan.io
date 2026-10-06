<?php

namespace App\Livewire\Forms;

use App\Actions\Loans\RecordLoanPaymentAction;
use App\Models\Accounting\Loan;
use App\Support\Concerns\NormalizesLocaleFormValues;
use App\Support\LocaleDate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class LoanPaymentForm extends Form
{
    use NormalizesLocaleFormValues;

    public ?Loan $loan = null;

    public string $amount = '';

    public string $transaction_date = '';

    public ?int $account_id = null;

    public string $reference_number = '';

    public string $note = '';

    public function setLoan(Loan $loan): void
    {
        $this->loan = $loan;
        $this->account_id = $loan->account_id;
        $this->amount = $loan->remainingAmount();
        $this->transaction_date = $this->todayInput();
        $this->reference_number = '';
        $this->note = '';
    }

    public function resetForm(): void
    {
        $this->reset(['amount', 'transaction_date', 'account_id', 'reference_number', 'note', 'loan']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/', 'gt:0'],
            'transaction_date' => $this->localeDateRules(),
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'amount' => __('general.amount'),
            'transaction_date' => __('general.transaction_date'),
            'account_id' => __('general.account'),
            'reference_number' => __('general.reference_number'),
            'note' => __('general.note'),
        ];
    }

    public function store(): Loan
    {
        $user = Auth::user();

        if ($user?->current_business_id === null || $this->loan === null) {
            throw ValidationException::withMessages([
                'amount' => __('general.business_required'),
            ]);
        }

        $this->normalizeMoneyFields('amount');
        $this->normalizeDateFields('transaction_date');
        $validated = $this->validate();

        $loan = app(RecordLoanPaymentAction::class)->handle($user, $this->loan, [
            'amount' => $validated['amount'],
            'transaction_date' => LocaleDate::toStorageDate($validated['transaction_date']) ?? $validated['transaction_date'],
            'account_id' => (int) $validated['account_id'],
            'reference_number' => ($validated['reference_number'] ?? '') !== '' ? $validated['reference_number'] : null,
            'note' => ($validated['note'] ?? '') !== '' ? $validated['note'] : null,
        ]);

        $this->resetForm();

        return $loan;
    }
}

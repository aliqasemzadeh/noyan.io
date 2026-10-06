<?php

namespace App\Livewire\Forms;

use App\Actions\Loans\CreateLoanAction;
use App\Enums\Accounting\LoanType;
use App\Models\Accounting\Loan;
use App\Support\Concerns\NormalizesLocaleFormValues;
use App\Support\LocaleDate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class LoanForm extends Form
{
    use NormalizesLocaleFormValues;

    public string $type = LoanType::Received->value;

    public ?int $party_id = null;

    public ?int $account_id = null;

    public string $title = '';

    public string $principal_amount = '';

    public string $interest_amount = '0';

    public ?int $installments_count = null;

    public string $installment_amount = '';

    public string $issue_date = '';

    public string $first_installment_date = '';

    public string $description = '';

    public function mount(): void
    {
        $this->issue_date = $this->todayInput();
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->type = LoanType::Received->value;
        $this->interest_amount = '0';
        $this->issue_date = $this->todayInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'type' => ['required', Rule::enum(LoanType::class)],
            'party_id' => [
                'required',
                'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)->whereNull('deleted_at')),
            ],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'title' => ['required', 'string', 'max:150'],
            'principal_amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/', 'gt:0'],
            'interest_amount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,18})?$/'],
            'installments_count' => ['nullable', 'integer', 'min:1'],
            'installment_amount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,18})?$/'],
            'issue_date' => $this->localeDateRules(),
            'first_installment_date' => $this->localeDateRules(required: false),
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'type' => __('general.loan_type'),
            'party_id' => __('general.party'),
            'account_id' => __('general.account'),
            'title' => __('general.title'),
            'principal_amount' => __('general.principal_amount'),
            'interest_amount' => __('general.interest_amount'),
            'installments_count' => __('general.installments_count'),
            'installment_amount' => __('general.installment_amount'),
            'issue_date' => __('general.issue_date'),
            'first_installment_date' => __('general.first_installment_date'),
            'description' => __('general.description'),
        ];
    }

    public function store(): Loan
    {
        $user = Auth::user();

        if ($user?->current_business_id === null) {
            throw ValidationException::withMessages([
                'party_id' => __('general.business_required'),
            ]);
        }

        $this->normalizeMoneyFields('principal_amount', 'interest_amount', 'installment_amount');
        $this->normalizeDateFields('issue_date', 'first_installment_date');
        $validated = $this->validate();

        $loan = app(CreateLoanAction::class)->handle($user, [
            'type' => $validated['type'],
            'party_id' => (int) $validated['party_id'],
            'account_id' => (int) $validated['account_id'],
            'title' => $validated['title'],
            'principal_amount' => $validated['principal_amount'],
            'interest_amount' => ($validated['interest_amount'] ?? '') !== '' ? $validated['interest_amount'] : '0',
            'installments_count' => $validated['installments_count'] ?? null,
            'installment_amount' => ($validated['installment_amount'] ?? '') !== '' ? $validated['installment_amount'] : null,
            'issue_date' => LocaleDate::toStorageDate($validated['issue_date']) ?? $validated['issue_date'],
            'first_installment_date' => ($validated['first_installment_date'] ?? '') !== ''
                ? (LocaleDate::toStorageDate($validated['first_installment_date']) ?? $validated['first_installment_date'])
                : null,
            'description' => ($validated['description'] ?? '') !== '' ? $validated['description'] : null,
        ]);

        $this->resetForm();

        return $loan;
    }
}

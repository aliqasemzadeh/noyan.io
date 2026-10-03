<?php

namespace App\Livewire\Forms;

use App\Actions\Cheques\RegisterChequeAction;
use App\Actions\Cheques\UpdateChequeAction;
use App\Enums\Accounting\ChequeType;
use App\Models\Accounting\Cheque;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ChequeForm extends Form
{
    public ?Cheque $cheque = null;

    public string $type = ChequeType::Received->value;

    public ?int $party_id = null;

    public ?int $account_id = null;

    public ?int $invoice_id = null;

    public string $cheque_number = '';

    public string $sayad_number = '';

    public string $bank_name = '';

    public string $bank_branch = '';

    public string $amount = '';

    public string $issue_date = '';

    public string $due_date = '';

    public string $note = '';

    public function mount(): void
    {
        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays(30)->toDateString();
    }

    public function setModel(Cheque $cheque): void
    {
        $this->cheque = $cheque;
        $this->type = $cheque->type->value;
        $this->party_id = $cheque->party_id;
        $this->account_id = $cheque->account_id;
        $this->invoice_id = $cheque->invoice_id;
        $this->cheque_number = $cheque->cheque_number;
        $this->sayad_number = (string) ($cheque->sayad_number ?? '');
        $this->bank_name = $cheque->bank_name;
        $this->bank_branch = (string) ($cheque->bank_branch ?? '');
        $this->amount = (string) $cheque->amount;
        $this->issue_date = $cheque->issue_date->toDateString();
        $this->due_date = $cheque->due_date->toDateString();
        $this->note = (string) ($cheque->note ?? '');
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->cheque = null;
        $this->type = ChequeType::Received->value;
        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays(30)->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;
        $isUpdate = $this->cheque !== null;

        return [
            'type' => [$isUpdate ? 'nullable' : 'required', Rule::enum(ChequeType::class)],
            'party_id' => [
                $isUpdate ? 'nullable' : 'required',
                'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)->whereNull('deleted_at')),
            ],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'invoice_id' => [
                'nullable',
                'integer',
                Rule::exists('invoices', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at')),
            ],
            'cheque_number' => ['required', 'string', 'max:50'],
            'sayad_number' => ['nullable', 'string', 'max:16', 'regex:/^\d*$/'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'amount' => [$isUpdate ? 'nullable' : 'required', 'string', 'regex:/^\d+(\.\d{1,18})?$/', 'gt:0'],
            'issue_date' => [$isUpdate ? 'nullable' : 'required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'type' => __('general.cheque_type'),
            'party_id' => __('general.party'),
            'account_id' => __('general.account'),
            'invoice_id' => __('general.related_invoice'),
            'cheque_number' => __('general.cheque_number'),
            'sayad_number' => __('general.sayad_number'),
            'bank_name' => __('general.bank_name'),
            'bank_branch' => __('general.bank_branch'),
            'amount' => __('general.amount'),
            'issue_date' => __('general.issue_date'),
            'due_date' => __('general.due_date'),
            'note' => __('general.note'),
        ];
    }

    public function store(): Cheque
    {
        $user = Auth::user();

        if ($user?->current_business_id === null) {
            throw ValidationException::withMessages([
                'party_id' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();

        $cheque = app(RegisterChequeAction::class)->handle($user, [
            'type' => $validated['type'],
            'party_id' => (int) $validated['party_id'],
            'account_id' => $validated['account_id'] ?? null,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'cheque_number' => $validated['cheque_number'],
            'sayad_number' => ($validated['sayad_number'] ?? '') !== '' ? $validated['sayad_number'] : null,
            'bank_name' => $validated['bank_name'],
            'bank_branch' => ($validated['bank_branch'] ?? '') !== '' ? $validated['bank_branch'] : null,
            'amount' => $validated['amount'],
            'issue_date' => $validated['issue_date'],
            'due_date' => $validated['due_date'],
            'note' => ($validated['note'] ?? '') !== '' ? $validated['note'] : null,
        ]);

        $this->resetForm();

        return $cheque;
    }

    public function update(): Cheque
    {
        $user = Auth::user();

        if ($user?->current_business_id === null || $this->cheque === null) {
            throw ValidationException::withMessages([
                'cheque_number' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();

        $cheque = app(UpdateChequeAction::class)->handle($user, $this->cheque, [
            'cheque_number' => $validated['cheque_number'],
            'sayad_number' => ($validated['sayad_number'] ?? '') !== '' ? $validated['sayad_number'] : null,
            'bank_name' => $validated['bank_name'],
            'bank_branch' => ($validated['bank_branch'] ?? '') !== '' ? $validated['bank_branch'] : null,
            'due_date' => $validated['due_date'],
            'account_id' => $validated['account_id'] ?? null,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'note' => ($validated['note'] ?? '') !== '' ? $validated['note'] : null,
        ]);

        $this->resetForm();

        return $cheque;
    }
}

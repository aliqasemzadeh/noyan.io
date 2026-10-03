<?php

namespace App\Livewire\Forms;

use App\Actions\Cheques\ChangeChequeStatusAction;
use App\Enums\Accounting\ChequeStatus;
use App\Models\Accounting\Cheque;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ChequeStatusForm extends Form
{
    public ?Cheque $cheque = null;

    public string $status = '';

    public string $transaction_date = '';

    public ?int $account_id = null;

    public string $note = '';

    public function setCheque(Cheque $cheque): void
    {
        $this->cheque = $cheque;
        $this->status = '';
        $this->transaction_date = now()->toDateString();
        $this->account_id = $cheque->account_id;
        $this->note = '';
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->cheque = null;
        $this->transaction_date = now()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;
        $status = ChequeStatus::tryFrom($this->status);
        $requiresAccount = $status === ChequeStatus::Cleared;

        return [
            'status' => ['required', Rule::enum(ChequeStatus::class)],
            'transaction_date' => [$requiresAccount ? 'required' : 'nullable', 'date'],
            'account_id' => [
                $requiresAccount ? 'required' : 'nullable',
                'integer',
                Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'status' => __('general.new_status'),
            'transaction_date' => __('general.transaction_date'),
            'account_id' => __('general.account'),
            'note' => __('general.note'),
        ];
    }

    public function submit(): Cheque
    {
        $user = Auth::user();

        if ($user?->current_business_id === null || $this->cheque === null) {
            throw ValidationException::withMessages([
                'status' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();
        $status = ChequeStatus::from($validated['status']);

        $cheque = app(ChangeChequeStatusAction::class)->handle($user, $this->cheque, $status, [
            'transaction_date' => ($validated['transaction_date'] ?? '') !== '' ? $validated['transaction_date'] : null,
            'account_id' => $validated['account_id'] ?? null,
            'note' => ($validated['note'] ?? '') !== '' ? $validated['note'] : null,
        ]);

        $this->resetForm();

        return $cheque;
    }
}

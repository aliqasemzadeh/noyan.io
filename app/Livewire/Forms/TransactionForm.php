<?php

namespace App\Livewire\Forms;

use App\Actions\Transactions\ProcessTransactionAction;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\BusinessCurrency;
use App\Models\Accounting\Transaction;
use App\Models\Category;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class TransactionForm extends Form
{
    public string $type = TransactionType::Income->value;

    public ?int $account_id = null;

    public ?int $destination_account_id = null;

    public ?int $party_id = null;

    public ?int $category_id = null;

    public string $amount = '';

    public string $transaction_date = '';

    public string $reference_number = '';

    public string $note = '';

    public function mount(): void
    {
        $this->transaction_date = now()->toDateString();
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->type = TransactionType::Income->value;
        $this->transaction_date = now()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;
        $isTransfer = $this->type === TransactionType::Transfer->value;

        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'destination_account_id' => [
                Rule::requiredIf($isTransfer),
                'nullable',
                'integer',
                'different:account_id',
                Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)),
            ],
            'party_id' => [
                'nullable',
                'integer',
                Rule::prohibitedIf($isTransfer),
                Rule::exists('parties', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)->whereNull('deleted_at')),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::prohibitedIf($isTransfer),
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('type', $this->type)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))),
                function (string $attribute, mixed $value, Closure $fail) use ($businessId): void {
                    if ($value === null || $businessId === null) {
                        return;
                    }

                    $hasChildren = Category::query()
                        ->where('parent_id', $value)
                        ->whereNull('deleted_at')
                        ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))
                        ->exists();

                    if ($hasChildren) {
                        $fail(__('general.transaction_category_must_be_leaf'));
                    }
                },
            ],
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,18})?$/', 'gt:0'],
            'transaction_date' => ['required', 'date'],
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
            'type' => __('general.transaction_type'),
            'account_id' => __('general.account'),
            'destination_account_id' => __('general.destination_account'),
            'party_id' => __('general.party'),
            'category_id' => __('general.category'),
            'amount' => __('general.amount'),
            'transaction_date' => __('general.transaction_date'),
            'reference_number' => __('general.reference_number'),
            'note' => __('general.note'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => __('general.transaction_amount_must_be_positive'),
            'destination_account_id.required' => __('general.transaction_destination_required'),
            'destination_account_id.different' => __('general.transaction_destination_must_differ'),
        ];
    }

    public function store(): Transaction
    {
        $user = Auth::user();

        if ($user?->current_business_id === null) {
            throw ValidationException::withMessages([
                'account_id' => __('general.business_required'),
            ]);
        }

        $validated = $this->validate();

        $account = Account::query()
            ->with('currency')
            ->where('business_id', $user->current_business_id)
            ->whereKey($validated['account_id'])
            ->firstOrFail();

        $exchangeRate = BusinessCurrency::query()
            ->where('business_id', $user->current_business_id)
            ->where('currency_id', $account->currency_id)
            ->value('exchange_rate_to_base') ?? '1';

        $transaction = app(ProcessTransactionAction::class)->handle($user, [
            'type' => $validated['type'],
            'account_id' => (int) $validated['account_id'],
            'destination_account_id' => $validated['destination_account_id'] ?? null,
            'party_id' => $validated['party_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'transaction_date' => $validated['transaction_date'],
            'currency' => $account->currency?->code ?? 'IRR',
            'exchange_rate' => (string) $exchangeRate,
            'amount' => $validated['amount'],
            'reference_number' => ($validated['reference_number'] ?? '') !== '' ? $validated['reference_number'] : null,
            'note' => ($validated['note'] ?? '') !== '' ? $validated['note'] : null,
        ]);

        $this->resetForm();

        return $transaction;
    }
}

<?php

namespace App\Livewire\Forms\Accounting;

use App\Enums\Accounting\AccountLevel;
use App\Enums\Accounting\AccountNature;
use App\Enums\Accounting\DetailType;
use App\Enums\Accounting\StatementType;
use App\Models\Accounting\GlAccount;
use App\Models\Business;
use App\Models\BusinessLedgerSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class GlAccountForm extends Form
{
    public ?GlAccount $glAccount = null;

    public ?int $parent_id = null;

    public string $code = '';

    public string $name = '';

    public string $nature = 'debit';

    public string $statement_type = 'balance_sheet';

    public string $requires_detail_level_4_type = '';

    public string $requires_detail_level_5_type = '';

    public string $requires_detail_level_6_type = '';

    public bool $is_active = true;

    public function setModel(GlAccount $glAccount): void
    {
        $this->glAccount = $glAccount;
        $this->parent_id = $glAccount->parent_id;
        $this->code = $glAccount->code;
        $this->name = $glAccount->name;
        $this->nature = $glAccount->nature->value;
        $this->statement_type = $glAccount->statement_type->value;
        $this->requires_detail_level_4_type = $glAccount->requires_detail_level_4_type?->value ?? '';
        $this->requires_detail_level_5_type = $glAccount->requires_detail_level_5_type?->value ?? '';
        $this->requires_detail_level_6_type = $glAccount->requires_detail_level_6_type?->value ?? '';
        $this->is_active = $glAccount->is_active;
    }

    public function prepareCreate(?int $parentId = null): void
    {
        $this->resetFormState();
        $this->parent_id = $parentId;

        if ($parentId === null) {
            return;
        }

        $parent = GlAccount::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->find($parentId);

        if ($parent === null) {
            return;
        }

        $this->nature = $parent->nature->value;
        $this->statement_type = $parent->statement_type->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = Auth::user()?->current_business_id;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('gl_accounts', 'id')->where(
                    fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at'),
                ),
            ],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'nature' => ['required', Rule::enum(AccountNature::class)],
            'statement_type' => ['required', Rule::enum(StatementType::class)],
            'requires_detail_level_4_type' => ['nullable', Rule::enum(DetailType::class)],
            'requires_detail_level_5_type' => ['nullable', Rule::enum(DetailType::class)],
            'requires_detail_level_6_type' => ['nullable', Rule::enum(DetailType::class)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'parent_id' => __('general.parent_account'),
            'code' => __('general.code'),
            'name' => __('general.name'),
            'nature' => __('general.account_nature'),
            'statement_type' => __('general.statement_type'),
            'requires_detail_level_4_type' => __('general.requires_detail_level_4'),
            'requires_detail_level_5_type' => __('general.requires_detail_level_5'),
            'requires_detail_level_6_type' => __('general.requires_detail_level_6'),
            'is_active' => __('general.is_active'),
        ];
    }

    public function store(): GlAccount
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();

        $parent = null;
        $level = AccountLevel::Group;

        if (! empty($validated['parent_id'])) {
            $parent = GlAccount::query()
                ->where('business_id', $businessId)
                ->findOrFail((int) $validated['parent_id']);

            if (! $parent->canHaveChildren()) {
                throw ValidationException::withMessages([
                    'parent_id' => [__('general.gl_account_parent_cannot_have_children')],
                ]);
            }

            $level = $parent->level->childLevel() ?? AccountLevel::Subsidiary;
        }

        $code = strtoupper(trim((string) $validated['code']));
        $expectedLength = BusinessLedgerSetting::forBusiness(
            Business::query()->findOrFail($businessId),
        )->codeLength($level);

        if (strlen($code) !== $expectedLength) {
            throw ValidationException::withMessages([
                'code' => [__('general.gl_code_length_invalid', ['length' => $expectedLength])],
            ]);
        }

        $fullCode = $parent === null ? $code : $parent->full_code.$code;

        if (GlAccount::query()
            ->where('business_id', $businessId)
            ->where('full_code', $fullCode)
            ->exists()) {
            throw ValidationException::withMessages([
                'code' => [__('general.gl_account_full_code_taken')],
            ]);
        }

        $account = GlAccount::query()->create([
            'business_id' => $businessId,
            'parent_id' => $parent?->id,
            'level' => $level,
            'code' => $code,
            'full_code' => $fullCode,
            'name' => $validated['name'],
            'nature' => $validated['nature'],
            'statement_type' => $validated['statement_type'],
            'is_postable' => $level === AccountLevel::Subsidiary,
            'is_system' => false,
            'system_key' => null,
            'requires_detail_level_4_type' => filled($validated['requires_detail_level_4_type'] ?? null)
                ? $validated['requires_detail_level_4_type']
                : null,
            'requires_detail_level_5_type' => filled($validated['requires_detail_level_5_type'] ?? null)
                ? $validated['requires_detail_level_5_type']
                : null,
            'requires_detail_level_6_type' => filled($validated['requires_detail_level_6_type'] ?? null)
                ? $validated['requires_detail_level_6_type']
                : null,
            'sort_order' => 0,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $this->resetFormState();

        return $account;
    }

    public function update(): void
    {
        $businessId = $this->requireBusinessId();
        $validated = $this->validate();

        if ($this->glAccount === null) {
            throw ValidationException::withMessages([
                'name' => [__('general.gl_account_not_found')],
            ]);
        }

        if ($this->glAccount->is_system) {
            throw ValidationException::withMessages([
                'name' => [__('general.gl_account_system_locked')],
            ]);
        }

        $this->glAccount->update([
            'name' => $validated['name'],
            'nature' => $validated['nature'],
            'statement_type' => $validated['statement_type'],
            'requires_detail_level_4_type' => filled($validated['requires_detail_level_4_type'] ?? null)
                ? $validated['requires_detail_level_4_type']
                : null,
            'requires_detail_level_5_type' => filled($validated['requires_detail_level_5_type'] ?? null)
                ? $validated['requires_detail_level_5_type']
                : null,
            'requires_detail_level_6_type' => filled($validated['requires_detail_level_6_type'] ?? null)
                ? $validated['requires_detail_level_6_type']
                : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $this->resetFormState();
    }

    public function delete(): void
    {
        $this->requireBusinessId();

        if ($this->glAccount === null) {
            throw ValidationException::withMessages([
                'name' => [__('general.gl_account_not_found')],
            ]);
        }

        if ($this->glAccount->is_system) {
            throw ValidationException::withMessages([
                'name' => [__('general.gl_account_system_locked')],
            ]);
        }

        if ($this->glAccount->hasChildren()) {
            throw ValidationException::withMessages([
                'name' => [__('general.gl_account_has_children')],
            ]);
        }

        $this->glAccount->delete();
        $this->resetFormState();
    }

    protected function requireBusinessId(): int
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            throw ValidationException::withMessages([
                'name' => [__('general.business_required')],
            ]);
        }

        return (int) $businessId;
    }

    protected function resetFormState(): void
    {
        $this->reset();
        $this->nature = AccountNature::Debit->value;
        $this->statement_type = StatementType::BalanceSheet->value;
        $this->is_active = true;
    }
}

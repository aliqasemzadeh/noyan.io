<?php

use App\Enums\Accounting\AccountNature;
use App\Enums\Accounting\DetailType;
use App\Enums\Accounting\StatementType;
use App\Livewire\Forms\Accounting\GlAccountForm;
use App\Models\Accounting\GlAccount;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public GlAccountForm $form;

    public bool $isSystem = false;

    #[On('panels.accounting.gl-account.edit.assign-data')]
    public function assignData(int $glAccountId): void
    {
        $account = GlAccount::query()
            ->where('business_id', Auth::user()?->current_business_id)
            ->findOrFail($glAccountId);

        $this->form->setModel($account);
        $this->isSystem = $account->is_system;
        $this->resetValidation();

        Flux::modal('gl-account.edit')->show();
    }

    public function save(): void
    {
        $this->form->update();

        $this->dispatch('panels.accounting.gl-account.index.table');
        Flux::modals()->close();
        Flux::toast(__('general.gl_account_updated'));
    }
};
?>

<flux:modal name="gl-account.edit" flyout position="right" class="space-y-6 md:w-112">
    <div>
        <flux:heading size="lg">{{ __('general.edit_gl_account') }}</flux:heading>
        @if ($form->glAccount)
            <flux:text class="mt-1 font-mono" dir="ltr">{{ $form->glAccount->full_code }}</flux:text>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        @if ($isSystem)
            <flux:callout icon="lock" variant="secondary" inline>
                {{ __('general.gl_account_system_edit_hint') }}
            </flux:callout>
        @endif

        <flux:input wire:model="form.name" :label="__('general.name')" :disabled="$isSystem" />

        <flux:select wire:model="form.nature" :label="__('general.account_nature')" variant="listbox" :disabled="$isSystem">
            @foreach (AccountNature::cases() as $nature)
                <flux:select.option :value="$nature->value">{{ $nature->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="form.statement_type" :label="__('general.statement_type')" variant="listbox" :disabled="$isSystem">
            @foreach (StatementType::cases() as $type)
                <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="form.requires_detail_level_4_type" :label="__('general.requires_detail_level_4')" variant="listbox" searchable :disabled="$isSystem">
            <flux:select.option value="">{{ __('general.none') }}</flux:select.option>
            @foreach (DetailType::cases() as $type)
                <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model.live="form.is_active" :disabled="$isSystem" />
        </flux:field>

        @unless ($isSystem)
            <flux:button type="submit" variant="primary" color="orange" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        @endunless
    </form>
</flux:modal>

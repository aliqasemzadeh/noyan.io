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

    public ?string $parentLabel = null;

    #[On('panels.accounting.gl-account.create.assign-data')]
    public function assignData(?int $parentId = null): void
    {
        $this->form->prepareCreate($parentId);
        $this->parentLabel = null;
        $this->resetValidation();

        if ($parentId !== null) {
            $parent = GlAccount::query()
                ->where('business_id', Auth::user()?->current_business_id)
                ->find($parentId);

            $this->parentLabel = $parent
                ? $parent->full_code.' — '.$parent->name
                : null;
        }

        Flux::modal('gl-account.create')->show();
    }

    public function save(): void
    {
        $account = $this->form->store();

        $this->dispatch('panels.accounting.gl-account.index.table');
        Flux::modals()->close();
        Flux::toast(__('general.gl_account_created', ['name' => $account->name]));
    }
};
?>

<flux:modal name="gl-account.create" flyout position="right" class="space-y-6 md:w-112">
    <div>
        <flux:heading size="lg">{{ __('general.create_gl_account') }}</flux:heading>
        @if ($parentLabel)
            <flux:text class="mt-1">{{ __('general.parent_account') }}: {{ $parentLabel }}</flux:text>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="form.code" :label="__('general.code')" dir="ltr" />
        <flux:input wire:model="form.name" :label="__('general.name')" />

        <flux:select wire:model="form.nature" :label="__('general.account_nature')" variant="listbox">
            @foreach (AccountNature::cases() as $nature)
                <flux:select.option :value="$nature->value">{{ $nature->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="form.statement_type" :label="__('general.statement_type')" variant="listbox">
            @foreach (StatementType::cases() as $type)
                <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="form.requires_detail_level_4_type" :label="__('general.requires_detail_level_4')" variant="listbox" searchable>
            <flux:select.option value="">{{ __('general.none') }}</flux:select.option>
            @foreach (DetailType::cases() as $type)
                <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:field variant="inline">
            <flux:label>{{ __('general.is_active') }}</flux:label>
            <flux:switch wire:model.live="form.is_active" />
        </flux:field>

        <flux:button type="submit" variant="primary" color="orange" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>

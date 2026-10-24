<?php

use App\Livewire\Forms\TicketForm;
use App\Models\TicketCategory;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public TicketForm $form;

    #[Computed]
    public function categories(): Collection
    {
        return TicketCategory::query()
            ->active()
            ->ordered()
            ->get(['id', 'title']);
    }

    #[Computed]
    public function businesses(): Collection
    {
        return Auth::user()
            ?->businesses()
            ->orderBy('name')
            ->get(['businesses.id', 'businesses.name'])
            ?? collect();
    }

    public function removeImage(int $index): void
    {
        $this->form->removeImage($index);
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $ticket = $this->form->store($user, request()->ip());

        Flux::toast(__('general.ticket_created'));

        $this->redirect(route('user.support.tickets.view', $ticket), navigate: true);
    }
};
?>

<x-slot name="title">{{ __('general.create_ticket') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('user.support.tickets.index')" wire:navigate>
                {{ __('general.tickets') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.create_ticket') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.create_ticket') }}
            </flux:heading>
        </div>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('general.title') }}</flux:label>
                <flux:input wire:model="form.title" />
                <flux:error name="form.title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.ticket_category') }}</flux:label>
                <flux:select
                    wire:model="form.ticket_category_id"
                    variant="listbox"
                    searchable
                    placeholder="{{ __('general.ticket_category') }}"
                >
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}" wire:key="create-category-{{ $category->id }}">
                            {{ $category->title }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.ticket_category_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.optional_business') }}</flux:label>
                <flux:select
                    wire:model="form.business_id"
                    variant="listbox"
                    searchable
                    clearable
                    placeholder="{{ __('general.select_business_optional') }}"
                >
                    @foreach ($this->businesses as $business)
                        <flux:select.option value="{{ $business->id }}" wire:key="create-business-{{ $business->id }}">
                            {{ $business->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.business_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.message') }}</flux:label>
                <flux:textarea wire:model="form.body" rows="6" />
                <flux:error name="form.body" />
            </flux:field>

            <x-support.ticket-image-upload
                model="form.images"
                :files="$form->images"
                error-prefix="form.images"
                remove-action="removeImage"
            />

            <flux:button type="submit" variant="primary" color="teal" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>

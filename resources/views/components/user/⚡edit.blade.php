<?php

use App\Enums\Gender;
use App\Livewire\Forms\UserForm;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public UserForm $form;

    public ?User $user = null;

    #[On('panels.administrator.user.edit.assign-data')]
    public function assignData(User $user): void
    {
        abort_unless(auth()->user()?->can('user_edit'), 403);

        $this->user = $user;
        $this->form->setModel($user);
        $this->resetValidation();

        Flux::modal('user.edit')->show();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('user_edit'), 403);

        $this->form->update();

        $this->dispatch('panels.administrator.user.index.table');

        Flux::modals()->close();

        Flux::toast(__('general.user_updated'));
    }
};
?>

<flux:modal name="user.edit" flyout position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('general.edit_user') }}</flux:heading>
    </div>

    @if ($user)
        <flux:callout icon="box" variant="secondary" inline>
            {{ $user->fullName() ?? $user->mobile }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.first_name') }}</flux:label>
                <flux:input wire:model="form.first_name" />
                <flux:error name="form.first_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.last_name') }}</flux:label>
                <flux:input wire:model="form.last_name" />
                <flux:error name="form.last_name" />
            </flux:field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.mobile') }}</flux:label>
                <flux:input
                    wire:model="form.mobile"
                    placeholder="{{ __('general.mobile_placeholder') }}"
                    dir="ltr"
                />
                <flux:error name="form.mobile" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('general.email') }}</flux:label>
                <flux:input
                    type="email"
                    wire:model="form.email"
                    dir="ltr"
                    clearable
                />
                <flux:error name="form.email" />
            </flux:field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('general.gender') }}</flux:label>
                <flux:select wire:model="form.gender" searchable clearable placeholder="{{ __('general.select_gender') }}">
                    @foreach (Gender::cases() as $gender)
                        <flux:select.option :value="$gender->value">{{ $gender->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.gender" />
            </flux:field>

            <x-date-picker
                wire:model="form.birth_date"
                name="form.birth_date"
                :label="__('general.birth_date')"
            />
        </div>

        <flux:file-upload wire:model="form.avatar" label="{{ __('general.avatar') }}">
            <flux:file-upload.dropzone
                heading="{{ __('general.avatar_upload_heading') }}"
                text="{{ __('general.avatar_upload_hint') }}"
            />
        </flux:file-upload>

        <div class="flex flex-col gap-2">
            @if ($form->avatar)
                <flux:file-item
                    :heading="$form->avatar->getClientOriginalName()"
                    :image="$form->avatar->temporaryUrl()"
                    :size="$form->avatar->getSize()"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove
                            wire:click="form.clearAvatar"
                            aria-label="{{ __('general.remove_avatar') }}"
                        />
                    </x-slot>
                </flux:file-item>
            @elseif ($user && filled($user->avatarUrl()) && ! $form->remove_avatar)
                <flux:file-item
                    :heading="__('general.avatar')"
                    :image="$user->avatarUrl()"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove
                            wire:click="form.markAvatarForRemoval"
                            aria-label="{{ __('general.remove_avatar') }}"
                        />
                    </x-slot>
                </flux:file-item>
            @endif
        </div>
        <flux:error name="form.avatar" />

        <flux:button type="submit" variant="primary" color="teal" class="w-full">
            {{ __('general.save') }}
        </flux:button>
    </form>
</flux:modal>

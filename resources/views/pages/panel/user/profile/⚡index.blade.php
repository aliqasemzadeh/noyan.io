<?php

use App\Actions\User\UpdateUserProfileAction;
use App\Enums\Gender;
use App\Livewire\Forms\UserProfileForm;
use App\Models\User;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public UserProfileForm $form;

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->form->setFromUser($user);
    }

    public function save(UpdateUserProfileAction $action): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->form->user = $user;

        $user = $action->handle($user, $this->form->validatedPayload());

        auth()->setUser($user);

        $this->form->setFromUser($user);

        Flux::toast(__('general.profile_updated'));
    }
};
?>

<x-slot name="title">{{ __('general.user_settings') }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ __('general.user_settings') }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4">
            <flux:heading size="xl" level="1">
                {{ __('general.user_settings') }}
            </flux:heading>

            <flux:text class="mt-2 text-base">
                {{ __('general.user_profile_hint') }}
            </flux:text>
        </div>
    </div>

    @if (! auth()->user()->currentBusiness)
        <flux:callout icon="building" variant="secondary" inline>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="sm">{{ __('general.no_business_yet') }}</flux:heading>
                    <flux:text>{{ __('general.create_first_business_hint') }}</flux:text>
                </div>
                <flux:button
                    variant="primary"
                    color="teal"
                    icon="plus"
                    :href="route('user.businesses.create')"
                    wire:navigate
                >
                    {{ __('general.create_business') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('general.profile') }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.user_profile_form_hint') }}</flux:text>
            </div>

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
                    <flux:label>{{ __('general.email') }}</flux:label>
                    <flux:input
                        type="email"
                        wire:model="form.email"
                        dir="ltr"
                        clearable
                    />
                    <flux:error name="form.email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.mobile') }}</flux:label>
                    <flux:input
                        :value="auth()->user()->mobile"
                        dir="ltr"
                        disabled
                    />
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
                @elseif (filled(auth()->user()->avatarUrl()) && ! $form->remove_avatar)
                    <flux:file-item
                        :heading="__('general.avatar')"
                        :image="auth()->user()->avatarUrl()"
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

            <flux:button type="submit" variant="primary" color="orange" class="w-full">
                {{ __('general.save') }}
            </flux:button>
        </form>
    </flux:card>
</div>

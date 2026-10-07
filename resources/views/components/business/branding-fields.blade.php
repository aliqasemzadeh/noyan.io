@props([
    'existingLogoUrl' => null,
    'removeLogo' => false,
    'logo' => null,
])

<div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
    <div>
        <flux:heading size="lg">{{ __('general.invoice_branding') }}</flux:heading>
        <flux:text class="mt-1">{{ __('general.invoice_branding_hint') }}</flux:text>
    </div>

    <flux:field>
        <flux:label>{{ __('general.phone') }}</flux:label>
        <flux:input
            wire:model="form.phone"
            placeholder="{{ __('general.phone_placeholder') }}"
            clearable
            dir="ltr"
        />
        <flux:error name="form.phone" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('general.address') }}</flux:label>
        <flux:textarea
            wire:model="form.address"
            placeholder="{{ __('general.address_placeholder') }}"
            rows="3"
        />
        <flux:error name="form.address" />
    </flux:field>

    <flux:file-upload wire:model="form.logo" label="{{ __('general.logo') }}">
        <flux:file-upload.dropzone
            heading="{{ __('general.logo_upload_heading') }}"
            text="{{ __('general.logo_upload_hint') }}"
        />
    </flux:file-upload>

    <div class="flex flex-col gap-2">
        @if ($logo)
            <flux:file-item
                :heading="$logo->getClientOriginalName()"
                :image="$logo->temporaryUrl()"
                :size="$logo->getSize()"
            >
                <x-slot name="actions">
                    <flux:file-item.remove
                        wire:click="form.clearLogo"
                        aria-label="{{ __('general.remove_logo') }}"
                    />
                </x-slot>
            </flux:file-item>
        @elseif (filled($existingLogoUrl) && ! $removeLogo)
            <flux:file-item
                :heading="__('general.logo')"
                :image="$existingLogoUrl"
            >
                <x-slot name="actions">
                    <flux:file-item.remove
                        wire:click="form.markLogoForRemoval"
                        aria-label="{{ __('general.remove_logo') }}"
                    />
                </x-slot>
            </flux:file-item>
        @endif
    </div>
    <flux:error name="form.logo" />

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:field>
            <flux:label>{{ __('general.invoice_primary_color') }}</flux:label>
            <flux:color-picker
                wire:model="form.invoice_primary_color"
                format="hex"
                clearable
                placeholder="#0d9488"
            />
            <flux:error name="form.invoice_primary_color" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('general.invoice_secondary_color') }}</flux:label>
            <flux:color-picker
                wire:model="form.invoice_secondary_color"
                format="hex"
                clearable
                placeholder="#134e4a"
            />
            <flux:error name="form.invoice_secondary_color" />
        </flux:field>
    </div>
</div>

@props([
    'model',
    'files' => [],
    'errorPrefix',
    'removeAction',
])

@php
    /** @var array<int, mixed> $files */
    $files = is_array($files) ? $files : [];
@endphp

<div {{ $attributes->class('space-y-3') }}>
    <flux:file-upload wire:model="{{ $model }}" multiple label="{{ __('general.ticket_images') }}">
        <flux:file-upload.dropzone
            heading="{{ __('general.ticket_images_upload_heading') }}"
            text="{{ __('general.ticket_images_upload_hint') }}"
            inline
        />
    </flux:file-upload>

    @if ($files !== [])
        <div class="flex flex-col gap-2">
            @foreach ($files as $index => $file)
                @continue(! is_object($file) || ! method_exists($file, 'getClientOriginalName'))
                <flux:file-item
                    :heading="$file->getClientOriginalName()"
                    :image="$file->temporaryUrl()"
                    :size="$file->getSize()"
                    wire:key="ticket-upload-{{ str_replace('.', '-', $model) }}-{{ $index }}-{{ method_exists($file, 'getFilename') ? $file->getFilename() : $index }}"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove
                            wire:click="{{ $removeAction }}({{ $index }})"
                            aria-label="{{ __('general.remove_ticket_image') }}"
                        />
                    </x-slot>
                </flux:file-item>
            @endforeach
        </div>
    @endif

    <flux:error name="{{ $errorPrefix }}" />
    <flux:error name="{{ $errorPrefix }}.*" />
</div>

@props(['files'])

@if ($files->isNotEmpty())
    <div class="mt-3 flex flex-wrap gap-2">
        @foreach ($files as $file)
            <flux:modal.trigger :name="'ticket-file-preview.'.$file->id" wire:key="file-trigger-{{ $file->id }}">
                <button
                    type="button"
                    class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 focus:outline-none focus:ring-2 focus:ring-teal-500"
                >
                    <img
                        src="{{ route('support.ticket-files.show', $file) }}"
                        alt="{{ $file->original_name }}"
                        class="h-20 w-20 object-cover"
                        loading="lazy"
                    />
                </button>
            </flux:modal.trigger>

            <flux:modal :name="'ticket-file-preview.'.$file->id" class="max-w-3xl" wire:key="file-modal-{{ $file->id }}">
                <div class="space-y-4">
                    <flux:heading size="lg">{{ __('general.preview_image') }}</flux:heading>
                    <img
                        src="{{ route('support.ticket-files.show', $file) }}"
                        alt="{{ $file->original_name }}"
                        class="mx-auto max-h-[70vh] w-auto max-w-full rounded-lg object-contain select-none"
                        draggable="false"
                    />
                </div>
            </flux:modal>
        @endforeach
    </div>
@endif

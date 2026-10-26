@if ($form->acceptsDetailLevels())
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <flux:heading size="sm">{{ __('general.detail_levels') }}</flux:heading>
            <flux:tooltip content="{{ __('general.add_detail_level') }}">
                <flux:button type="button" size="xs" variant="primary" color="teal" icon="plus" wire:click="addDetailLevel" />
            </flux:tooltip>
        </div>

        @foreach ($form->detailLevels as $index => $detailLevel)
            <div class="flex items-end gap-2" wire:key="detail-level-{{ $detailLevel['level'] }}-{{ $index }}">
                <div class="w-28 shrink-0">
                    <flux:input
                        :value="__('general.detail_level_label', ['level' => $detailLevel['level']])"
                        :label="__('general.account_level')"
                        disabled
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:select
                        wire:model="form.detailLevels.{{ $index }}.detail_type"
                        :label="__('general.detail_level_type')"
                        variant="listbox"
                        searchable
                        placeholder="{{ __('general.none') }}"
                    >
                        @foreach (\App\Enums\Accounting\DetailType::cases() as $type)
                            <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.detailLevels.{{ $index }}.detail_type" />
                </div>
                <flux:tooltip content="{{ __('general.remove_detail_level') }}">
                    <flux:button type="button" size="xs" variant="primary" color="red" icon="trash" wire:click="removeDetailLevel({{ $index }})" />
                </flux:tooltip>
            </div>
        @endforeach
    </div>
@endif

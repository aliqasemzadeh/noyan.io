@props([
    'items',
    'wireKeyPrefix' => 'document-item',
    'productsFor',
])

<div {{ $attributes }}>
    {{-- Desktop column headers --}}
    <div class="mb-2 hidden gap-3 border-b border-zinc-200 px-2 pb-2 text-sm font-medium text-zinc-500 dark:border-zinc-700 dark:text-zinc-400 lg:grid lg:grid-cols-[2rem_minmax(0,1.6fr)_repeat(4,minmax(0,7rem))_minmax(0,8rem)_2.5rem]">
        <div></div>
        <div>{{ __('general.item_title') }}</div>
        <div>{{ __('general.quantity') }}</div>
        <div>{{ __('general.unit_price') }}</div>
        <div>{{ __('general.discount_amount') }}</div>
        <div>{{ __('general.tax_amount') }}</div>
        <div>{{ __('general.row_total') }}</div>
        <div></div>
    </div>

    <div class="space-y-3 lg:space-y-0" wire:sort="updateSortOrder">
        @foreach ($items as $index => $item)
            <div
                wire:key="{{ $wireKeyPrefix }}-{{ $item['id'] }}"
                wire:sort:item="{{ $item['id'] }}"
                class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 lg:rounded-none lg:border-0 lg:border-b lg:border-zinc-100 lg:bg-transparent lg:p-2 lg:shadow-none lg:dark:border-zinc-800 lg:dark:bg-transparent"
            >
                <div class="grid gap-3 lg:grid-cols-[2rem_minmax(0,1.6fr)_repeat(4,minmax(0,7rem))_minmax(0,8rem)_2.5rem] lg:items-start">
                    {{-- Drag handle + mobile actions --}}
                    <div class="flex items-center justify-between lg:block lg:pt-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400">
                            <flux:icon.grip-vertical class="size-4" />
                        </div>

                        <div class="flex items-center gap-2 lg:hidden" wire:sort:ignore>
                            <flux:text class="text-sm font-medium">
                                {{ __('general.row_total') }}:
                                <span dir="ltr">{{ $item['total'] }}</span>
                            </flux:text>
                            <flux:tooltip content="{{ __('general.delete') }}">
                                <flux:button
                                    type="button"
                                    size="xs"
                                    variant="danger"
                                    icon="trash"
                                    icon:variant="outline"
                                    wire:click="removeItem({{ $index }})"
                                />
                            </flux:tooltip>
                        </div>
                    </div>

                    {{-- Title / product --}}
                    <div class="min-w-0 space-y-2" wire:sort:ignore>
                        <flux:select
                            wire:model.live="form.items.{{ $index }}.product_id"
                            variant="combobox"
                            clearable
                            placeholder="{{ __('general.select_or_type_item') }}"
                        >
                            <flux:select.input
                                wire:model.live.debounce.300ms="itemSearches.{{ $index }}"
                                placeholder="{{ __('general.select_or_type_item') }}"
                            />

                            @foreach ($productsFor($index) as $product)
                                <flux:select.option
                                    value="{{ $product->id }}"
                                    wire:key="{{ $wireKeyPrefix }}-product-{{ $index }}-{{ $product->id }}"
                                >
                                    {{ $product->name }}
                                </flux:select.option>
                            @endforeach

                            <flux:select.option.create wire:click="useFreeTextItem({{ $index }})" min-length="1">
                                {{ __('general.use_as_free_text_item') }}
                            </flux:select.option.create>
                        </flux:select>

                        <flux:editor
                            class="**:data-[slot=content]:min-h-[100px]!"
                            wire:model="form.items.{{ $index }}.title"
                            toolbar="heading | bold italic underline | align ~ undo redo"
                            placeholder="{{ __('general.item_description_placeholder') }}"
                        />
                        <flux:error name="form.items.{{ $index }}.title" />
                    </div>

                    {{-- Numeric fields: 2-col on mobile, grid cells on lg --}}
                    <div class="grid grid-cols-2 gap-3 lg:contents" wire:sort:ignore>
                        <flux:field>
                            <flux:label class="lg:sr-only">{{ __('general.quantity') }}</flux:label>
                            <flux:input type="number" step="any" wire:model.live.blur="form.items.{{ $index }}.quantity" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="lg:sr-only">{{ __('general.unit_price') }}</flux:label>
                            <flux:input wire:model.live.blur="form.items.{{ $index }}.unit_price" mask:dynamic="$money($input)" dir="ltr" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="lg:sr-only">{{ __('general.discount_amount') }}</flux:label>
                            <flux:input wire:model.live.blur="form.items.{{ $index }}.discount_amount" mask:dynamic="$money($input)" dir="ltr" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="lg:sr-only">{{ __('general.tax_amount') }}</flux:label>
                            <flux:input wire:model.live.blur="form.items.{{ $index }}.tax_amount" mask:dynamic="$money($input)" dir="ltr" />
                        </flux:field>
                    </div>

                    {{-- Desktop row total --}}
                    <div class="hidden lg:block" wire:sort:ignore>
                        <flux:input readonly :value="$item['total']" dir="ltr" />
                    </div>

                    {{-- Desktop delete --}}
                    <div class="hidden pt-1 lg:block" wire:sort:ignore>
                        <flux:tooltip content="{{ __('general.delete') }}">
                            <flux:button
                                type="button"
                                size="xs"
                                variant="danger"
                                icon="trash"
                                icon:variant="outline"
                                wire:click="removeItem({{ $index }})"
                            />
                        </flux:tooltip>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <flux:error name="form.items" />
</div>

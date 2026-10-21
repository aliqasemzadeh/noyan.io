@props([
    'items',
    'wireKeyPrefix' => 'document-item',
    'productsFor',
])

@php
    use App\Enums\Catalog\ProductUnit;
@endphp

<div {{ $attributes }}>
    {{-- Desktop column headers --}}
    <div class="mb-2 hidden gap-2 border-b border-zinc-200 px-2 pb-2 text-sm font-medium text-zinc-500 dark:border-zinc-700 dark:text-zinc-400 xl:grid xl:grid-cols-[2rem_minmax(0,1.4fr)_minmax(0,6rem)_minmax(0,6rem)_repeat(4,minmax(0,6.5rem))_minmax(0,7rem)_2.5rem]">
        <div></div>
        <div>{{ __('general.item_title') }}</div>
        <div>{{ __('general.sku') }}</div>
        <div>{{ __('general.unit') }}</div>
        <div>{{ __('general.quantity') }}</div>
        <div>{{ __('general.unit_price') }}</div>
        <div>{{ __('general.discount_amount') }}</div>
        <div>{{ __('general.tax_amount') }}</div>
        <div>{{ __('general.row_total') }}</div>
        <div></div>
    </div>

    <div class="space-y-3 xl:space-y-0" wire:sort="updateSortOrder">
        @foreach ($items as $index => $item)
            <div
                wire:key="{{ $wireKeyPrefix }}-{{ $item['id'] }}"
                wire:sort:item="{{ $item['id'] }}"
                class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 xl:rounded-none xl:border-0 xl:border-b xl:border-zinc-100 xl:bg-transparent xl:p-2 xl:shadow-none xl:dark:border-zinc-800 xl:dark:bg-transparent"
            >
                <div class="grid gap-3 xl:grid-cols-[2rem_minmax(0,1.4fr)_minmax(0,6rem)_minmax(0,6rem)_repeat(4,minmax(0,6.5rem))_minmax(0,7rem)_2.5rem] xl:items-start">
                    <div class="flex items-center justify-between xl:block xl:pt-2">
                        <div wire:sort:handle class="cursor-grab text-zinc-400">
                            <flux:icon.grip-vertical class="size-4" />
                        </div>

                        <div class="flex items-center gap-2 xl:hidden" wire:sort:ignore>
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

                    <div class="grid grid-cols-2 gap-3 xl:contents" wire:sort:ignore>
                        <flux:field>
                            <flux:label class="xl:sr-only">{{ __('general.sku') }}</flux:label>
                            <flux:input wire:model="form.items.{{ $index }}.sku" clearable dir="ltr" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="xl:sr-only">{{ __('general.unit') }}</flux:label>
                            <flux:select wire:model="form.items.{{ $index }}.unit" searchable clearable placeholder="{{ __('general.unit') }}">
                                @foreach (ProductUnit::cases() as $unit)
                                    <flux:select.option value="{{ $unit->value }}">{{ $unit->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label class="xl:sr-only">{{ __('general.quantity') }}</flux:label>
                            <flux:input type="number" step="any" wire:model.live.blur="form.items.{{ $index }}.quantity" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="xl:sr-only">{{ __('general.unit_price') }}</flux:label>
                            <flux:input wire:model.live.blur="form.items.{{ $index }}.unit_price" mask:dynamic="$money($input)" dir="ltr" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="xl:sr-only">{{ __('general.discount_amount') }}</flux:label>
                            <flux:input wire:model.live.blur="form.items.{{ $index }}.discount_amount" mask:dynamic="$money($input)" dir="ltr" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="xl:sr-only">{{ __('general.tax_amount') }}</flux:label>
                            <flux:input wire:model.live.blur="form.items.{{ $index }}.tax_amount" mask:dynamic="$money($input)" dir="ltr" />
                        </flux:field>
                    </div>

                    <div class="hidden xl:block" wire:sort:ignore>
                        <flux:input readonly :value="$item['total']" dir="ltr" />
                    </div>

                    <div class="hidden pt-1 xl:block" wire:sort:ignore>
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

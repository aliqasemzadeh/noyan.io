<?php

use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Livewire\Forms\Accounting\InvoiceForm;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Catalog\Product;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public InvoiceForm $form;

    public string $partySearch = '';

    /** @var array<int|string, string> */
    public array $itemSearches = [];

    public function mount(?string $type = null, ?Invoice $invoice = null): void
    {
        Auth::user()?->ensureCurrentBusiness();

        if ($invoice !== null) {
            abort_unless((int) $invoice->business_id === (int) Auth::user()?->current_business_id, 404);
            abort_unless($invoice->isDraft(), 403);

            $this->form->setInvoice($invoice);

            return;
        }

        $resolvedType = InvoiceType::tryFrom((string) $type);

        if ($resolvedType === null || ! in_array($resolvedType, InvoiceType::creatable(), true)) {
            abort(404);
        }

        $this->form->setType($resolvedType->value);
        $this->form->initializeDefaults();
    }

    public function updatedFormGlobalDiscount(): void
    {
        $this->form->recalculate();
    }

    public function updatedFormGlobalTax(): void
    {
        $this->form->recalculate();
    }

    public function updatedFormItems($value, string $key): void
    {
        if (preg_match('/^(\d+)\.product_id$/', $key, $matches) === 1 && filled($value)) {
            $this->form->selectProduct((int) $matches[1], (int) $value);

            return;
        }

        if (str_ends_with($key, 'quantity')
            || str_ends_with($key, 'unit_price')
            || str_ends_with($key, 'discount_amount')
            || str_ends_with($key, 'tax_amount')
            || str_ends_with($key, 'title')) {
            $this->form->recalculate();
        }
    }

    public function updatedFormPartyId(?int $partyId): void
    {
        if ($partyId === null) {
            return;
        }

        $party = $this->parties->firstWhere('id', $partyId);

        if ($party !== null) {
            $this->form->selectParty($party->id, $party->displayName());
        }
    }

    public function addItem(): void
    {
        $this->form->addItem();
    }

    public function removeItem(int $index): void
    {
        $this->form->removeItem($index);
        unset($this->itemSearches[$index]);
    }

    public function updateSortOrder(string|int $id, int $position): void
    {
        $this->form->updateSortOrder($id, $position);
    }

    public function useGuestParty(): void
    {
        $name = trim($this->partySearch);

        if ($name === '') {
            return;
        }

        $this->form->useGuestParty($name);
        $this->partySearch = '';
    }

    public function selectProductForItem(int $index, int $productId): void
    {
        $this->form->selectProduct($index, $productId);
        $this->itemSearches[$index] = '';
    }

    public function useFreeTextItem(int $index): void
    {
        $title = trim((string) ($this->itemSearches[$index] ?? ''));

        if ($title === '') {
            return;
        }

        $this->form->useFreeTextItem($index, $title);
        $this->itemSearches[$index] = '';
    }

    public function saveAsDraft(SaveInvoiceAction $action): void
    {
        $invoice = $this->form->saveAsDraft($action);

        Flux::toast(__('general.invoice_draft_saved', ['number' => $invoice->invoice_number]));

        $this->redirect(route('accounting.invoices.edit', $invoice), navigate: true);
    }

    public function saveAndFinalize(SaveInvoiceAction $saveAction, FinalizeInvoiceAction $finalizeAction): void
    {
        $invoice = $this->form->saveAndFinalize($saveAction, $finalizeAction);

        Flux::toast(__('general.invoice_finalized', ['number' => $invoice->invoice_number]));

        $this->redirect(route('accounting.invoices.index'), navigate: true);
    }

    /**
     * @return Collection<int, Party>
     */
    #[Computed]
    public function parties(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        $type = InvoiceType::tryFrom($this->form->type);

        return Party::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->when($type === InvoiceType::Sale, fn ($query) => $query->where('is_customer', true))
            ->when($type === InvoiceType::Purchase, fn ($query) => $query->where('is_supplier', true))
            ->when($this->partySearch !== '', function ($query): void {
                $search = '%'.$this->partySearch.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('legal_name', 'like', $search)
                        ->orWhere('mobile', 'like', $search);
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function productsFor(int $index): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        $search = trim((string) ($this->itemSearches[$index] ?? ''));

        return Product::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'sku', 'sale_price', 'purchase_price']);
    }

    public function heading(): string
    {
        if ($this->form->invoice !== null) {
            return __('general.edit_invoice', ['number' => $this->form->invoice->invoice_number]);
        }

        return $this->form->type === InvoiceType::Purchase->value
            ? __('general.new_purchase_invoice')
            : __('general.new_sale_invoice');
    }
};
?>

<x-slot name="title">{{ $this->heading() }} - {{ __('general.app_name') }}</x-slot>

<div class="space-y-6">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('user.dashboard')" wire:navigate>
                {{ __('general.dashboard') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.dashboard')" wire:navigate>
                {{ __('general.accounting') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('accounting.invoices.index')" wire:navigate>
                {{ __('general.invoices') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $this->heading() }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $this->heading() }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.invoice_form_hint') }}</flux:text>
            </div>

            <flux:badge size="sm" :color="$form->type === 'purchase' ? 'amber' : 'teal'">
                {{ InvoiceType::from($form->type)->label() }}
            </flux:badge>
        </div>
    </div>

    <form wire:submit="saveAsDraft" class="space-y-6">
        <flux:card class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('general.party') }}</flux:label>
                    <flux:select
                        wire:model.live="form.party_id"
                        variant="combobox"
                        clearable
                        placeholder="{{ __('general.select_party_or_guest') }}"
                    >
                        <flux:select.input wire:model.live.debounce.300ms="partySearch" placeholder="{{ __('general.select_party_or_guest') }}" />

                        @foreach ($this->parties as $party)
                            <flux:select.option value="{{ $party->id }}" wire:key="invoice-party-{{ $party->id }}">
                                {{ $party->displayName() }}
                            </flux:select.option>
                        @endforeach

                        <flux:select.option.create wire:click="useGuestParty" min-length="2">
                            {{ __('general.use_as_guest_party') }}
                        </flux:select.option.create>
                    </flux:select>
                    <flux:error name="form.party_id" />
                    <flux:error name="form.party_name" />
                    @if ($form->party_id === null && $form->party_name !== '')
                        <flux:text class="mt-1 text-sm">
                            {{ __('general.guest_party') }}: <span class="font-medium">{{ $form->party_name }}</span>
                        </flux:text>
                    @endif
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('general.issue_date') }}</flux:label>
                        <flux:input type="date" wire:model="form.issue_date" />
                        <flux:error name="form.issue_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('general.due_date') }}</flux:label>
                        <flux:input type="date" wire:model="form.due_date" />
                        <flux:error name="form.due_date" />
                    </flux:field>
                </div>
            </div>

            <flux:field>
                <flux:label>{{ __('general.note') }}</flux:label>
                <flux:textarea wire:model="form.note" rows="2" clearable />
                <flux:error name="form.note" />
            </flux:field>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.invoice_items') }}</flux:heading>
                <flux:button type="button" size="sm" variant="primary" color="zinc" icon="plus" wire:click="addItem">
                    {{ __('general.add_item') }}
                </flux:button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-start dark:border-zinc-700">
                            <th class="w-10 px-2 py-2"></th>
                            <th class="px-2 py-2 text-start">{{ __('general.item_title') }}</th>
                            <th class="w-28 px-2 py-2 text-start">{{ __('general.quantity') }}</th>
                            <th class="w-36 px-2 py-2 text-start">{{ __('general.unit_price') }}</th>
                            <th class="w-32 px-2 py-2 text-start">{{ __('general.discount_amount') }}</th>
                            <th class="w-32 px-2 py-2 text-start">{{ __('general.tax_amount') }}</th>
                            <th class="w-36 px-2 py-2 text-start">{{ __('general.row_total') }}</th>
                            <th class="w-12 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody wire:sort="updateSortOrder">
                        @foreach ($form->items as $index => $item)
                            <tr
                                wire:key="invoice-item-{{ $item['id'] }}"
                                wire:sort:item="{{ $item['id'] }}"
                                class="border-b border-zinc-100 dark:border-zinc-800"
                            >
                                <td class="px-2 py-2 align-top">
                                    <div wire:sort:handle class="cursor-grab pt-2 text-zinc-400">
                                        <flux:icon.grip-vertical class="size-4" />
                                    </div>
                                </td>
                                <td class="min-w-64 px-2 py-2 align-top" wire:sort:ignore>
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

                                        @foreach ($this->productsFor($index) as $product)
                                            <flux:select.option
                                                value="{{ $product->id }}"
                                                wire:key="invoice-item-product-{{ $index }}-{{ $product->id }}"
                                            >
                                                {{ $product->name }}
                                            </flux:select.option>
                                        @endforeach

                                        <flux:select.option.create wire:click="useFreeTextItem({{ $index }})" min-length="1">
                                            {{ __('general.use_as_free_text_item') }}
                                        </flux:select.option.create>
                                    </flux:select>

                                    @if (empty($item['product_id']) && filled($item['title']))
                                        <flux:input class="mt-2" wire:model.live="form.items.{{ $index }}.title" />
                                    @elseif (filled($item['title']))
                                        <flux:text class="mt-1 block text-xs">{{ $item['title'] }}</flux:text>
                                    @endif
                                    <flux:error name="form.items.{{ $index }}.title" />
                                </td>
                                <td class="px-2 py-2 align-top" wire:sort:ignore>
                                    <flux:input type="number" step="any" wire:model.live="form.items.{{ $index }}.quantity" />
                                </td>
                                <td class="px-2 py-2 align-top" wire:sort:ignore>
                                    <flux:input type="number" step="any" wire:model.live="form.items.{{ $index }}.unit_price" />
                                </td>
                                <td class="px-2 py-2 align-top" wire:sort:ignore>
                                    <flux:input type="number" step="any" wire:model.live="form.items.{{ $index }}.discount_amount" />
                                </td>
                                <td class="px-2 py-2 align-top" wire:sort:ignore>
                                    <flux:input type="number" step="any" wire:model.live="form.items.{{ $index }}.tax_amount" />
                                </td>
                                <td class="px-2 py-2 align-top">
                                    <flux:input readonly :value="$item['total']" dir="ltr" />
                                </td>
                                <td class="px-2 py-2 align-top" wire:sort:ignore>
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
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <flux:error name="form.items" />
        </flux:card>

        <flux:card>
            <div class="grid gap-4 md:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('general.global_discount') }}</flux:label>
                    <flux:input type="number" step="any" wire:model.live="form.global_discount" />
                    <flux:error name="form.global_discount" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.global_tax') }}</flux:label>
                    <flux:input type="number" step="any" wire:model.live="form.global_tax" />
                    <flux:error name="form.global_tax" />
                </flux:field>

                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900">
                    <flux:text>{{ __('general.items_total') }}: <span dir="ltr">{{ $form->itemsTotal() }}</span></flux:text>
                    <flux:heading size="lg" class="mt-2">
                        {{ __('general.grand_total') }}: <span dir="ltr">{{ $form->grandTotal() }}</span>
                    </flux:heading>
                </div>
            </div>
        </flux:card>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:button type="submit" variant="primary" color="zinc" class="w-full sm:w-auto">
                {{ __('general.save_as_draft') }}
            </flux:button>
            <flux:button type="button" variant="primary" color="teal" class="w-full sm:w-auto" wire:click="saveAndFinalize">
                {{ __('general.save_and_finalize') }}
            </flux:button>
        </div>
    </form>
</div>

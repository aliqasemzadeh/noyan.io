<?php

use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Enums\Business\BusinessPermission;
use App\Livewire\Forms\Accounting\InvoiceForm;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Party;
use App\Models\Accounting\SaleKind;
use App\Models\Catalog\Product;
use App\Support\Business\BusinessPanelContext;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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

        if (BusinessPanelContext::isSale()) {
            $permission = $invoice !== null
                ? BusinessPermission::InvoiceEdit
                : BusinessPermission::InvoiceCreate;

            abort_unless(Auth::user()?->canInBusiness($permission), 403);
        }

        if ($invoice !== null) {
            abort_unless((int) $invoice->business_id === (int) Auth::user()?->current_business_id, 404);
            abort_unless($invoice->isDraft(), 403);

            if (BusinessPanelContext::isSale()) {
                abort_unless($invoice->type === InvoiceType::Sale, 403);
            }

            $invoice->loadMissing('quote');
            $this->form->setInvoice($invoice);

            return;
        }

        $resolvedType = InvoiceType::tryFrom((string) $type);

        if ($resolvedType === null || ! in_array($resolvedType, InvoiceType::creatable(), true)) {
            abort(404);
        }

        if (BusinessPanelContext::isSale() && $resolvedType !== InvoiceType::Sale) {
            abort(403);
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
            || str_ends_with($key, 'tax_amount')) {
            $this->form->recalculate();
        }
    }

    public function updatedFormPartyId(?int $partyId): void
    {
        if ($partyId === null) {
            $this->form->party_name = '';

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

    #[On('panels.accounting.party.created')]
    public function onPartyCreated(int $partyId): void
    {
        unset($this->parties);

        $party = $this->parties->firstWhere('id', $partyId);

        if ($party === null) {
            $party = Party::query()
                ->where('business_id', Auth::user()?->current_business_id)
                ->whereKey($partyId)
                ->first();
        }

        if ($party === null) {
            return;
        }

        $this->form->selectParty($party->id, $party->displayName());
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

    public function recalculateTotals(): void
    {
        $this->form->recalculate();
    }

    public function saveAsDraft(SaveInvoiceAction $action): void
    {
        $invoice = $this->form->saveAsDraft($action);

        Flux::toast(__('general.invoice_draft_saved', ['number' => $invoice->invoice_number]));

        $this->redirect(BusinessPanelContext::route('invoices.edit', $invoice), navigate: true);
    }

    public function saveAndFinalize(SaveInvoiceAction $saveAction, FinalizeInvoiceAction $finalizeAction): void
    {
        $invoice = $this->form->saveAndFinalize($saveAction, $finalizeAction);

        Flux::toast(__('general.invoice_finalized', ['number' => $invoice->invoice_number]));

        $this->redirect(BusinessPanelContext::route('invoices.index'), navigate: true);
    }

    public function panelRoute(string $name, mixed $parameters = []): string
    {
        return BusinessPanelContext::route($name, $parameters);
    }

    public function panelLabel(): string
    {
        return BusinessPanelContext::current()->label();
    }

    /**
     * @return Collection<int, SaleKind>
     */
    #[Computed]
    public function saleKinds(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return SaleKind::query()
            ->enabledForBusiness($businessId)
            ->get();
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
     * @return Collection<int, Account>
     */
    #[Computed]
    public function selectableAccounts(): Collection
    {
        $businessId = Auth::user()?->current_business_id;

        if ($businessId === null) {
            return collect();
        }

        return Account::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'bank_name', 'account_number', 'iban', 'card_number']);
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
            <flux:breadcrumbs.item :href="$this->panelRoute('dashboard')" wire:navigate>
                {{ $this->panelLabel() }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="$this->panelRoute('invoices.index')" wire:navigate>
                {{ __('general.invoices') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ $this->heading() }}
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <flux:heading size="xl">{{ $this->heading() }}</flux:heading>
                <flux:text class="mt-1">{{ __('general.invoice_form_hint') }}</flux:text>
            </div>

            <flux:badge size="sm" :color="$form->type === 'purchase' ? 'amber' : 'teal'" class="self-start sm:self-auto">
                {{ InvoiceType::from($form->type)->label() }}
            </flux:badge>
        </div>
    </div>

    <form wire:submit="saveAsDraft" class="space-y-6">
        @if ($form->invoice?->quote)
            <flux:callout icon="file-pen-line" variant="secondary" inline>
                {{ __('general.invoice_created_from_quote', ['number' => $form->invoice->quote->quote_number]) }}
                <flux:link class="ms-2" :href="$this->panelRoute('quotes.index', ['search' => $form->invoice->quote->quote_number])" wire:navigate>
                    {{ $form->invoice->quote->quote_number }}
                </flux:link>
            </flux:callout>
        @endif

        <flux:card class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                @if ($this->saleKinds->isNotEmpty())
                    <flux:field>
                        <flux:label>{{ __('general.sale_kind') }}</flux:label>
                        <flux:select
                            wire:model="form.sale_kind_id"
                            searchable
                            clearable
                            placeholder="{{ __('general.select_sale_kind') }}"
                        >
                            @foreach ($this->saleKinds as $saleKind)
                                <flux:select.option value="{{ $saleKind->id }}" wire:key="invoice-sale-kind-{{ $saleKind->id }}">
                                    {{ $saleKind->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.sale_kind_id" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('general.party') }}</flux:label>
                    <flux:select
                        wire:model.live="form.party_id"
                        variant="combobox"
                        clearable
                        placeholder="{{ __('general.select_party') }}"
                    >
                        <flux:select.input wire:model.live.debounce.300ms="partySearch" placeholder="{{ __('general.select_party') }}" />

                        @foreach ($this->parties as $party)
                            <flux:select.option value="{{ $party->id }}" wire:key="invoice-party-{{ $party->id }}">
                                {{ $party->displayName() }}
                            </flux:select.option>
                        @endforeach

                        <flux:select.option.create modal="party.create">
                            {{ __('general.create_party') }}
                        </flux:select.option.create>
                    </flux:select>
                    <flux:error name="form.party_id" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    @if (\App\Support\LocaleDate::usesJalali())
                        <x-date-picker wire:model="form.issue_date" name="form.issue_date" :label="__('general.issue_date')" required />
                        <x-date-picker wire:model="form.due_date" name="form.due_date" :label="__('general.due_date')" />
                    @else
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
                    @endif
                </div>
            </div>

            <flux:field>
                <flux:label>{{ __('general.note') }}</flux:label>
                <flux:textarea wire:model="form.note" rows="2" clearable />
                <flux:error name="form.note" />
            </flux:field>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('general.invoice_items') }}</flux:heading>
                <flux:button type="button" size="sm" variant="primary" color="zinc" icon="plus" wire:click="addItem" class="w-full sm:w-auto">
                    {{ __('general.add_item') }}
                </flux:button>
            </div>

            <x-accounting.document-line-items
                :items="$form->items"
                wire-key-prefix="invoice-item"
                :products-for="fn (int $index) => $this->productsFor($index)"
            />
        </flux:card>

        <flux:card>
            <div class="grid gap-4 md:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('general.global_discount') }}</flux:label>
                    <flux:input wire:model.live.blur="form.global_discount" mask:dynamic="$money($input)" dir="ltr" />
                    <flux:error name="form.global_discount" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('general.global_tax') }}</flux:label>
                    <flux:input wire:model.live.blur="form.global_tax" mask:dynamic="$money($input)" dir="ltr" />
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

        @if ($this->selectableAccounts->isNotEmpty())
            <flux:card>
                <flux:accordion transition>
                    <flux:accordion.item heading="{{ __('general.additional_settings') }}">
                        <div class="space-y-3 pt-2">
                            <flux:checkbox.group
                                wire:model="form.account_ids"
                                label="{{ __('general.invoice_payment_accounts') }}"
                                description="{{ __('general.invoice_payment_accounts_hint') }}"
                            >
                                @foreach ($this->selectableAccounts as $account)
                                    <flux:checkbox
                                        wire:key="invoice-account-{{ $account->id }}"
                                        value="{{ $account->id }}"
                                        :label="$account->name"
                                        :description="collect([
                                            $account->bank_name,
                                            $account->iban ?: $account->account_number ?: $account->card_number,
                                        ])->filter()->implode(' — ')"
                                    />
                                @endforeach
                            </flux:checkbox.group>
                            <flux:error name="form.account_ids" />
                            <flux:error name="form.account_ids.*" />
                        </div>
                    </flux:accordion.item>
                </flux:accordion>
            </flux:card>
        @endif

        <div class="sticky bottom-0 z-10 -mx-1 border-t border-zinc-200 bg-white/95 px-1 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95 sm:static sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none dark:sm:bg-transparent">
            <div class="flex flex-col gap-3 sm:flex-row">
                <flux:button type="submit" variant="primary" color="zinc" class="w-full sm:w-auto">
                    {{ $form->invoice !== null ? __('general.update') : __('general.save_as_draft') }}
                </flux:button>
                <flux:button type="button" variant="primary" color="sky" class="w-full sm:w-auto" wire:click="recalculateTotals">
                    {{ __('general.recalculate_totals') }}
                </flux:button>
                <flux:button type="button" variant="primary" color="teal" class="w-full sm:w-auto" wire:click="saveAndFinalize">
                    {{ __('general.save_and_finalize') }}
                </flux:button>
            </div>
        </div>
    </form>

    <livewire:accounting.party.create :key="'invoice-party-create'" />
</div>

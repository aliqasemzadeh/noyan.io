<?php

namespace App\Livewire\Forms\Accounting;

use App\Actions\Invoices\FinalizeInvoiceAction;
use App\Actions\Invoices\SaveInvoiceAction;
use App\Enums\Accounting\InvoiceType;
use App\Models\Accounting\Invoice;
use App\Models\Catalog\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class InvoiceForm extends Form
{
    public ?Invoice $invoice = null;

    public string $type = InvoiceType::Sale->value;

    public ?int $party_id = null;

    public string $party_name = '';

    public string $issue_date = '';

    public string $due_date = '';

    public string $global_discount = '0';

    public string $global_tax = '0';

    public string $note = '';

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function setType(string $type): void
    {
        $this->type = InvoiceType::from($type)->value;
    }

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice;
        $this->type = $invoice->type->value;
        $this->party_id = $invoice->party_id;
        $this->party_name = (string) ($invoice->party_name ?? '');
        $this->issue_date = $invoice->issue_date?->format('Y-m-d') ?? '';
        $this->due_date = $invoice->due_date?->format('Y-m-d') ?? '';
        $this->global_discount = $this->formatAmount((string) $invoice->global_discount);
        $this->global_tax = $this->formatAmount((string) $invoice->global_tax);
        $this->note = (string) ($invoice->note ?? '');
        $this->items = $invoice->items
            ->map(fn ($item): array => [
                'id' => (string) $item->id,
                'product_id' => $item->product_id,
                'title' => $item->title,
                'quantity' => $this->formatAmount((string) $item->quantity),
                'unit_price' => $this->formatAmount((string) $item->unit_price),
                'discount_amount' => $this->formatAmount((string) $item->discount_amount),
                'tax_amount' => $this->formatAmount((string) $item->tax_amount),
                'total' => $this->formatAmount((string) $item->total),
                'sort_order' => (int) $item->sort_order,
            ])
            ->values()
            ->all();

        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function initializeDefaults(): void
    {
        $this->issue_date = now()->toDateString();
        $this->due_date = '';
        $this->global_discount = '0';
        $this->global_tax = '0';
        $this->note = '';
        $this->party_id = null;
        $this->party_name = '';
        $this->items = [];
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'id' => (string) Str::uuid(),
            'product_id' => null,
            'title' => '',
            'quantity' => '1',
            'unit_price' => '0',
            'discount_amount' => '0',
            'tax_amount' => '0',
            'total' => '0',
            'sort_order' => count($this->items),
        ];
    }

    public function removeItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->reindexSortOrder();
        $this->recalculate();
    }

    public function updateSortOrder(string|int $id, int $position): void
    {
        $id = (string) $id;
        $items = collect($this->items)->values();
        $currentIndex = $items->search(fn (array $item): bool => (string) $item['id'] === $id);

        if ($currentIndex === false) {
            return;
        }

        $item = $items->pull($currentIndex);
        $items = $items->values();
        $items->splice($position, 0, [$item]);

        $this->items = $items->values()->all();
        $this->reindexSortOrder();
    }

    public function selectProduct(int $index, int $productId): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $businessId = Auth::user()?->current_business_id;

        $product = Product::query()
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->whereKey($productId)
            ->first();

        if ($product === null) {
            return;
        }

        $unitPrice = $this->type === InvoiceType::Purchase->value
            ? (string) $product->purchase_price
            : (string) $product->sale_price;

        $this->items[$index]['product_id'] = $product->id;
        $this->items[$index]['title'] = $product->name;
        $this->items[$index]['unit_price'] = $this->formatAmount($unitPrice);
        $this->recalculate();
    }

    public function useFreeTextItem(int $index, string $title): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['product_id'] = null;
        $this->items[$index]['title'] = trim($title);
        $this->recalculate();
    }

    public function useGuestParty(string $name): void
    {
        $this->party_id = null;
        $this->party_name = trim($name);
    }

    public function selectParty(int $partyId, string $name): void
    {
        $this->party_id = $partyId;
        $this->party_name = $name;
    }

    public function recalculate(): void
    {
        foreach ($this->items as $index => $item) {
            $quantity = $this->normalizeAmount((string) ($item['quantity'] ?? '0'));
            $unitPrice = $this->normalizeAmount((string) ($item['unit_price'] ?? '0'));
            $discount = $this->normalizeAmount((string) ($item['discount_amount'] ?? '0'));
            $tax = $this->normalizeAmount((string) ($item['tax_amount'] ?? '0'));
            $line = bcmul($quantity, $unitPrice, 18);
            $total = bcadd(bcsub($line, $discount, 18), $tax, 18);

            $this->items[$index]['quantity'] = $this->formatAmount($quantity);
            $this->items[$index]['unit_price'] = $this->formatAmount($unitPrice);
            $this->items[$index]['discount_amount'] = $this->formatAmount($discount);
            $this->items[$index]['tax_amount'] = $this->formatAmount($tax);
            $this->items[$index]['total'] = $this->formatAmount($total);
            $this->items[$index]['sort_order'] = $index;
        }
    }

    public function itemsTotal(): string
    {
        $this->recalculate();
        $total = '0';

        foreach ($this->items as $item) {
            $total = bcadd($total, $this->normalizeAmount((string) ($item['total'] ?? '0')), 18);
        }

        return $this->formatAmount($total);
    }

    public function grandTotal(): string
    {
        $itemsTotal = $this->normalizeAmount($this->itemsTotal());
        $discount = $this->normalizeAmount($this->global_discount);
        $tax = $this->normalizeAmount($this->global_tax);

        return $this->formatAmount(bcadd(bcsub($itemsTotal, $discount, 18), $tax, 18));
    }

    public function saveAsDraft(SaveInvoiceAction $action): Invoice
    {
        $this->validate($this->rules());
        $this->recalculate();

        $invoice = $action->handle(Auth::user(), $this->payload(), $this->invoice);
        $this->setInvoice($invoice);

        return $invoice;
    }

    public function saveAndFinalize(SaveInvoiceAction $saveAction, FinalizeInvoiceAction $finalizeAction): Invoice
    {
        $this->validate($this->rules(requireItems: true));
        $this->recalculate();

        $invoice = $saveAction->handle(Auth::user(), $this->payload(), $this->invoice);
        $invoice = $finalizeAction->handle($invoice, Auth::user());
        $this->setInvoice($invoice);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(bool $requireItems = false): array
    {
        $businessId = Auth::user()?->current_business_id;
        $amount = ['required', 'string', 'regex:/^-?\d+(\.\d{1,18})?$/'];

        $rules = [
            'type' => ['required', Rule::enum(InvoiceType::class)],
            'party_id' => [
                'nullable',
                'integer',
                Rule::exists('parties', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at')),
            ],
            'party_name' => ['nullable', 'string', 'max:150', 'required_without:party_id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'global_discount' => $amount,
            'global_tax' => $amount,
            'note' => ['nullable', 'string'],
            'items' => [$requireItems ? 'required' : 'nullable', 'array', $requireItems ? 'min:1' : 'min:0'],
            'items.*.title' => [$requireItems ? 'required' : 'nullable', 'string', 'max:255'],
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at')),
            ],
            'items.*.quantity' => $amount,
            'items.*.unit_price' => $amount,
            'items.*.discount_amount' => $amount,
            'items.*.tax_amount' => $amount,
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'party_id' => __('general.party'),
            'party_name' => __('general.party_name'),
            'issue_date' => __('general.issue_date'),
            'due_date' => __('general.due_date'),
            'global_discount' => __('general.global_discount'),
            'global_tax' => __('general.global_tax'),
            'items' => __('general.invoice_items'),
            'items.*.title' => __('general.item_title'),
            'items.*.quantity' => __('general.quantity'),
            'items.*.unit_price' => __('general.unit_price'),
            'items.*.discount_amount' => __('general.discount_amount'),
            'items.*.tax_amount' => __('general.tax_amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'type' => $this->type,
            'party_id' => $this->party_id,
            'party_name' => $this->party_name !== '' ? $this->party_name : null,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date !== '' ? $this->due_date : null,
            'global_discount' => $this->normalizeAmount($this->global_discount),
            'global_tax' => $this->normalizeAmount($this->global_tax),
            'note' => $this->note !== '' ? $this->note : null,
            'items' => collect($this->items)
                ->filter(fn (array $item): bool => trim((string) ($item['title'] ?? '')) !== '')
                ->values()
                ->map(fn (array $item, int $index): array => [
                    'product_id' => $item['product_id'] ?? null,
                    'title' => trim((string) $item['title']),
                    'quantity' => $this->normalizeAmount((string) ($item['quantity'] ?? '1')),
                    'unit_price' => $this->normalizeAmount((string) ($item['unit_price'] ?? '0')),
                    'discount_amount' => $this->normalizeAmount((string) ($item['discount_amount'] ?? '0')),
                    'tax_amount' => $this->normalizeAmount((string) ($item['tax_amount'] ?? '0')),
                    'sort_order' => $index,
                ])
                ->all(),
        ];
    }

    protected function reindexSortOrder(): void
    {
        foreach ($this->items as $index => $item) {
            $this->items[$index]['sort_order'] = $index;
        }
    }

    protected function normalizeAmount(string $value): string
    {
        $value = trim(str_replace(',', '', $value));

        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', 18);
    }

    protected function formatAmount(string $value): string
    {
        $normalized = $this->normalizeAmount($value);
        $trimmed = rtrim(rtrim($normalized, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}

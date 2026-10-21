<?php

namespace App\Livewire\Forms\Accounting;

use App\Actions\Quotes\FinalizeQuoteAction;
use App\Actions\Quotes\SaveQuoteAction;
use App\Livewire\Forms\Concerns\ManagesLineItems;
use App\Models\Accounting\Quote;
use App\Models\Catalog\Product;
use App\Support\Concerns\NormalizesLocaleFormValues;
use App\Support\LocaleDate;
use App\Support\Money;
use App\Support\RichText;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class QuoteForm extends Form
{
    use ManagesLineItems;
    use NormalizesLocaleFormValues;

    public ?Quote $quote = null;

    public ?int $party_id = null;

    public string $party_name = '';

    public string $issue_date = '';

    public string $valid_until = '';

    public string $global_discount = '0';

    public string $global_tax = '0';

    public string $note = '';

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function setQuote(Quote $quote): void
    {
        $this->quote = $quote;
        $this->party_id = $quote->party_id;
        $this->party_name = (string) ($quote->party_name ?? '');
        $this->issue_date = LocaleDate::formatInput($quote->issue_date);
        $this->valid_until = LocaleDate::formatInput($quote->valid_until);
        $this->global_discount = $this->formatAmount((string) $quote->global_discount);
        $this->global_tax = $this->formatAmount((string) $quote->global_tax);
        $this->note = (string) ($quote->note ?? '');
        $this->items = $quote->items
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
        $this->issue_date = $this->todayInput();
        $this->valid_until = LocaleDate::formatInput(now()->addDays(7));
        $this->global_discount = '0';
        $this->global_tax = '0';
        $this->note = '';
        $this->party_id = null;
        $this->party_name = '';
        $this->items = [];
        $this->addItem();
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

        $this->items[$index]['product_id'] = $product->id;
        $this->items[$index]['title'] = RichText::fromPlain($product->name);
        $this->items[$index]['unit_price'] = $this->formatAmount((string) $product->sale_price);
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

    public function saveAsDraft(SaveQuoteAction $action): Quote
    {
        $this->prepareLocaleInputs();
        $this->validate($this->rules());
        $this->recalculate();

        $quote = $action->handle(Auth::user(), $this->payload(), $this->quote);
        $this->setQuote($quote);

        return $quote;
    }

    public function saveAndFinalize(SaveQuoteAction $saveAction, FinalizeQuoteAction $finalizeAction): Quote
    {
        $this->prepareLocaleInputs();
        $this->validate($this->rules(requireItems: true));
        $this->recalculate();

        $quote = $saveAction->handle(Auth::user(), $this->payload(), $this->quote);
        $quote = $finalizeAction->handle($quote, Auth::user());
        $this->setQuote($quote);

        return $quote;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(bool $requireItems = false): array
    {
        $businessId = Auth::user()?->current_business_id;
        $amount = ['required', 'string', 'regex:/^-?\d+(\.\d{1,18})?$/'];

        return [
            'party_id' => [
                'required',
                'integer',
                Rule::exists('parties', 'id')
                    ->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at')),
            ],
            'party_name' => ['nullable', 'string', 'max:150'],
            'issue_date' => $this->localeDateRules(),
            'valid_until' => [...$this->localeDateRules(), 'after_or_equal:issue_date'],
            'global_discount' => $amount,
            'global_tax' => $amount,
            'note' => ['nullable', 'string'],
            'items' => [$requireItems ? 'required' : 'nullable', 'array', $requireItems ? 'min:1' : 'min:0'],
            'items.*.title' => [
                $requireItems ? 'required' : 'nullable',
                'string',
                'max:20000',
                function (string $attribute, mixed $value, \Closure $fail) use ($requireItems): void {
                    if ($requireItems && RichText::isBlank(is_string($value) ? $value : null)) {
                        $fail(__('validation.required', ['attribute' => __('general.item_title')]));
                    }
                },
            ],
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
            'valid_until' => __('general.valid_until'),
            'global_discount' => __('general.global_discount'),
            'global_tax' => __('general.global_tax'),
            'items' => __('general.quote_items'),
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
            'party_id' => $this->party_id,
            'party_name' => $this->party_name !== '' ? $this->party_name : null,
            'issue_date' => LocaleDate::toStorageDate($this->issue_date) ?? $this->issue_date,
            'valid_until' => LocaleDate::toStorageDate($this->valid_until) ?? $this->valid_until,
            'global_discount' => $this->normalizeAmount($this->global_discount),
            'global_tax' => $this->normalizeAmount($this->global_tax),
            'note' => $this->note !== '' ? $this->note : null,
            'items' => collect($this->items)
                ->filter(fn (array $item): bool => ! RichText::isBlank((string) ($item['title'] ?? '')))
                ->values()
                ->map(fn (array $item, int $index): array => [
                    'product_id' => $item['product_id'] ?? null,
                    'title' => (string) $item['title'],
                    'quantity' => $this->normalizeAmount((string) ($item['quantity'] ?? '1')),
                    'unit_price' => $this->normalizeAmount((string) ($item['unit_price'] ?? '0')),
                    'discount_amount' => $this->normalizeAmount((string) ($item['discount_amount'] ?? '0')),
                    'tax_amount' => $this->normalizeAmount((string) ($item['tax_amount'] ?? '0')),
                    'sort_order' => $index,
                ])
                ->all(),
        ];
    }

    protected function prepareLocaleInputs(): void
    {
        $this->normalizeMoneyFields('global_discount', 'global_tax');
        $this->normalizeDateFields('issue_date', 'valid_until');

        foreach ($this->items as $index => $item) {
            foreach (['quantity', 'unit_price', 'discount_amount', 'tax_amount'] as $field) {
                $this->items[$index][$field] = Money::normalize((string) ($item[$field] ?? '0'));
            }
        }
    }
}

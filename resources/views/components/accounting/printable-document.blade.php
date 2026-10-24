@props([
    'business',
    'documentLabel',
    'number',
    'partyName',
    'dates' => [],
    'items',
    'itemsTotal',
    'globalDiscount' => '0',
    'globalTax' => '0',
    'totalAmount',
    'note' => null,
    'formatAmount',
    'accounts' => [],
    'saleKindName' => null,
    'buyerLegalName' => null,
    'buyerNationalId' => null,
    'buyerEconomicCode' => null,
    'buyerPostalCode' => null,
    'buyerPhone' => null,
    'buyerAddress' => null,
    'heading' => null,
])

@php
    use App\Enums\Catalog\ProductUnit;
    use App\Support\Accounting\DocumentLineAmounts;
    use App\Support\PersianAmountInWords;

    $logoUrl = $business->logoUrl();
    $primary = $business->invoicePrimaryColor();
    $initial = mb_substr($business->name, 0, 1);
    $sellerName = method_exists($business, 'displayLegalName')
        ? $business->displayLegalName()
        : $business->name;
    $buyerDisplayName = filled($buyerLegalName) ? $buyerLegalName : ($partyName ?: '—');
    $documentHeading = $heading ?? (
        $documentLabel === __('general.quote')
            ? __('general.formal_quote_title')
            : __('general.formal_invoice_title')
    );
    $headerTint = "color-mix(in srgb, {$primary} 18%, white)";
    $stripeTint = "color-mix(in srgb, {$primary} 8%, white)";
    $borderColor = "color-mix(in srgb, {$primary} 35%, #71717a)";
    $issueDateRow = collect($dates)->firstWhere('label', __('general.issue_date'))
        ?? collect($dates)->first();
    $issueDateValue = is_array($issueDateRow) ? ($issueDateRow['value'] ?? '—') : '—';
    $columnTotals = DocumentLineAmounts::columnTotals($items);
    $accountsList = collect($accounts);
@endphp

<div
    {{ $attributes->merge([
        'class' => 'invoice-sheet overflow-hidden border-2 bg-white text-black dark:bg-white',
        'style' => "border-color: {$borderColor}; print-color-adjust: exact; -webkit-print-color-adjust: exact;",
    ]) }}
>
    <div class="invoice-doc space-y-1.5 p-2.5 text-[10px] leading-tight sm:p-3 sm:text-[11px]">
        {{ $banner ?? '' }}

        {{-- Header: logo | title | date & number --}}
        <div class="grid grid-cols-[5.5rem_1fr_8.5rem] items-stretch gap-1.5 border" style="border-color: {{ $borderColor }};">
            <div class="flex items-center justify-center border-e p-1.5" style="border-color: {{ $borderColor }};">
                @if ($logoUrl)
                    <img
                        src="{{ $logoUrl }}"
                        alt="{{ $business->name }}"
                        class="max-h-14 max-w-full object-contain"
                    />
                @else
                    <div
                        class="flex size-12 items-center justify-center text-lg font-bold text-white"
                        style="background-color: {{ $primary }};"
                    >
                        {{ $initial }}
                    </div>
                @endif
            </div>

            <div class="flex flex-col items-center justify-center gap-0.5 px-2 py-1.5 text-center">
                <p class="text-sm font-bold text-black sm:text-base">{{ $documentHeading }}</p>
                <p class="text-[10px] text-zinc-700">{{ $documentLabel }}</p>
            </div>

            <div class="space-y-1 border-s p-1.5" style="border-color: {{ $borderColor }};">
                <div class="flex items-center justify-between gap-1 border px-1.5 py-1" style="border-color: {{ $borderColor }};">
                    <span class="font-semibold text-black">{{ __('general.number') }}</span>
                    <span class="font-bold text-black" dir="ltr">{{ $number }}</span>
                </div>
                @foreach ($dates as $date)
                    <div class="flex items-center justify-between gap-1 border px-1.5 py-1" style="border-color: {{ $borderColor }};">
                        <span class="font-semibold text-black">{{ $date['label'] }}</span>
                        <span class="font-medium text-black">{{ $date['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Seller --}}
        <div class="border" style="border-color: {{ $borderColor }};">
            <div class="border-b px-2 py-1 text-center text-[11px] font-bold text-black" style="background-color: {{ $headerTint }}; border-color: {{ $borderColor }};">
                {{ __('general.document_seller_details') }}
            </div>
            <div class="grid grid-cols-1 divide-y sm:grid-cols-2 sm:divide-x sm:divide-y-0" style="border-color: {{ $borderColor }};">
                <div class="space-y-0.5 p-1.5 text-black" style="border-color: {{ $borderColor }};">
                    <p><span class="font-semibold">{{ __('general.legal_or_natural_name') }}:</span> {{ $sellerName }}</p>
                    @if (filled($business->name) && $business->name !== $sellerName)
                        <p><span class="font-semibold">{{ __('general.name') }}:</span> {{ $business->name }}</p>
                    @endif
                    @if (filled($business->phone))
                        <p><span class="font-semibold">{{ __('general.phone') }}:</span> <span dir="ltr">{{ $business->phone }}</span></p>
                    @endif
                    @if (filled($business->address))
                        <p><span class="font-semibold">{{ __('general.address') }}:</span> {{ $business->address }}</p>
                    @endif
                </div>
                <div class="space-y-0.5 p-1.5 text-black">
                    @if (filled($business->national_id))
                        <p><span class="font-semibold">{{ __('general.national_id') }}:</span> <span dir="ltr">{{ $business->national_id }}</span></p>
                    @endif
                    @if (filled($business->economic_code))
                        <p><span class="font-semibold">{{ __('general.economic_code') }}:</span> <span dir="ltr">{{ $business->economic_code }}</span></p>
                    @endif
                    @if (filled($business->postal_code))
                        <p><span class="font-semibold">{{ __('general.postal_code') }}:</span> <span dir="ltr">{{ $business->postal_code }}</span></p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Buyer --}}
        <div class="border" style="border-color: {{ $borderColor }};">
            <div class="border-b px-2 py-1 text-center text-[11px] font-bold text-black" style="background-color: {{ $headerTint }}; border-color: {{ $borderColor }};">
                {{ __('general.document_buyer_details') }}
            </div>
            <div class="grid grid-cols-1 divide-y sm:grid-cols-2 sm:divide-x sm:divide-y-0" style="border-color: {{ $borderColor }};">
                <div class="space-y-0.5 p-1.5 text-black" style="border-color: {{ $borderColor }};">
                    <p><span class="font-semibold">{{ __('general.legal_or_natural_name') }}:</span> {{ $buyerDisplayName }}</p>
                    @if (filled($buyerPhone))
                        <p><span class="font-semibold">{{ __('general.phone') }}:</span> <span dir="ltr">{{ $buyerPhone }}</span></p>
                    @endif
                    @if (filled($buyerAddress))
                        <p><span class="font-semibold">{{ __('general.address') }}:</span> {{ $buyerAddress }}</p>
                    @endif
                </div>
                <div class="space-y-0.5 p-1.5 text-black">
                    @if (filled($buyerNationalId))
                        <p><span class="font-semibold">{{ __('general.national_id') }}:</span> <span dir="ltr">{{ $buyerNationalId }}</span></p>
                    @endif
                    @if (filled($buyerEconomicCode))
                        <p><span class="font-semibold">{{ __('general.economic_code') }}:</span> <span dir="ltr">{{ $buyerEconomicCode }}</span></p>
                    @endif
                    @if (filled($buyerPostalCode))
                        <p><span class="font-semibold">{{ __('general.postal_code') }}:</span> <span dir="ltr">{{ $buyerPostalCode }}</span></p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Items table --}}
        <div class="border" style="border-color: {{ $borderColor }};">
            <div class="border-b px-2 py-1 text-center text-[11px] font-bold text-black" style="background-color: {{ $headerTint }}; border-color: {{ $borderColor }};">
                {{ __('general.document_items_details') }}
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-center text-[9px] text-black sm:text-[10px]">
                    <thead>
                        <tr style="background-color: {{ $headerTint }};">
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.row') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.sku') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.item_description') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.quantity') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.unit') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.unit_price_rial') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.line_total_rial') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.discount_amount') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.after_discount_rial') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.tax_duties_rial') }}</th>
                            <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.final_line_total_rial') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $item)
                            @php
                                $unitValue = $item->unit ?? null;
                                $unitEnum = is_string($unitValue) ? ProductUnit::tryFrom($unitValue) : null;
                                $unitLabel = $unitEnum?->label() ?? ($unitValue ?: '—');
                                $amounts = DocumentLineAmounts::forItem($item);
                                $rowStyle = $index % 2 === 1 ? "background-color: {$stripeTint};" : '';
                            @endphp
                            <tr style="{{ $rowStyle }}">
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};">{{ $index + 1 }}</td>
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $item->sku ?: '—' }}</td>
                                <td class="border px-0.5 py-1 text-start text-black" style="border-color: {{ $borderColor }};">
                                    <div class="prose prose-sm max-w-none text-[9px] text-black sm:text-[10px] [&_p]:my-0">
                                        {!! $item->titleHtml() !!}
                                    </div>
                                </td>
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($amounts['quantity']) }}</td>
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};">{{ $unitLabel }}</td>
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($amounts['unit_price']) }}</td>
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($amounts['line_total']) }}</td>
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($amounts['discount_amount']) }}</td>
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($amounts['after_discount']) }}</td>
                                <td class="border px-0.5 py-1 text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($amounts['tax_amount']) }}</td>
                                <td class="border px-0.5 py-1 font-semibold text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($amounts['final_total']) }}</td>
                            </tr>
                        @endforeach

                        <tr style="background-color: {{ $headerTint }};">
                            <td colspan="6" class="border px-1 py-1 text-start font-bold text-black" style="border-color: {{ $borderColor }};">
                                {{ __('general.column_totals') }}:
                                {{ PersianAmountInWords::convert((string) $totalAmount) }}
                            </td>
                            <td class="border px-0.5 py-1 font-bold text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($columnTotals['line_total']) }}</td>
                            <td class="border px-0.5 py-1 font-bold text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($columnTotals['discount_amount']) }}</td>
                            <td class="border px-0.5 py-1 font-bold text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($columnTotals['after_discount']) }}</td>
                            <td class="border px-0.5 py-1 font-bold text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($columnTotals['tax_amount']) }}</td>
                            <td class="border px-0.5 py-1 font-bold text-black" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount($columnTotals['final_total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Sale kind + note | accounts --}}
        <div class="grid grid-cols-1 border sm:grid-cols-2" style="border-color: {{ $borderColor }};">
            <div class="border-b p-1.5 sm:border-b-0 sm:border-e" style="border-color: {{ $borderColor }};">
                <p class="mb-1 font-bold text-black">{{ __('general.sale_terms_and_notes') }}</p>
                <p class="text-black">
                    <span class="font-semibold">{{ __('general.sale_kind') }}:</span>
                    {{ filled($saleKindName) ? $saleKindName : '—' }}
                </p>
                <p class="mt-1 text-black">
                    <span class="font-semibold">{{ __('general.descriptions') }}:</span>
                    {{ filled($note) ? $note : '—' }}
                </p>
            </div>
            <div class="p-1.5">
                <p class="mb-1 font-bold text-black">{{ __('general.invoice_payment_accounts') }}</p>
                @forelse ($accountsList as $account)
                    <div class="mb-1.5 last:mb-0 text-black">
                        <p class="font-semibold">{{ $account->name }}</p>
                        @if (filled($account->bank_name ?? null))
                            <p>{{ __('general.bank_name') }}: {{ $account->bank_name }}</p>
                        @endif
                        @if (filled($account->account_number ?? null))
                            <p dir="ltr">{{ __('general.account_number') }}: {{ $account->account_number }}</p>
                        @endif
                        @if (filled($account->iban ?? null))
                            <p dir="ltr">{{ __('general.iban') }}: {{ $account->iban }}</p>
                        @endif
                        @if (filled($account->card_number ?? null))
                            <p dir="ltr">{{ __('general.card_number') }}: {{ $account->card_number }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-black">—</p>
                @endforelse
            </div>
        </div>

        {{-- Signatures --}}
        <div class="grid grid-cols-2 gap-1.5">
            <div class="flex min-h-20 flex-col justify-between border p-2 text-black" style="border-color: {{ $borderColor }};">
                <p class="text-center font-bold">{{ __('general.seller_stamp_signature') }}</p>
                <p class="text-center text-[10px]">{{ __('general.issue_date') }}: {{ $issueDateValue }}</p>
            </div>
            <div class="flex min-h-20 flex-col justify-between border p-2 text-black" style="border-color: {{ $borderColor }};">
                <p class="text-center font-bold">{{ __('general.buyer_stamp_signature') }}</p>
                <p class="text-center text-[10px]">{{ __('general.delivery_receipt_date') }}: —</p>
            </div>
        </div>

        {{ $footer ?? '' }}
    </div>
</div>

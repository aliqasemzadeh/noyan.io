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
])

@php
    use App\Enums\Catalog\ProductUnit;

    $logoUrl = $business->logoUrl();
    $primary = $business->invoicePrimaryColor();
    $secondary = $business->invoiceSecondaryColor();
    $initial = mb_substr($business->name, 0, 1);
    $sellerName = method_exists($business, 'displayLegalName')
        ? $business->displayLegalName()
        : $business->name;
    $buyerDisplayName = filled($buyerLegalName) ? $buyerLegalName : ($partyName ?: '—');
@endphp

<div class="invoice-sheet overflow-hidden rounded-lg border border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="h-1.5 w-full" style="background: linear-gradient(90deg, {{ $primary }}, {{ $secondary }});"></div>

    <div class="space-y-6 px-5 py-6 sm:px-8">
        {{ $banner ?? '' }}

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                @if ($logoUrl)
                    <img
                        src="{{ $logoUrl }}"
                        alt="{{ $business->name }}"
                        class="size-14 rounded-lg object-contain ring-1 ring-zinc-200 dark:ring-zinc-700"
                    />
                @else
                    <div
                        class="flex size-14 items-center justify-center rounded-lg text-xl font-bold text-white"
                        style="background-color: {{ $primary }};"
                    >
                        {{ $initial }}
                    </div>
                @endif

                <div>
                    <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $sellerName }}</h1>
                    @if (filled($business->name) && $business->name !== $sellerName)
                        <p class="mt-0.5 text-sm text-zinc-500">{{ $business->name }}</p>
                    @endif
                </div>
            </div>

            <div class="sm:text-end">
                <p class="text-sm text-zinc-500">{{ $documentLabel }}</p>
                <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100" dir="ltr">{{ $number }}</p>
                @if (filled($saleKindName))
                    <p class="mt-1 text-sm font-medium" style="color: {{ $primary }};">{{ $saleKindName }}</p>
                @endif
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ __('general.document_seller') }}</p>
                <div class="mt-3 space-y-1.5 text-sm text-zinc-800 dark:text-zinc-200">
                    <p class="font-medium">{{ $sellerName }}</p>
                    @if (filled($business->national_id))
                        <p>{{ __('general.national_id') }}: <span dir="ltr">{{ $business->national_id }}</span></p>
                    @endif
                    @if (filled($business->economic_code))
                        <p>{{ __('general.economic_code') }}: <span dir="ltr">{{ $business->economic_code }}</span></p>
                    @endif
                    @if (filled($business->phone))
                        <p>{{ __('general.phone') }}: <span dir="ltr">{{ $business->phone }}</span></p>
                    @endif
                    @if (filled($business->address))
                        <p>{{ __('general.address') }}: {{ $business->address }}</p>
                    @endif
                    @if (filled($business->postal_code))
                        <p>{{ __('general.postal_code') }}: <span dir="ltr">{{ $business->postal_code }}</span></p>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ __('general.document_buyer') }}</p>
                <div class="mt-3 space-y-1.5 text-sm text-zinc-800 dark:text-zinc-200">
                    <p class="font-medium">{{ $buyerDisplayName }}</p>
                    @if (filled($buyerNationalId))
                        <p>{{ __('general.national_id') }}: <span dir="ltr">{{ $buyerNationalId }}</span></p>
                    @endif
                    @if (filled($buyerEconomicCode))
                        <p>{{ __('general.economic_code') }}: <span dir="ltr">{{ $buyerEconomicCode }}</span></p>
                    @endif
                    @if (filled($buyerPhone))
                        <p>{{ __('general.phone') }}: <span dir="ltr">{{ $buyerPhone }}</span></p>
                    @endif
                    @if (filled($buyerAddress))
                        <p>{{ __('general.address') }}: {{ $buyerAddress }}</p>
                    @endif
                    @if (filled($buyerPostalCode))
                        <p>{{ __('general.postal_code') }}: <span dir="ltr">{{ $buyerPostalCode }}</span></p>
                    @endif
                </div>
            </div>
        </div>

        @if (count($dates) > 0)
            <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                @foreach ($dates as $date)
                    <div>
                        <span class="text-zinc-500">{{ $date['label'] }}:</span>
                        <span class="ms-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $date['value'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full text-sm">
                <thead style="background-color: color-mix(in srgb, {{ $primary }} 12%, transparent);">
                    <tr class="text-start">
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">#</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ __('general.sku') }}</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ __('general.title') }}</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ __('general.unit') }}</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ __('general.quantity') }}</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ __('general.unit_price') }}</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ __('general.discount_amount') }}</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ __('general.tax_amount') }}</th>
                        <th class="border-b border-zinc-200 px-3 py-2.5 font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">{{ __('general.row_total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($items as $index => $item)
                        @php
                            $unitValue = $item->unit ?? null;
                            $unitEnum = is_string($unitValue) ? ProductUnit::tryFrom($unitValue) : null;
                            $unitLabel = $unitEnum?->label() ?? ($unitValue ?: '—');
                            $discount = (string) ($item->discount_amount ?? '0');
                            $tax = (string) ($item->tax_amount ?? '0');
                        @endphp
                        <tr>
                            <td class="px-3 py-2.5 text-zinc-500">{{ $index + 1 }}</td>
                            <td class="px-3 py-2.5 text-zinc-700 dark:text-zinc-300" dir="ltr">{{ $item->sku ?: '—' }}</td>
                            <td class="px-3 py-2.5 font-medium text-zinc-900 dark:text-zinc-100">
                                <div class="prose prose-sm max-w-none dark:prose-invert [&_p]:my-0">
                                    {!! $item->titleHtml() !!}
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-zinc-700 dark:text-zinc-300">{{ $unitLabel }}</td>
                            <td class="px-3 py-2.5" dir="ltr">{{ $formatAmount((string) $item->quantity) }}</td>
                            <td class="px-3 py-2.5" dir="ltr">{{ $formatAmount((string) $item->unit_price) }}</td>
                            <td class="px-3 py-2.5" dir="ltr">{{ $formatAmount($discount) }}</td>
                            <td class="px-3 py-2.5" dir="ltr">{{ $formatAmount($tax) }}</td>
                            <td class="px-3 py-2.5 font-medium" dir="ltr">{{ $formatAmount((string) $item->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ms-auto max-w-xs space-y-2 text-sm">
            <div class="flex justify-between gap-6">
                <span class="text-zinc-500">{{ __('general.items_total') }}</span>
                <span dir="ltr">{{ $formatAmount((string) $itemsTotal) }}</span>
            </div>
            @if ((float) $globalDiscount > 0)
                <div class="flex justify-between gap-6">
                    <span class="text-zinc-500">{{ __('general.global_discount') }}</span>
                    <span dir="ltr">{{ $formatAmount((string) $globalDiscount) }}</span>
                </div>
            @endif
            @if ((float) $globalTax > 0)
                <div class="flex justify-between gap-6">
                    <span class="text-zinc-500">{{ __('general.global_tax') }}</span>
                    <span dir="ltr">{{ $formatAmount((string) $globalTax) }}</span>
                </div>
            @endif
            <div
                class="flex justify-between gap-6 border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700"
                style="color: {{ $primary }};"
            >
                <span>{{ __('general.total_amount') }}</span>
                <span dir="ltr">{{ $formatAmount((string) $totalAmount) }}</span>
            </div>
        </div>

        @if (filled($note))
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm text-zinc-500">{{ __('general.note') }}</p>
                <p class="mt-1 text-zinc-800 dark:text-zinc-200">{{ $note }}</p>
            </div>
        @endif

        @if (count($accounts) > 0)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('general.invoice_payment_accounts') }}</p>
                <div class="mt-3 space-y-3">
                    @foreach ($accounts as $account)
                        <div class="text-sm text-zinc-800 dark:text-zinc-200">
                            <p class="font-medium">{{ $account->name }}</p>
                            <div class="mt-1 space-y-0.5 text-zinc-600 dark:text-zinc-300">
                                @if (filled($account->bank_name))
                                    <p>{{ __('general.bank_name') }}: {{ $account->bank_name }}</p>
                                @endif
                                @if (filled($account->iban))
                                    <p dir="ltr">{{ __('general.iban') }}: {{ $account->iban }}</p>
                                @endif
                                @if (filled($account->account_number))
                                    <p dir="ltr">{{ __('general.account_number') }}: {{ $account->account_number }}</p>
                                @endif
                                @if (filled($account->card_number))
                                    <p dir="ltr">{{ __('general.card_number') }}: {{ $account->card_number }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="rounded-lg border border-dashed border-zinc-300 p-4 dark:border-zinc-600">
            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('general.document_business_details') }}</p>
            <div class="mt-3 space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                @if (filled($business->phone))
                    <p>{{ __('general.phone') }}: <span dir="ltr">{{ $business->phone }}</span></p>
                @endif
                @if (filled($business->address))
                    <p>{{ __('general.address') }}: {{ $business->address }}</p>
                @endif
                @if (filled($business->postal_code))
                    <p>{{ __('general.postal_code') }}: <span dir="ltr">{{ $business->postal_code }}</span></p>
                @endif
                @if (! filled($business->phone) && ! filled($business->address) && ! filled($business->postal_code))
                    <p>—</p>
                @endif
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex h-28 flex-col justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm text-zinc-500">{{ __('general.seller_stamp_signature') }}</p>
                <div class="border-t border-dashed border-zinc-300 dark:border-zinc-600"></div>
            </div>
            <div class="flex h-28 flex-col justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm text-zinc-500">{{ __('general.buyer_stamp_signature') }}</p>
                <div class="border-t border-dashed border-zinc-300 dark:border-zinc-600"></div>
            </div>
        </div>

        {{ $footer ?? '' }}
    </div>
</div>

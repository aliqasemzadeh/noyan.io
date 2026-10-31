@props([
    'business',
    'journalEntry',
    'lines',
    'totalDebit',
    'totalCredit',
    'sourceLabel',
    'formatAmount',
    'formatDate',
    'orientation' => null,
    'printStorageKey' => 'journal-entry-print-settings',
])

@php
    use App\Enums\Business\InvoiceOrientation;
    use App\Support\PersianAmountInWords;

    $defaultOrientation = $orientation instanceof InvoiceOrientation
        ? $orientation
        : (InvoiceOrientation::tryFrom((string) ($orientation ?? '')) ?? InvoiceOrientation::Portrait);

    $logoUrl = $business->logoUrl();
    $primary = $business->invoicePrimaryColor();
    $initial = mb_substr($business->name, 0, 1);
    $sellerName = method_exists($business, 'displayLegalName')
        ? $business->displayLegalName()
        : $business->name;
    $headerTint = "color-mix(in srgb, {$primary} 18%, white)";
    $stripeTint = "color-mix(in srgb, {$primary} 8%, white)";
    $borderColor = "color-mix(in srgb, {$primary} 35%, #71717a)";
    $entryDate = $formatDate($journalEntry->entry_date);
    $isBalanced = (string) $totalDebit === (string) $totalCredit;
@endphp

<div
    x-data="{
        storageKey: @js($printStorageKey),
        orientation: @js($defaultOrientation->value),
        advancedOpen: false,
        init() {
            try {
                const saved = JSON.parse(sessionStorage.getItem(this.storageKey) || 'null');
                if (saved && (saved.orientation === 'portrait' || saved.orientation === 'landscape')) {
                    this.orientation = saved.orientation;
                }
            } catch (e) {}

            this.applyPageOrientation();
            this.$watch('orientation', () => {
                this.persist();
                this.applyPageOrientation();
            });
        },
        persist() {
            sessionStorage.setItem(this.storageKey, JSON.stringify({
                orientation: this.orientation,
            }));
        },
        applyPageOrientation() {
            let style = document.getElementById('print-page-orientation');
            if (! style) {
                style = document.createElement('style');
                style.id = 'print-page-orientation';
                document.head.appendChild(style);
            }
            style.textContent = '@media print { @page { size: A4 ' + this.orientation + '; margin: 8mm; } }';
        },
    }"
    class="mx-auto px-3 py-4 sm:px-4"
    :class="orientation === 'landscape' ? 'max-w-6xl' : 'max-w-5xl'"
>
    <div
        {{ $attributes->merge([
            'class' => 'journal-sheet overflow-hidden border-2 bg-white text-black dark:bg-white',
            'style' => "border-color: {$borderColor}; print-color-adjust: exact; -webkit-print-color-adjust: exact;",
        ]) }}
    >
        <div class="no-print mb-3 flex flex-wrap items-start justify-end gap-2">
            <div class="relative">
                <flux:button
                    type="button"
                    variant="filled"
                    color="zinc"
                    icon="sliders-horizontal"
                    x-on:click="advancedOpen = ! advancedOpen"
                >
                    {{ __('general.advanced_print') }}
                </flux:button>

                <div
                    x-show="advancedOpen"
                    x-cloak
                    x-on:click.outside="advancedOpen = false"
                    class="absolute end-0 z-20 mt-2 w-72 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <p class="mb-2 text-xs text-zinc-600 dark:text-zinc-300">{{ __('general.advanced_print_hint') }}</p>
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">{{ __('general.invoice_orientation') }}</p>
                        <label class="flex items-center gap-2 text-sm text-zinc-800 dark:text-zinc-100">
                            <input type="radio" class="border-zinc-300" name="journal-print-orientation" value="portrait" x-model="orientation">
                            <span>{{ __('general.invoice_orientation_portrait') }}</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-zinc-800 dark:text-zinc-100">
                            <input type="radio" class="border-zinc-300" name="journal-print-orientation" value="landscape" x-model="orientation">
                            <span>{{ __('general.invoice_orientation_landscape') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            {{ $toolbar ?? '' }}
        </div>

        <div class="journal-doc space-y-1 p-2 text-[10px] leading-tight sm:p-2.5 sm:text-[11px]">
            <div class="grid grid-cols-[4rem_1fr_auto] items-stretch gap-1 border" style="border-color: {{ $borderColor }};">
                <div class="flex items-center justify-center border-e p-1" style="border-color: {{ $borderColor }};">
                    @if ($logoUrl)
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ $business->name }}"
                            class="max-h-8 max-w-full object-contain"
                        />
                    @else
                        <div
                            class="flex size-8 items-center justify-center text-sm font-bold text-white"
                            style="background-color: {{ $primary }};"
                        >
                            {{ $initial }}
                        </div>
                    @endif
                </div>

                <div class="flex flex-col items-center justify-center px-2 py-1 text-center">
                    <p class="text-sm font-bold text-black sm:text-base">{{ __('general.formal_journal_entry_title') }}</p>
                    <p class="mt-0.5 text-[10px] text-zinc-700">{{ $journalEntry->status->label() }}</p>
                </div>

                <div class="flex flex-col justify-center gap-1 border-s px-1.5 py-1" style="border-color: {{ $borderColor }};">
                    <div class="flex items-center gap-1 border px-1.5 py-0.5" style="border-color: {{ $borderColor }};">
                        <span class="font-semibold text-black">{{ __('general.number') }}</span>
                        <span class="font-bold text-black" dir="ltr">{{ $journalEntry->voucher_number }}</span>
                    </div>
                    <div class="flex items-center gap-1 border px-1.5 py-0.5" style="border-color: {{ $borderColor }};">
                        <span class="font-medium text-black">{{ $entryDate }}</span>
                    </div>
                </div>
            </div>

            <div class="border" style="border-color: {{ $borderColor }};">
                <div class="border-b px-2 py-0.5 text-center text-[11px] font-bold text-black" style="background-color: {{ $headerTint }}; border-color: {{ $borderColor }};">
                    {{ __('general.journal_business_details') }}
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
                        <p><span class="font-semibold">{{ __('general.fiscal_year') }}:</span> {{ $journalEntry->fiscalYear?->name ?? '—' }}</p>
                        <p><span class="font-semibold">{{ __('general.journal_source') }}:</span> {{ $sourceLabel }}</p>
                    </div>
                </div>
            </div>

            <div class="border px-2 py-1.5 text-black" style="border-color: {{ $borderColor }};">
                <p><span class="font-semibold">{{ __('general.description') }}:</span> {{ $journalEntry->description }}</p>
            </div>

            <div class="border" style="border-color: {{ $borderColor }};">
                <div class="border-b px-2 py-0.5 text-center text-[11px] font-bold text-black" style="background-color: {{ $headerTint }}; border-color: {{ $borderColor }};">
                    {{ __('general.journal_lines_table') }}
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-[9px] text-black sm:text-[10px]">
                        <thead>
                            <tr style="background-color: {{ $headerTint }};">
                                <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.row') }}</th>
                                <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.gl_account') }}</th>
                                <th class="border px-0.5 py-1 font-bold text-start" style="border-color: {{ $borderColor }};">{{ __('general.journal_line_details') }}</th>
                                <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.debit') }}</th>
                                <th class="border px-0.5 py-1 font-bold" style="border-color: {{ $borderColor }};">{{ __('general.credit') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $index => $line)
                                @php
                                    $rowStyle = $index % 2 === 1 ? "background-color: {$stripeTint};" : '';
                                    $detailParts = collect([
                                        $line->account?->name,
                                        $line->party?->name,
                                        $line->category?->name,
                                        $line->costCenter?->name,
                                        $line->project?->name,
                                        $line->description,
                                    ])->filter()->values();
                                @endphp
                                <tr style="{{ $rowStyle }}">
                                    <td class="border px-0.5 py-1 text-center" style="border-color: {{ $borderColor }};">{{ $index + 1 }}</td>
                                    <td class="border px-0.5 py-1 text-start" style="border-color: {{ $borderColor }};">
                                        <div class="font-semibold">{{ $line->glAccount?->name ?? '—' }}</div>
                                        <div class="text-[9px]" dir="ltr">{{ $line->glAccount?->full_code ?? $line->glAccount?->code }}</div>
                                    </td>
                                    <td class="border px-0.5 py-1 text-start" style="border-color: {{ $borderColor }};">
                                        @forelse ($detailParts as $part)
                                            <div>{{ $part }}</div>
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                    <td class="border px-0.5 py-1 text-end" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount((string) $line->debit) }}</td>
                                    <td class="border px-0.5 py-1 text-end" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount((string) $line->credit) }}</td>
                                </tr>
                            @endforeach
                            <tr style="background-color: {{ $headerTint }};">
                                <td class="border px-1 py-1 text-start font-bold" style="border-color: {{ $borderColor }};" colspan="3">
                                    {{ __('general.total') }}
                                    @if ($isBalanced)
                                        ({{ __('general.journal_balanced') }})
                                    @else
                                        ({{ __('general.journal_out_of_balance_badge') }})
                                    @endif
                                    —
                                    {{ __('general.journal_amount_in_words') }}:
                                    {{ PersianAmountInWords::convert((string) $totalDebit) }}
                                </td>
                                <td class="border px-0.5 py-1 text-end font-bold" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount((string) $totalDebit) }}</td>
                                <td class="border px-0.5 py-1 text-end font-bold" style="border-color: {{ $borderColor }};" dir="ltr">{{ $formatAmount((string) $totalCredit) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-1.5">
                <div class="flex min-h-16 flex-col justify-between border p-1.5 text-black" style="border-color: {{ $borderColor }};">
                    <p class="text-center font-bold">{{ __('general.journal_prepared_by') }}</p>
                    <p class="text-center text-[10px]">{{ $journalEntry->creator?->fullName() ?: ($journalEntry->creator?->mobile ?? '—') }}</p>
                </div>
                <div class="flex min-h-16 flex-col justify-between border p-1.5 text-black" style="border-color: {{ $borderColor }};">
                    <p class="text-center font-bold">{{ __('general.journal_reviewed_by') }}</p>
                    <p class="text-center text-[10px]">{{ $entryDate }}</p>
                </div>
                <div class="flex min-h-16 flex-col justify-between border p-1.5 text-black" style="border-color: {{ $borderColor }};">
                    <p class="text-center font-bold">{{ __('general.journal_approved_by') }}</p>
                    <p class="text-center text-[10px]">{{ $entryDate }}</p>
                </div>
            </div>

            {{ $footer ?? '' }}
        </div>
    </div>
</div>

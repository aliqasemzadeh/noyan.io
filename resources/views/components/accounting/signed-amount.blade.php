@props([
    'amount' => '0',
    'direction' => 'none',
    'decimals' => null,
    'currency' => null,
    'suffix' => null,
    'creditStyle' => null,
    'showIcon' => null,
])

@php
    $presentation = \App\Support\Accounting\SignedAmount::present(
        amount: (string) $amount,
        direction: (string) $direction,
        creditStyle: $creditStyle,
        decimals: $decimals !== null ? (int) $decimals : null,
        currency: $currency,
    );

    $shouldShowIcon = $showIcon ?? ($direction !== 'none');
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 font-medium', $presentation['color_class']]) }} dir="ltr">
    @if ($shouldShowIcon && $presentation['icon'])
        <flux:icon
            :name="$presentation['icon']"
            variant="mini"
            class="{{ $presentation['icon_class'] }} size-4 shrink-0"
        />
    @endif
    <span>{{ $presentation['formatted'] }}</span>
    @if (filled($suffix))
        <span class="text-zinc-500 dark:text-zinc-400">{{ $suffix }}</span>
    @endif
</span>

@blaze(fold: true)

{{-- Credit: Lucide (https://lucide.dev) --}}

@props([
    'variant' => 'outline',
])

@php
if ($variant === 'solid') {
    throw new \Exception('The "solid" variant is not supported in Lucide.');
}

$classes = Flux::classes('shrink-0')
    ->add(match($variant) {
        'outline' => '[:where(&)]:size-6',
        'solid' => '[:where(&)]:size-6',
        'mini' => '[:where(&)]:size-5',
        'micro' => '[:where(&)]:size-4',
    });

$strokeWidth = match ($variant) {
    'outline' => 2,
    'mini' => 2.25,
    'micro' => 2.5,
};
@endphp

<svg
    {{ $attributes->class($classes) }}
    data-flux-icon
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $strokeWidth }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    data-slot="icon"
>
  <path d="M12 18v-6" />
  <path d="M12 5v1.344" />
  <path d="M17.707 6.293A3 3 0 1012 5a3 3 0 00-4.124-2.781" />
  <path d="M17.997 5.125a4 4 0 012.526 5.77" />
  <path d="M19.644 19.644A4 4 0 0112 18a4 4 0 11-7.967-.517" />
  <path d="m2 2 20 20" />
  <path d="M20 10.536a4 4 0 011.533 5.34" />
  <path d="M3.477 10.895a4 4 0 011.876-5.542" />
  <path d="M6 18a4 4 0 01-2-7.464" />
  <path d="M9 13a4.17 4.17 0 002.166-1.5 4 4 0 00.138-.196" />
</svg>

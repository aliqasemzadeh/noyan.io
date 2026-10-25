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
  <path d="m10 16 1.5 1.5" />
  <path d="m14 8-1.5-1.5" />
  <path d="M15 2c-1.798 1.998-2.518 3.995-2.807 5.993" />
  <path d="m16.5 10.5 1 1" />
  <path d="m17 6-2.891-2.891" />
  <path d="M2 15c6.667-6 13.333 0 20-6" />
  <path d="m20 9 .891.891" />
  <path d="M3.109 14.109 4 15" />
  <path d="m6.5 12.5 1 1" />
  <path d="m7 18 2.891 2.891" />
  <path d="M9 22c1.798-1.998 2.518-3.995 2.807-5.993" />
</svg>

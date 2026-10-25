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
  <path d="M 19 3 L 5 3" />
  <path d="M 21 13 L 21 5" />
  <path d="M 21 5 A2 2 0 0 0 19 3" />
  <path d="M 3 19 A2 2 0 0 0 5 21" />
  <path d="M 3 5 L 3 19" />
  <path d="M 5 3 A2 2 0 0 0 3 5" />
  <path d="m16 19 2 2 4-4" />
  <path d="M16 2v3" />
  <path d="M3 9h18" />
  <path d="M5 21 L12.5 21" />
  <path d="M8 2v3" />
</svg>

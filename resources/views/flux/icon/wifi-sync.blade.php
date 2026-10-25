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
  <path d="M11.965 10.105v4L13.5 12.5a5 5 0 0 1 8 1.5" />
  <path d="M11.965 14.105h4" />
  <path d="M17.965 18.105h4L20.43 19.71a5 5 0 0 1-8-1.5" />
  <path d="M2 8.82a15 15 0 0 1 20 0" />
  <path d="M21.965 22.105v-4" />
  <path d="M5 12.86a10 10 0 0 1 3-2.032" />
  <path d="M8.5 16.429h.01" />
</svg>

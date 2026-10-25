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
  <path d="M12 17h1.5" />
  <path d="M12 22h1.5" />
  <path d="M12 2h1.5" />
  <path d="M17.5 22H19a1 1 0 0 0 1-1" />
  <path d="M17.5 2H19a1 1 0 0 1 1 1v1.5" />
  <path d="M20 14v3h-2.5" />
  <path d="M20 8.5V10" />
  <path d="M4 10V8.5" />
  <path d="M4 19.5V14" />
  <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H8" />
  <path d="M8 22H6.5a1 1 0 0 1 0-5H8" />
</svg>

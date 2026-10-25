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
  <path d="M11 7a16 16 20 0 1 10.98 4.362" />
  <path d="M12 12a13 13 0 0 1-8.66 5" />
  <path d="M16.83 13.634a16 16 0 0 1-9.267 7.328" />
  <path d="M20.66 17A13 13 0 0 0 12 12a13 13 0 0 1 0-10" />
  <path d="M8.17 15.366a16 16 0 0 1-1.713-11.69" />
  <circle cx="12" cy="12" r="10" />
</svg>

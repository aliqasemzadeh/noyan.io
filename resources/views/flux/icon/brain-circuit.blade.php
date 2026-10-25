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
  <path d="M12 13h4" />
  <path d="M12 18h6a2 2 0 012 2v1" />
  <path d="M12 8h8" />
  <path d="M12 9a4.17 4.17 0 01-3 4" />
  <path d="M16 8V5a2 2 0 012-2" />
  <path d="M6 18a4 4 0 01-2-7.465" />
  <path d="M6.004 5.125a4 4 0 00-2.526 5.77" />
  <path d="M6.294 6.294A3 3 0 1112 5v13a4 4 0 11-7.967-.518" />
  <circle cx="16" cy="13" r="0.5" fill="currentColor" />
  <circle cx="18" cy="3" r="0.5" fill="currentColor" />
  <circle cx="20" cy="21" r="0.5" fill="currentColor" />
  <circle cx="20" cy="8" r="0.5" fill="currentColor" />
</svg>

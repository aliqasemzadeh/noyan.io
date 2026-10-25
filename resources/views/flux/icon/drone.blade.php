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
  <path d="M10 10 7 7" />
  <path d="m10 14-3 3" />
  <path d="m14 10 3-3" />
  <path d="m14 14 3 3" />
  <path d="M14.205 4.139a4 4 0 1 1 5.439 5.863" />
  <path d="M19.637 14a4 4 0 1 1-5.432 5.868" />
  <path d="M4.367 10a4 4 0 1 1 5.438-5.862" />
  <path d="M9.795 19.862a4 4 0 1 1-5.429-5.873" />
  <rect x="10" y="8" width="4" height="8" rx="1" />
</svg>

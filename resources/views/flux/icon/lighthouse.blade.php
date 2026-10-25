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
  <path d="M12 3V2" />
  <path d="M16.066 16.865 7 22l2-11V6a3 3 0 016 0v5l2 11" />
  <path d="m19.792 4.5.866-.5" />
  <path d="m19.797 13.5.866.5" />
  <path d="M21 9h1" />
  <path d="M3 9H2" />
  <path d="m4.203 13.5-.866.5" />
  <path d="M4.208 4.5 3.342 4" />
  <path d="M5.5 22h13" />
  <path d="m7.932 16.875 7.377-4.178" />
  <path d="M8 11h8" />
  <path d="M8 7h8" />
</svg>

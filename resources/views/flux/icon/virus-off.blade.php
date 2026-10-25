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
  <path d="M10.01 10h.01" />
  <path d="M12 14.991h.01" />
  <path d="M12 22v-3" />
  <path d="M12 2v3" />
  <path d="M13 22h-2" />
  <path d="M13 2h-2" />
  <path d="m16.5 19.794-1-1.733" />
  <path d="m16.5 4.205-1 1.732" />
  <path d="M18.891 13.235a7 7 0 00-8.126-8.126" />
  <path d="m19.794 7.5-1.732 1" />
  <path d="M2 12h3" />
  <path d="M2 13v-2" />
  <path d="m2 2 20 20" />
  <path d="M22 12h-3" />
  <path d="M22 13v-2" />
  <path d="m4.206 16.5 1.732-1" />
  <path d="m4.206 7.5 1.732 1" />
  <path d="M7.05 7.05a7 7 0 009.9 9.9" />
  <path d="m7.5 19.794 1-1.733" />
  <path d="M9 12h.01" />
</svg>

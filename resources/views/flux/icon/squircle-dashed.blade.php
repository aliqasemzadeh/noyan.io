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
  <path d="M13.77 3.043a34 34 0 0 0-3.54 0" />
  <path d="M13.771 20.956a33 33 0 0 1-3.541.001" />
  <path d="M20.18 17.74c-.51 1.15-1.29 1.93-2.439 2.44" />
  <path d="M20.18 6.259c-.51-1.148-1.291-1.929-2.44-2.438" />
  <path d="M20.957 10.23a33 33 0 0 1 0 3.54" />
  <path d="M3.043 10.23a34 34 0 0 0 .001 3.541" />
  <path d="M6.26 20.179c-1.15-.508-1.93-1.29-2.44-2.438" />
  <path d="M6.26 3.82c-1.149.51-1.93 1.291-2.44 2.44" />
</svg>

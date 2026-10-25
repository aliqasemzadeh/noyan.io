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
  <path d="M15 8a1 1 0 0 1-1-1V2a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8z" />
  <path d="M20 8v12a2 2 0 0 1-2 2h-4.182" />
  <path d="m3.305 19.53.923-.382" />
  <path d="M4 10.592V4a2 2 0 0 1 2-2h8" />
  <path d="m4.228 16.852-.924-.383" />
  <path d="m5.852 15.228-.383-.923" />
  <path d="m5.852 20.772-.383.924" />
  <path d="m8.148 15.228.383-.923" />
  <path d="m8.53 21.696-.382-.924" />
  <path d="m9.773 16.852.922-.383" />
  <path d="m9.773 19.148.922.383" />
  <circle cx="7" cy="18" r="3" />
</svg>

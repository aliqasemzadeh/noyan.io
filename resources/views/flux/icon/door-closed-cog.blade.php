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
  <path d="m14.305 19.53.923-.382" />
  <path d="m15.229 16.852-.924-.383" />
  <path d="m16.852 15.228-.383-.923" />
  <path d="m16.852 20.773-.383.924" />
  <path d="M19 10.35V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16" />
  <path d="m19.148 15.228.383-.923" />
  <path d="m19.53 21.697-.382-.924" />
  <path d="M2 21h8.58" />
  <path d="m20.773 16.852.922-.383" />
  <path d="m20.773 19.148.922.383" />
  <path d="M9 12h.01" />
  <circle cx="18" cy="18" r="3" />
</svg>

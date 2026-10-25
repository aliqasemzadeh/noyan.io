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
  <path d="M10 15H6a4 4 0 0 0-4 4v2" />
  <path d="m14.305 16.53.923-.382" />
  <path d="m15.228 13.852-.923-.383" />
  <path d="m16.852 12.228-.383-.923" />
  <path d="m16.852 17.772-.383.924" />
  <path d="m19.148 12.228.383-.923" />
  <path d="m19.53 18.696-.382-.924" />
  <path d="m20.772 13.852.924-.383" />
  <path d="m20.772 16.148.924.383" />
  <circle cx="18" cy="15" r="3" />
  <circle cx="9" cy="7" r="4" />
</svg>

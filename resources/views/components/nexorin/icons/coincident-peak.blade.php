@props([
    'size' => 32,
    'label' => null,
])

<svg
    {{ $attributes->merge([
        'class' => 'nx-coincident-peak-icon',
    ]) }}
    viewBox="0 0 32 32"
    width="{{ $size }}"
    height="{{ $size }}"
    fill="none"
    shape-rendering="geometricPrecision"
    @if ($label)
        role="img"
        aria-label="{{ $label }}"
    @else
        aria-hidden="true"
    @endif
>
    <path class="nx-coincident-peak-icon__base" d="M8.8 23.6h14.6" />
    <path class="nx-coincident-peak-icon__trend" d="M10 20.8l4.2-4.4 3.7 2.9 5.5-7" />
    <path class="nx-coincident-peak-icon__arrow" d="M20.9 12.3h4.2v4.2" />
    <circle class="nx-coincident-peak-icon__point" cx="14.2" cy="16.4" r="1.18" />
</svg>

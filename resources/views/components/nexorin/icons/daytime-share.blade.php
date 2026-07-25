@props([
    'size' => 32,
    'label' => null,
])

<svg
    {{ $attributes->merge([
        'class' => 'nx-daytime-share-icon',
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
    <path class="nx-daytime-share-icon__horizon" d="M8.4 19.3h15.2" />
    <path class="nx-daytime-share-icon__ground" d="M10.3 22.4h11.4" />
    <path class="nx-daytime-share-icon__sun" d="M11.1 19.3a4.9 4.9 0 0 1 9.8 0" />
    <path class="nx-daytime-share-icon__ray" d="M16 8.5v2.2" />
    <path class="nx-daytime-share-icon__ray" d="M11.45 10.1l1.45 1.5" />
    <path class="nx-daytime-share-icon__ray" d="M20.55 10.1 19.1 11.6" />
</svg>

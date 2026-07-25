@props([
    'size' => 32,
    'label' => null,
])

<svg
    {{ $attributes->merge([
        'class' => 'nx-connected-load-icon',
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
    <rect class="nx-connected-load-icon__chip" x="10" y="8" width="12" height="16" rx="4.2" />

    <path class="nx-connected-load-icon__lead" d="M10 12.2H7.6a2.6 2.6 0 0 1-2.6-2.6" />
    <path class="nx-connected-load-icon__lead" d="M22 12.2h2.4A2.6 2.6 0 0 0 27 9.6" />
    <path class="nx-connected-load-icon__lead" d="M10 19.8H7.6A2.6 2.6 0 0 0 5 22.4" />
    <path class="nx-connected-load-icon__lead" d="M22 19.8h2.4a2.6 2.6 0 0 1 2.6 2.6" />

    <circle class="nx-connected-load-icon__node" cx="5" cy="9.6" r="1.45" />
    <circle class="nx-connected-load-icon__node" cx="27" cy="9.6" r="1.45" />
    <circle class="nx-connected-load-icon__node" cx="5" cy="22.4" r="1.45" />
    <circle class="nx-connected-load-icon__node" cx="27" cy="22.4" r="1.45" />

    <rect class="nx-connected-load-icon__bar is-low" x="12.3" y="15.1" width="2.05" height="5.6" rx="1.025" />
    <rect class="nx-connected-load-icon__bar is-high" x="14.98" y="11.3" width="2.05" height="9.4" rx="1.025" />
    <rect class="nx-connected-load-icon__bar is-mid" x="17.66" y="13.25" width="2.05" height="7.45" rx="1.025" />
</svg>

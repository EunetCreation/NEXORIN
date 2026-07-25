@props([
    'size' => 32,
    'label' => null,
])

<svg
    {{ $attributes->merge([
        'class' => 'nx-daily-energy-icon',
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
    <path class="nx-daily-energy-icon__orbit" d="M8.2 12.6a9.2 9.2 0 0 1 15.1-2.4" />
    <path class="nx-daily-energy-icon__orbit-accent" d="M23.3 10.2a9.2 9.2 0 0 1-1.3 13.5 9.2 9.2 0 0 1-13.8-4.2" />
    <circle class="nx-daily-energy-icon__marker" cx="23.25" cy="10.15" r="1.2" />

    <rect class="nx-daily-energy-icon__bar is-low" x="10.7" y="16.5" width="2.4" height="5.2" rx="1.2" />
    <rect class="nx-daily-energy-icon__bar is-mid" x="14.8" y="12.8" width="2.4" height="8.9" rx="1.2" />
    <rect class="nx-daily-energy-icon__bar is-high" x="18.9" y="10.9" width="2.4" height="10.8" rx="1.2" />

    <path class="nx-daily-energy-icon__base" d="M9.8 23.7h12.4" />
</svg>

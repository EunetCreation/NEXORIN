@php
    $size = $size ?? 20;
@endphp

@switch($name)
    @case('project')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M3.5 10.5 12 4l8.5 6.5V20H3.5v-9.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M8 20v-6h8v6M9.5 8.7h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('load')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M13.5 2.8 5.6 13h5.3l-.4 8.2L18.4 11h-5.3l.4-8.2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
        @break
    @case('solar')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/>
            <path d="M12 2.5v3M12 18.5v3M21.5 12h-3M5.5 12h-3M18.7 5.3l-2.1 2.1M7.4 16.6l-2.1 2.1M18.7 18.7l-2.1-2.1M7.4 7.4 5.3 5.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('roof')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m3 11 9-7 9 7M5 10v10h14V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M8 14h8M9 17h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('recommendation')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M9 18h6M10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M7.2 14.4A6.4 6.4 0 1 1 16.8 14.4c-.8.7-1.3 1.3-1.5 2.1H8.7c-.2-.8-.7-1.4-1.5-2.1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
        @break
    @case('technical')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m14.4 4.1 1.5-1.5 2.5 2.5-1.5 1.5M13 5.5l5.5 5.5-8.8 8.8H4.2v-5.5L13 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="m11.5 7 5.5 5.5" stroke="currentColor" stroke-width="1.8"/>
        </svg>
        @break
    @case('bom')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 5.5h16v13H4v-13Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M8 9h8M8 12h8M8 15h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('financial')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 19V9M10 19V5M16 19v-7M22 19H2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('quotation')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 3h8l4 4v14H6V3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M14 3v5h4M9 12h6M9 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('warning')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M10.2 4.2 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.8 4.2a2 2 0 0 0-3.6 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M12 9v4M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('release')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 3 4.5 6v5.2c0 4.7 3.1 8.2 7.5 9.8 4.4-1.6 7.5-5.1 7.5-9.8V6L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="m8.7 12 2.1 2.1 4.5-4.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
    @case('report')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 3h10l4 4v14H5V3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M15 3v5h4M8.5 12h7M8.5 15.5h7M8.5 19h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('audit')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/>
            <path d="m16 16 4.5 4.5M11 7.5V11l2.5 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break
    @case('arrow')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 12h14M14 7l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
    @case('edit')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m14.5 5.5 4 4M4 20l1.2-5.2L15.7 4.3a1.8 1.8 0 0 1 2.6 0l1.4 1.4a1.8 1.8 0 0 1 0 2.6L9.2 18.8 4 20Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
        @break
    @case('back')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m10 6-6 6 6 6M4 12h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
    @default
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/>
            <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
@endswitch

<div class="section-kicker">
    <span>{{ $eyebrow ?? 'NEXORIN' }}</span>
    <h2>{{ $title }}</h2>
    @isset($subtitle)
        <p>{{ $subtitle }}</p>
    @endisset
</div>

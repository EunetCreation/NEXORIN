<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['project']['number'] }} Premium Engineering Report</title>
    @if (! empty($inlineCss))
        <style>{!! $inlineCss !!}</style>
    @else
        <link rel="stylesheet" href="{{ $assets['css'] }}">
    @endif
</head>
<body class="nx-report {{ $assetMode === 'web' ? 'nx-preview' : 'nx-print' }}">
    @include('pdf.premium.pages.cover')
    @include('pdf.premium.pages.executive-dashboard')
    @include('pdf.premium.pages.recommendation')
    @include('pdf.premium.pages.technical-summary')
    @include('pdf.premium.pages.financial-summary')
    @include('pdf.premium.pages.detailed-boq')
</body>
</html>

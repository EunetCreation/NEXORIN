<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'SLD Drawing Editor') | NEXORIN</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">

    <link href="{{ asset('css/nexorin-ui.css') }}" rel="stylesheet">
    <link href="{{ asset('css/nexorin-feedback-system.css') }}?v=55a" rel="stylesheet">
    @stack('styles')
</head>
<body class="nxsld-editor-document">
    @yield('content')
    @stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | NEXORIN</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <script>
        (() => {
            const savedTheme = localStorage.getItem('nexorin-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.dataset.theme = savedTheme || (prefersDark ? 'dark' : 'light');
        })();
    </script>

    <link href="{{ asset('css/nexorin-ui.css') }}" rel="stylesheet">
    <link href="{{ asset('css/nexorin-shell.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>
    <div class="app-shell">
        @include('partials.sidebar')

        <button
            id="sidebarOverlay"
            class="sidebar-overlay"
            type="button"
            aria-label="Close navigation"
        ></button>

        <div class="app-main">
            @include('partials.topbar')

            <main class="app-content">
                <div class="content-container page-enter">
                    @if (session('success'))
                        <div class="ui-alert ui-alert-success" role="status" data-alert>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M8 12l3 3 5-6"/>
                            </svg>

                            <div>{{ session('success') }}</div>

                            <button type="button" class="ui-alert-close" aria-label="Dismiss notification" data-alert-close>×</button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="ui-alert ui-alert-error" role="alert" data-alert>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 7v6M12 17h.01"/>
                            </svg>

                            <div>{{ session('error') }}</div>

                            <button type="button" class="ui-alert-close" aria-label="Dismiss notification" data-alert-close>×</button>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script src="{{ asset('js/nexorin-shell.js') }}" defer></script>
    @stack('scripts')
</body>
</html>

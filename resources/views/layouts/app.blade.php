<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'Dashboard') | NEXORIN
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        href="{{ asset('css/nexorin-ui.css') }}"
        rel="stylesheet"
    >

    <style>
        :root {
            --navy-950: #031522;
            --navy-900: #061d2e;
            --navy-850: #08283d;
            --navy-800: #0a3550;
            --navy-700: #104969;

            --teal-600: #0da99f;
            --teal-500: #16beb3;
            --teal-400: #34d6cb;
            --teal-100: #dff8f5;

            --amber-500: #f5a800;

            --slate-950: #071827;
            --slate-800: #1d3547;
            --slate-700: #3b5365;
            --slate-600: #60778a;
            --slate-500: #7f93a5;
            --slate-400: #a3b2bf;
            --slate-300: #cbd6df;
            --slate-200: #dde6ec;
            --slate-100: #edf3f6;
            --slate-50: #f7fafb;

            --white: #ffffff;

            --sidebar-width: 280px;
            --topbar-height: 88px;

            --radius-sm: 10px;
            --radius-md: 16px;
            --radius-lg: 22px;
            --radius-xl: 28px;

            --shadow-sm:
                0 8px 24px rgba(3, 21, 34, 0.06);

            --shadow-md:
                0 18px 45px rgba(3, 21, 34, 0.09);

            --shadow-lg:
                0 28px 70px rgba(3, 21, 34, 0.14);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--slate-950);
            font-family: "Inter", sans-serif;
            background:
                radial-gradient(
                    circle at 85% 5%,
                    rgba(22, 190, 179, 0.10),
                    transparent 24%
                ),
                linear-gradient(
                    135deg,
                    #f7fafb 0%,
                    #edf4f7 100%
                );
        }

        body.sidebar-open {
            overflow: hidden;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        a {
            color: inherit;
        }

        svg {
            display: block;
        }

        .app-shell {
            min-height: 100vh;
        }

        .app-main {
            min-height: 100vh;
            margin-left: var(--sidebar-width);
            transition: margin-left 220ms ease;
        }

        .app-content {
            min-height: calc(100vh - var(--topbar-height));
            padding: 28px 32px 40px;
        }

        .content-container {
            width: min(1480px, 100%);
            margin: 0 auto;
        }

        .sidebar-overlay {
            position: fixed;
            z-index: 80;
            inset: 0;
            visibility: hidden;
            opacity: 0;
            background: rgba(2, 15, 24, 0.58);
            backdrop-filter: blur(3px);
            transition:
                opacity 200ms ease,
                visibility 200ms ease;
        }

        body.sidebar-open .sidebar-overlay {
            visibility: visible;
            opacity: 1;
        }

        .page-enter {
            animation: pageEnter 420ms ease both;
        }

        @keyframes pageEnter {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1100px) {
            .app-main {
                margin-left: 0;
            }

            .app-content {
                padding: 24px;
            }
        }

        @media (max-width: 720px) {
            .app-content {
                padding: 20px 16px 30px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>

    @stack('styles')
</head>

<body>
    <div class="app-shell">
        @include('partials.sidebar')

        <div
            id="sidebarOverlay"
            class="sidebar-overlay"
            aria-hidden="true"
        ></div>

        <div class="app-main">
            @include('partials.topbar')

            <main class="app-content">
                <div class="content-container page-enter">
                    @if (session('success'))
                        <div class="ui-alert ui-alert-success" role="status" data-alert>
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.9"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M8 12l3 3 5-6"/>
                            </svg>

                            <div>{{ session('success') }}</div>

                            <button
                                type="button"
                                class="ui-alert-close"
                                aria-label="Dismiss notification"
                                data-alert-close
                            >
                                ×
                            </button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="ui-alert ui-alert-error" role="alert" data-alert>
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.9"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 7v6M12 17h.01"/>
                            </svg>

                            <div>{{ session('error') }}</div>

                            <button
                                type="button"
                                class="ui-alert-close"
                                aria-label="Dismiss notification"
                                data-alert-close
                            >
                                ×
                            </button>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script>
        (() => {
            const body = document.body;
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            const openSidebar = () => {
                body.classList.add('sidebar-open');
            };

            const closeSidebar = () => {
                body.classList.remove('sidebar-open');
            };

            sidebarToggle?.addEventListener('click', openSidebar);
            sidebarClose?.addEventListener('click', closeSidebar);
            sidebarOverlay?.addEventListener('click', closeSidebar);

            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeSidebar();
                }
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 1100) {
                    closeSidebar();
                }
            });

            document.querySelectorAll('[data-alert-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    button.closest('[data-alert]')?.remove();
                });
            });

            document.querySelectorAll('[data-confirm]').forEach((element) => {
                element.addEventListener('click', (event) => {
                    const message = element.dataset.confirm || 'Continue with this action?';

                    if (!window.confirm(message)) {
                        event.preventDefault();
                    }
                });
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
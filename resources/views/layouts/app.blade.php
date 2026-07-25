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
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,600;1,9..144,700&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <script>
        (() => {
            const savedTheme = localStorage.getItem('nexorin-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.dataset.theme = savedTheme || (prefersDark ? 'dark' : 'light');

            try {
                const transitionStartedAt = Number(sessionStorage.getItem('nexorin-auth-transition'));
                const transitionIsRecent = Number.isFinite(transitionStartedAt)
                    && Date.now() - transitionStartedAt < 15000;

                if (transitionIsRecent) {
                    document.documentElement.classList.add('workspace-entry-pending');
                }
            } catch (error) {
                // Storage access can be unavailable in hardened browsers.
            }
        })();
    </script>

    <link href="{{ asset('css/nexorin-ui.css') }}" rel="stylesheet">
    <link href="{{ asset('css/nexorin-shell.css') }}" rel="stylesheet">
    <link href="{{ asset('css/nexorin-engineering-system.css') }}?v=55a" rel="stylesheet">

    @stack('styles')
    <link href="{{ asset('css/nexorin-feedback-system.css') }}?v=55a" rel="stylesheet">
</head>
<body>
    <div id="workspaceEntryOverlay" class="nxeng-action-overlay workspace-entry-overlay is-auth" aria-hidden="true">
        <div class="nxeng-action-ambient" aria-hidden="true"><span></span><span></span><span></span></div>
        <section class="nxeng-action-deck" role="status" aria-live="polite" aria-label="Opening engineering workspace">
            <aside class="nxeng-action-instrument" aria-hidden="true">
                <div class="nxeng-action-brand">
                    <strong>NEXORIN</strong>
                    <span>Secure orchestration</span>
                </div>
                <div class="nxeng-action-orbit">
                    <span class="nxeng-action-core workspace-entry-core">
                        <img src="{{ asset('images/nexorin-mark.png') }}" alt="">
                    </span>
                    <i></i><i></i><i></i>
                </div>
                <div class="nxeng-action-readout">
                    <small data-workspace-entry-sequence>SEQUENCE 01 / 04</small>
                    <strong data-workspace-entry-state>AUTHENTICATING</strong>
                </div>
                <div class="nxeng-action-telemetry">
                    <span><small>Session</small><b>Encrypted access</b></span>
                    <span><small>Control</small><b>Role context secured</b></span>
                </div>
            </aside>

            <div class="nxeng-action-console">
                <header class="nxeng-action-head">
                    <span class="nxeng-action-signal"><i></i>NEXORIN secure access</span>
                    <span class="nxeng-action-eyebrow">Secure workspace handoff</span>
                    <h2>Opening Engineering Workspace</h2>
                    <p data-workspace-entry-status>Establishing the authenticated session and preparing the engineering command center.</p>
                </header>

                <div class="nxeng-action-steps" data-workspace-entry-steps>
                    <div class="is-active"><span>01</span><b>Validating the authenticated session</b><i aria-hidden="true"></i></div>
                    <div><span>02</span><b>Resolving company, role, and access context</b><i aria-hidden="true"></i></div>
                    <div><span>03</span><b>Loading project and engineering signals</b><i aria-hidden="true"></i></div>
                    <div><span>04</span><b>Opening the NEXORIN command center</b><i aria-hidden="true"></i></div>
                </div>

                <footer class="nxeng-action-footer">
                    <div class="nxeng-action-track" aria-hidden="true"><span data-workspace-entry-progress></span></div>
                    <span data-workspace-entry-caption>Validating the authenticated session</span>
                </footer>
            </div>
        </section>
    </div>

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
                    @unless (View::hasSection('suppress-global-feedback'))
                        @if (session('success'))
                            <div class="nx-feedback nx-feedback--success" role="status" data-alert>
                                <span class="nx-feedback-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M20 7 9 18l-5-5"/>
                                        <path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/>
                                    </svg>
                                </span>
                                <div class="nx-feedback-body">
                                    <span class="nx-feedback-kicker">Controlled update</span>
                                    <strong class="nx-feedback-title">Operation completed</strong>
                                    <p class="nx-feedback-copy">{{ session('success') }}</p>
                                </div>
                                <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>
                                </button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="nx-feedback nx-feedback--danger" role="alert" data-alert>
                                <span class="nx-feedback-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M12 8v5M12 17h.01"/>
                                        <path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
                                    </svg>
                                </span>
                                <div class="nx-feedback-body">
                                    <span class="nx-feedback-kicker">Workflow exception</span>
                                    <strong class="nx-feedback-title">Action requires attention</strong>
                                    <p class="nx-feedback-copy">{{ session('error') }}</p>
                                </div>
                                <button type="button" class="nx-feedback-close" aria-label="Dismiss notification" data-alert-close>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>
                                </button>
                            </div>
                        @endif
                    @endunless

                    @yield('content')
                </div>
            </main>
        </div>
    </div>


    <div id="nxConfirmOverlay" class="nx-confirm-overlay" hidden aria-hidden="true">
        <section
            id="nxConfirmCard"
            class="nx-confirm-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="nxConfirmTitle"
            aria-describedby="nxConfirmMessage"
        >
            <header class="nx-confirm-head">
                <span class="nx-confirm-icon" aria-hidden="true">
                    <svg data-nx-confirm-icon="warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 8v5M12 17h.01"/>
                        <path d="M10.3 3.6 2.8 17a2 2 0 0 0 1.8 3h14.8a2 2 0 0 0 1.8-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
                    </svg>
                    <svg data-nx-confirm-icon="decision" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" hidden>
                        <path d="M20 7 9 18l-5-5"/>
                        <path d="M12 3 5 6v5c0 4.5 2.7 7.8 7 10 4.3-2.2 7-5.5 7-10V6z"/>
                    </svg>
                </span>
                <div>
                    <span class="nx-confirm-kicker" data-nx-confirm-kicker>Controlled decision</span>
                    <h2 id="nxConfirmTitle" data-nx-confirm-title>Confirm this action</h2>
                    <p id="nxConfirmMessage" data-nx-confirm-message>Continue with this controlled workflow action?</p>
                </div>
            </header>
            <div class="nx-confirm-context" aria-label="Decision context">
                <span><small>Control</small><strong data-nx-confirm-control>Explicit approval required</strong></span>
                <span><small>Result</small><strong data-nx-confirm-result>Workflow continues after confirmation</strong></span>
            </div>
            <footer class="nx-confirm-actions">
                <button type="button" class="nx-confirm-cancel" data-nx-confirm-cancel>Keep current state</button>
                <button type="button" class="nx-confirm-proceed" data-nx-confirm-proceed>Continue</button>
            </footer>
        </section>
    </div>

    <script src="{{ asset('js/nexorin-engineering-action.js') }}?v=55a" defer></script>
    <script src="{{ asset('js/nexorin-shell.js') }}?v=55a" defer></script>
    @stack('scripts')
</body>
</html>

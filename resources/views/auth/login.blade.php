<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Secure access to the NEXORIN solar engineering and quotation platform.">
    <meta name="theme-color" content="#020b14">
    <title>Secure Access | NEXORIN</title>
    <style id="nx-auth-critical">
        html, body { margin: 0; min-height: 100%; background: #010811; color: #f3f8fb; }
        body { min-height: 100vh; }
    </style>
    <link rel="preload" href="{{ asset('images/nexorin-hero.webp') }}" as="image" type="image/webp">
    <link rel="preload" href="{{ asset('images/nexorin-wordmark-symbol-refined.png') }}" as="image">
    <link rel="preload" href="{{ asset('images/nexorin-mark.webp') }}" as="image" type="image/webp">
    <link rel="stylesheet" href="{{ asset('css/nexorin-auth-v6.css') }}?v=001.12">
    <link rel="stylesheet" href="{{ asset('css/nexorin-v6-token-contract.css') }}?v=001010.1">
</head>
@php
    $googleSsoEnabled = (bool) config('nexorin.authentication.google_enabled', false);
@endphp
<body class="page-auth-login-v6" @if ($errors->any()) data-auth-error="true" @endif>
<div class="auth-canvas" data-auth-canvas>
    <section class="brand-stage" aria-labelledby="brand-headline">
        <div class="brand-stage__image" data-hero-image aria-hidden="true"></div>
        <div class="brand-stage__veil" aria-hidden="true"></div>
        <div class="hero-motion" aria-hidden="true">
            <div class="sun-bloom"></div>
            <svg class="energy-stream" viewBox="0 0 566 1448" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="energyTrackGradient" x1="1" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#ffd76b"/>
                        <stop offset=".44" stop-color="#27e7d2"/>
                        <stop offset="1" stop-color="#05abc5" stop-opacity="0"/>
                    </linearGradient>
                    <filter id="energyGlow" x="-80%" y="-80%" width="260%" height="260%">
                        <feGaussianBlur stdDeviation="4.2" result="blur"/>
                        <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                    </filter>
                </defs>
                <path class="energy-stream__rail" d="M510 403 C478 515 438 594 364 647 C284 704 174 729 8 800"/>
                <path class="energy-stream__pulse" pathLength="1" d="M510 403 C478 515 438 594 364 647 C284 704 174 729 8 800"/>
                <circle class="energy-stream__node" cx="364" cy="647" r="4.3"/>
            </svg>
            <div class="panel-light-sweep"></div>
        </div>
        <div class="energy-orbit energy-orbit--one" aria-hidden="true"></div>
        <div class="energy-orbit energy-orbit--two" aria-hidden="true"></div>

        <header class="brand-lockup">
            <div class="brand-wordmark-shell">
                <img
                    class="brand-wordmark"
                    src="{{ asset('images/nexorin-wordmark-symbol-refined.png') }}"
                    alt="NEXORIN"
                    width="1203"
                    height="165"
                >
                <span class="brand-x-sheen" aria-hidden="true"></span>
            </div>
            <div class="brand-kicker" aria-hidden="true">
                <span></span>
                <p>Solar Engineering and Quotation Platform</p>
                <span></span>
            </div>
        </header>

        <div class="brand-story">
            <h1 id="brand-headline">
                Powering a<br>
                Sustainable<br>
                <strong>Tomorrow</strong>
            </h1>
            <div class="story-accent" aria-hidden="true"><span></span><i></i></div>
            <p>NEXORIN unifies solar intelligence<br class="desktop-only"> and engineering excellence to<br class="desktop-only"> build a smarter, cleaner future.</p>
        </div>

        <div class="impact-metrics" aria-label="NEXORIN platform metrics">
            <article class="metric-card">
                <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M6.5 25.5h19M8 22l2.5-12h11L24 22M12 10l1.4-4h5.2L20 10M10 15h12M9 19h14M16 22v5"/></svg>
                <p><strong>{{ config('nexorin.login_metrics.solar_output') }}</strong> <span>{{ config('nexorin.login_metrics.solar_output_unit') }}</span></p>
                <small>Solar Output<br>(Live)</small>
            </article>
            <article class="metric-card">
                <svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="12"/><path d="m18.5 7.5-6 9h5l-4 8 7-10h-5z"/></svg>
                <p><strong>{{ config('nexorin.login_metrics.projected_generation') }}</strong> <span>{{ config('nexorin.login_metrics.projected_generation_unit') }}</span></p>
                <small>Projected Generation<br>(This Year)</small>
            </article>
            <article class="metric-card">
                <svg viewBox="0 0 32 32" aria-hidden="true"><path d="m16 4 10 6v12l-10 6-10-6V10zM6 10l10 6 10-6M16 16v12M11 13l10-6M11 19l10 6"/></svg>
                <p><strong>{{ config('nexorin.login_metrics.active_projects') }}</strong></p>
                <small>Active Projects<br>Across Regions</small>
            </article>
            <article class="metric-card">
                <svg viewBox="0 0 32 32" aria-hidden="true"><rect x="7" y="7" width="18" height="18" rx="5"/><circle cx="16" cy="16" r="5"/><path d="M12 7V4h8v3M9 25v3m14-3v3"/></svg>
                <p><strong>{{ config('nexorin.login_metrics.total_capacity') }}</strong> <span>{{ config('nexorin.login_metrics.total_capacity_unit') }}</span></p>
                <small>Total Capacity<br>Managed</small>
            </article>
        </div>

        <aside class="security-summary" aria-label="Security summary">
            <div class="security-summary__icon" aria-hidden="true">
                <svg viewBox="0 0 32 36"><path d="M16 2.5c4.4 3.2 8.7 4.1 12 4.7v9.2c0 8.3-5.2 14.1-12 17.1-6.8-3-12-8.8-12-17.1V7.2c3.3-.6 7.6-1.5 12-4.7Z"/><path d="m11.8 17 2.7 2.8 5.9-6.2"/></svg>
            </div>
            <div>
                <strong>Enterprise-grade security &amp; reliability</strong>
                <p>Secure authentication, governed access,<br class="desktop-only"> and resilient engineering workspaces.</p>
            </div>
        </aside>

        <footer class="brand-footer">
            <p>© {{ now()->year }} NEXORIN. All rights reserved.</p>
            <nav aria-label="Legal">
                <a href="#privacy">Privacy Policy</a>
                <a href="#terms">Terms of Service</a>
            </nav>
        </footer>
    </section>

    <main class="access-stage">
        <section
                @class([
                    'access-card',
                    'access-card--password-only' => ! $googleSsoEnabled,
                ])
                aria-labelledby="login-title"
            >
            <div class="access-card__glow" aria-hidden="true"></div>
            <svg class="access-card__trace" viewBox="0 0 460 1048" preserveAspectRatio="none" aria-hidden="true">
                <rect x="1" y="1" width="458" height="1046" rx="15" pathLength="1"/>
            </svg>

            <header class="access-header">
                <div class="nexorin-auth-mark" data-auth-mark aria-hidden="true">
                    <span class="nexorin-auth-mark__halo"></span>
                    <img
                        src="{{ asset('images/nexorin-mark.webp') }}"
                        alt=""
                        width="384"
                        height="411"
                        decoding="async"
                    >
                </div>
                <h2 id="login-title">Welcome back</h2>
                <p>Sign in to your NEXORIN workspace</p>
            </header>

            @if ($errors->any())
                <div class="auth-alert" role="alert" aria-live="polite">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 3.5v.1M12 3l9 17H3z"/></svg>
                    <div>
                        <strong>Secure access could not be completed</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if (session('status'))
                <div class="auth-alert auth-alert--success" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form class="login-form" method="POST" action="{{ route('login') }}" data-login-form novalidate>
                @csrf

                <div class="field-group">
                    <label for="email">Email address</label>
                    <div class="field-shell @error('email') field-shell--invalid @enderror">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 5.5h17v13h-17zM4 6l8 7 8-7"/></svg>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            placeholder="you@company.com"
                            autocomplete="username"
                            inputmode="email"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                        >
                    </div>
                    <p class="field-message" data-field-message="email" aria-live="polite"></p>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="field-shell @error('password') field-shell--invalid @enderror">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10m-4 4v3"/></svg>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            placeholder="••••••••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
                            <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.2 6.2c.6-.1 1.2-.2 1.8-.2 6 0 9.5 6 9.5 6a17 17 0 0 1-2.7 3.4M6.2 7.5A17 17 0 0 0 2.5 12s3.5 6 9.5 6c1 0 1.9-.2 2.8-.5M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                    <p class="field-message" data-field-message="password" aria-live="polite"></p>
                </div>

                <div class="form-options">
                    <label class="remember-control">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember', true))>
                        <span aria-hidden="true"><svg viewBox="0 0 16 16"><path d="m3.5 8 3 3 6-6"/></svg></span>
                        Remember me
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button class="sign-in-button" type="submit" data-submit-button>
                    <span data-submit-label>Sign In</span>
                    <span class="button-spinner" aria-hidden="true"></span>
                    <span class="button-progress" aria-hidden="true"></span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13m-5-5 5 5-5 5"/></svg>
                </button>
            </form>

            @if ($googleSsoEnabled)
                <div class="provider-divider"><span></span><p>Or continue with</p><span></span></div>

                <a class="google-button" href="{{ route('auth.google.redirect') }}" data-google-button>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.4-.2-2.1H12v4h5.4a4.6 4.6 0 0 1-2 3v2.6h3.3c1.9-1.8 2.9-4.4 2.9-7.5Z"/>
                        <path fill="#34A853" d="M12 22c2.7 0 5-.9 6.7-2.3L15.4 17c-.9.6-2.1 1-3.4 1-2.6 0-4.8-1.8-5.6-4.1H3v2.7A10 10 0 0 0 12 22Z"/>
                        <path fill="#FBBC05" d="M6.4 13.9A6 6 0 0 1 6.1 12c0-.7.1-1.3.3-1.9V7.4H3A10 10 0 0 0 2 12c0 1.7.4 3.2 1 4.6l3.4-2.7Z"/>
                        <path fill="#EA4335" d="M12 6c1.5 0 2.9.5 4 1.6l3-3A10 10 0 0 0 3 7.4l3.4 2.7C7.2 7.8 9.4 6 12 6Z"/>
                    </svg>
                    <span>Continue with Google</span>
                </a>
            @endif

            <aside class="identity-note identity-note--trust">
                <div class="identity-note__icon" aria-hidden="true">
                    <svg viewBox="0 0 32 36"><path d="M16 2.5c4.4 3.2 8.7 4.1 12 4.7v9.2c0 8.3-5.2 14.1-12 17.1-6.8-3-12-8.8-12-17.1V7.2c3.3-.6 7.6-1.5 12-4.7Z"/><path d="m11.8 17 2.7 2.8 5.9-6.2"/></svg>
                </div>
                <p><strong>Protected workspace</strong><span>Role-based access · Secure sessions · Audit-ready activity</span></p>
            </aside>
        </section>
    </main>
</div>
<script src="{{ asset('js/nexorin-auth-v6.js') }}?v=001.12" defer></script>
</body>
</html>

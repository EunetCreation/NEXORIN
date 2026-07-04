@extends('layouts.app')

@section('title', 'Dashboard')

@section('page-heading', 'Dashboard Overview')

@push('styles')
<style>
    .dashboard-grid {
        display: grid;
        gap: 22px;
    }

    .dashboard-hero {
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 30px;
        min-height: 220px;
        padding: 34px 38px;
        border: 1px solid rgba(7, 40, 61, 0.08);
        border-radius: var(--radius-xl);
        color: var(--white);
        background:
            radial-gradient(
                circle at 85% 15%,
                rgba(52, 214, 203, 0.26),
                transparent 27%
            ),
            linear-gradient(
                125deg,
                var(--navy-950),
                var(--navy-800)
            );
        box-shadow: var(--shadow-md);
    }

    .dashboard-hero::after {
        position: absolute;
        right: -80px;
        bottom: -120px;
        width: 360px;
        height: 360px;
        content: "";
        border: 1px solid rgba(52, 214, 203, 0.17);
        border-radius: 50%;
        box-shadow:
            0 0 0 50px rgba(52, 214, 203, 0.035),
            0 0 0 100px rgba(52, 214, 203, 0.025);
    }

    .hero-copy {
        position: relative;
        z-index: 2;
    }

    .hero-eyebrow {
        width: fit-content;
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 17px;
        padding: 8px 11px;
        border: 1px solid rgba(52, 214, 203, 0.20);
        border-radius: 999px;
        color: var(--teal-400);
        background: rgba(52, 214, 203, 0.08);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.11em;
        text-transform: uppercase;
    }

    .hero-eyebrow span {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--teal-400);
        box-shadow: 0 0 12px rgba(52, 214, 203, 0.75);
    }

    .dashboard-hero h2 {
        max-width: 690px;
        margin: 0;
        font-family: "Space Grotesk", sans-serif;
        font-size: clamp(30px, 4vw, 48px);
        line-height: 1.06;
        letter-spacing: -0.045em;
    }

    .dashboard-hero h2 strong {
        color: var(--teal-400);
        font-weight: 700;
    }

    .dashboard-hero p {
        max-width: 670px;
        margin: 18px 0 0;
        color: rgba(255, 255, 255, 0.61);
        font-size: 13px;
        line-height: 1.75;
    }

    .hero-metric {
        position: relative;
        z-index: 2;
        min-width: 190px;
        padding: 22px;
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.065);
        backdrop-filter: blur(12px);
    }

    .hero-metric span {
        display: block;
        color: rgba(255, 255, 255, 0.45);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.10em;
        text-transform: uppercase;
    }

    .hero-metric strong {
        display: block;
        margin-top: 9px;
        font-family: "Space Grotesk", sans-serif;
        font-size: 32px;
    }

    .hero-metric small {
        display: block;
        margin-top: 6px;
        color: var(--teal-400);
        font-size: 10px;
        font-weight: 700;
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .metric-card {
        position: relative;
        overflow: hidden;
        min-height: 150px;
        padding: 22px;
        border: 1px solid rgba(7, 40, 61, 0.075);
        border-radius: var(--radius-lg);
        background: rgba(255, 255, 255, 0.90);
        box-shadow: var(--shadow-sm);
    }

    .metric-card::after {
        position: absolute;
        right: -30px;
        bottom: -38px;
        width: 100px;
        height: 100px;
        content: "";
        border-radius: 50%;
        background: rgba(22, 190, 179, 0.055);
    }

    .metric-header {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .metric-icon {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 13px;
        color: var(--teal-600);
        background: var(--teal-100);
    }

    .metric-icon svg {
        width: 20px;
        height: 20px;
    }

    .metric-status {
        padding: 5px 8px;
        border-radius: 999px;
        color: var(--slate-500);
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 0.06em;
        background: var(--slate-100);
        text-transform: uppercase;
    }

    .metric-value {
        position: relative;
        z-index: 2;
        display: block;
        margin-top: 20px;
        font-family: "Space Grotesk", sans-serif;
        font-size: 30px;
        line-height: 1;
    }

    .metric-label {
        position: relative;
        z-index: 2;
        display: block;
        margin-top: 9px;
        color: var(--slate-600);
        font-size: 11px;
        font-weight: 600;
    }

    .dashboard-columns {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.55fr);
        gap: 22px;
    }

    .panel-card {
        padding: 25px;
        border: 1px solid rgba(7, 40, 61, 0.075);
        border-radius: var(--radius-lg);
        background: rgba(255, 255, 255, 0.90);
        box-shadow: var(--shadow-sm);
    }

    .panel-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }

    .panel-title h3 {
        margin: 0;
        font-family: "Space Grotesk", sans-serif;
        font-size: 18px;
        letter-spacing: -0.025em;
    }

    .panel-title p {
        margin: 7px 0 0;
        color: var(--slate-500);
        font-size: 11px;
        line-height: 1.6;
    }

    .panel-badge {
        padding: 7px 10px;
        border-radius: 999px;
        color: var(--teal-600);
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 0.08em;
        background: var(--teal-100);
        text-transform: uppercase;
    }

    .empty-state {
        min-height: 230px;
        display: grid;
        place-items: center;
        padding: 25px;
        border: 1px dashed var(--slate-300);
        border-radius: 18px;
        text-align: center;
        background:
            linear-gradient(
                135deg,
                rgba(247, 250, 251, 0.9),
                rgba(237, 243, 246, 0.6)
            );
    }

    .empty-state-icon {
        width: 58px;
        height: 58px;
        display: grid;
        place-items: center;
        margin: 0 auto 15px;
        border-radius: 18px;
        color: var(--teal-600);
        background: var(--teal-100);
    }

    .empty-state-icon svg {
        width: 27px;
        height: 27px;
    }

    .empty-state strong {
        display: block;
        font-size: 13px;
    }

    .empty-state p {
        max-width: 390px;
        margin: 8px auto 0;
        color: var(--slate-500);
        font-size: 10px;
        line-height: 1.65;
    }

    .readiness-list {
        display: grid;
        gap: 12px;
    }

    .readiness-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px;
        border: 1px solid var(--slate-100);
        border-radius: 15px;
        background: var(--slate-50);
    }

    .readiness-icon {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        color: var(--teal-600);
        background: var(--teal-100);
    }

    .readiness-icon svg {
        width: 17px;
        height: 17px;
    }

    .readiness-copy {
        min-width: 0;
        flex: 1;
    }

    .readiness-copy strong {
        display: block;
        font-size: 10px;
    }

    .readiness-copy span {
        display: block;
        margin-top: 4px;
        color: var(--slate-500);
        font-size: 9px;
    }

    .readiness-status {
        color: var(--teal-600);
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    @media (max-width: 1250px) {
        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .dashboard-hero {
            grid-template-columns: 1fr;
        }

        .hero-metric {
            width: 100%;
        }

        .dashboard-columns {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 620px) {
        .dashboard-hero {
            padding: 27px 23px;
            border-radius: 22px;
        }

        .metric-grid {
            grid-template-columns: 1fr;
        }

        .panel-card {
            padding: 20px;
        }
    }
</style>
@endpush

@section('content')
    <div class="dashboard-grid">
        <section class="dashboard-hero">
            <div class="hero-copy">
                <div class="hero-eyebrow">
                    <span></span>
                    Platform Ready
                </div>

                <h2>
                    Welcome back,
                    <strong>{{ auth()->user()->name }}.</strong>
                </h2>

                <p>
                    Manage photovoltaic projects, engineering calculations,
                    quotation workflows, and company operations from one
                    secure solar intelligence platform.
                </p>
            </div>

            <div class="hero-metric">
                <span>System readiness</span>
                <strong>100%</strong>
                <small>Core services operational</small>
            </div>
        </section>

        <section class="metric-grid">
            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M4 19V9l8-5 8 5v10"/>
                            <path d="M8 19v-6h8v6"/>
                            <path d="M3 19h18"/>
                        </svg>
                    </div>

                    <span class="metric-status">
                        Ready
                    </span>
                </div>

                <strong class="metric-value">0</strong>
                <span class="metric-label">Active Projects</span>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 3v2M12 19v2M3 12h2M19 12h2"/>
                            <path d="M5.6 5.6L7 7M17 17l1.4 1.4M18.4 5.6L17 7M7 17l-1.4 1.4"/>
                        </svg>
                    </div>

                    <span class="metric-status">
                        Ready
                    </span>
                </div>

                <strong class="metric-value">0 kWp</strong>
                <span class="metric-label">Designed Capacity</span>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M5 3h11l3 3v15H5z"/>
                            <path d="M16 3v4h4M8 12h8M8 16h6"/>
                        </svg>
                    </div>

                    <span class="metric-status">
                        Ready
                    </span>
                </div>

                <strong class="metric-value">0</strong>
                <span class="metric-label">Quotations</span>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M4 17l5-5 4 3 7-8"/>
                            <path d="M16 7h4v4"/>
                        </svg>
                    </div>

                    <span class="metric-status">
                        Ready
                    </span>
                </div>

                <strong class="metric-value">0 MWh</strong>
                <span class="metric-label">Estimated Energy</span>
            </article>
        </section>

        <section class="dashboard-columns">
            <article class="panel-card">
                <div class="panel-header">
                    <div class="panel-title">
                        <h3>Project Activity</h3>

                        <p>
                            Recent solar engineering activity will appear here.
                        </p>
                    </div>

                    <span class="panel-badge">
                        Live Overview
                    </span>
                </div>

                <div class="empty-state">
                    <div>
                        <div class="empty-state-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="M4 19V9l8-5 8 5v10"/>
                                <path d="M8 19v-6h8v6"/>
                                <path d="M3 19h18"/>
                            </svg>
                        </div>

                        <strong>No project activity yet</strong>

                        <p>
                            Project data, capacity calculations, and quotation
                            progress will appear after the project module is activated.
                        </p>
                    </div>
                </div>
            </article>

            <article class="panel-card">
                <div class="panel-header">
                    <div class="panel-title">
                        <h3>System Readiness</h3>

                        <p>
                            Current status of the NEXORIN core foundation.
                        </p>
                    </div>
                </div>

                <div class="readiness-list">
                    <div class="readiness-item">
                        <div class="readiness-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="M5 11l4 4L19 5"/>
                                <path d="M12 22A10 10 0 1 0 12 2a10 10 0 0 0 0 20z"/>
                            </svg>
                        </div>

                        <div class="readiness-copy">
                            <strong>Authentication</strong>
                            <span>Login, session, and logout</span>
                        </div>

                        <span class="readiness-status">
                            Active
                        </span>
                    </div>

                    <div class="readiness-item">
                        <div class="readiness-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/>
                                <path d="M9 12l2 2 4-4"/>
                            </svg>
                        </div>

                        <div class="readiness-copy">
                            <strong>Authorization</strong>
                            <span>Roles and permissions</span>
                        </div>

                        <span class="readiness-status">
                            Active
                        </span>
                    </div>

                    <div class="readiness-item">
                        <div class="readiness-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="M4 21V5l8-3 8 3v16"/>
                                <path d="M8 8h2M14 8h2M8 12h2M14 12h2"/>
                            </svg>
                        </div>

                        <div class="readiness-copy">
                            <strong>Multi-Company</strong>
                            <span>Company membership access</span>
                        </div>

                        <span class="readiness-status">
                            Active
                        </span>
                    </div>

                    <div class="readiness-item">
                        <div class="readiness-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="M4 17l5-5 4 3 7-8"/>
                                <path d="M16 7h4v4"/>
                            </svg>
                        </div>

                        <div class="readiness-copy">
                            <strong>Solar Modules</strong>
                            <span>Project development stage</span>
                        </div>

                        <span class="readiness-status">
                            Pending
                        </span>
                    </div>
                </div>
            </article>
        </section>
    </div>
@endsection
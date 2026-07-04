<style>
.topbar {
    position: sticky;
    z-index: 60;
    top: 0;
    min-height: var(--topbar-height);
    display: flex;
    align-items: center;
    border-bottom: 1px solid rgba(7, 40, 61, 0.07);
    background: #f7fafb;
    box-shadow: 0 8px 24px rgba(3, 21, 34, 0.05);
}

    .topbar-container {
        width: min(1480px, 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 22px;
        margin: 0 auto;
        padding: 0 32px;
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .menu-toggle {
        width: 44px;
        height: 44px;
        display: none;
        place-items: center;
        border: 1px solid var(--slate-200);
        border-radius: 13px;
        color: var(--navy-800);
        cursor: pointer;
        background: rgba(255, 255, 255, 0.86);
        box-shadow: var(--shadow-sm);
    }

    .menu-toggle svg {
        width: 20px;
        height: 20px;
    }

    .page-heading small {
        display: block;
        margin-bottom: 4px;
        color: var(--teal-600);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .page-heading h1 {
        margin: 0;
        font-family: "Space Grotesk", sans-serif;
        font-size: 22px;
        line-height: 1.2;
        letter-spacing: -0.025em;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .system-status {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 10px 13px;
        border: 1px solid rgba(22, 190, 179, 0.16);
        border-radius: 13px;
        color: var(--slate-700);
        background: rgba(255, 255, 255, 0.72);
        font-size: 10px;
        font-weight: 700;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--teal-500);
        box-shadow:
            0 0 0 5px rgba(22, 190, 179, 0.10),
            0 0 15px rgba(22, 190, 179, 0.45);
    }

    .date-card {
        padding: 10px 13px;
        border: 1px solid var(--slate-200);
        border-radius: 13px;
        color: var(--slate-600);
        background: rgba(255, 255, 255, 0.72);
        font-size: 10px;
        font-weight: 600;
    }

    @media (max-width: 1100px) {
        .menu-toggle {
            display: grid;
        }
    }

    @media (max-width: 720px) {
        .topbar {
            min-height: 76px;
        }

        .topbar-container {
            padding: 0 16px;
        }

        .page-heading h1 {
            font-size: 18px;
        }

        .system-status span:last-child,
        .date-card {
            display: none;
        }

        .system-status {
            width: 42px;
            height: 42px;
            justify-content: center;
            padding: 0;
        }
    }
</style>

<header class="topbar">
    <div class="topbar-container">
        <div class="topbar-left">
            <button
                id="sidebarToggle"
                class="menu-toggle"
                type="button"
                aria-label="Open navigation"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                >
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>

            <div class="page-heading">
                <small>
                    @yield('page-eyebrow', 'Solar intelligence platform')
                </small>

                <h1>
                    @yield('page-heading', 'Dashboard Overview')
                </h1>
            </div>
        </div>

        <div class="topbar-right">
            <div class="system-status">
                <span class="status-indicator"></span>
                <span>System operational</span>
            </div>

            <div class="date-card">
                {{ now('Asia/Jakarta')->format('d M Y') }}
            </div>
        </div>
    </div>
</header>
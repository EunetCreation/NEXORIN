<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="NEXORIN Solar Engineering and Quotation Platform"
    >

    <meta name="theme-color" content="#031625">

    <title>Sign In | NEXORIN</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --font-display:
                "Space Grotesk",
                "Segoe UI",
                sans-serif;

            --font-body:
                "Inter",
                "Segoe UI",
                Arial,
                sans-serif;

            --navy-1000: #020a13;
            --navy-975: #03111f;
            --navy-950: #041625;
            --navy-900: #06243d;
            --navy-850: #08314f;
            --navy-800: #0b4268;
            --navy-700: #0f5b87;

            --cyan-600: #10bda9;
            --cyan-500: #20cdb9;
            --cyan-400: #43e2ce;
            --cyan-300: #77f0de;
            --cyan-100: #dffbf6;

            --gold-500: #f5b31a;
            --gold-400: #ffd04b;

            --white: #ffffff;
            --surface: #f8fbfd;
            --surface-soft: #edf4f7;

            --text: #0a1c31;
            --muted: #6d7e92;
            --muted-light: #92a2b2;
            --border: #dbe5ed;

            --danger: #b42318;
            --danger-bg: #fff1f0;
            --danger-border: #ffd1cc;

            --success: #067647;
            --success-bg: #ecfdf3;
            --success-border: #a6f4c5;

            --shell-radius: 32px;
            --input-radius: 16px;

            --shell-shadow:
                0 48px 115px rgba(2, 18, 33, 0.21),
                0 14px 38px rgba(2, 18, 33, 0.10);

            --button-shadow:
                0 18px 36px rgba(6, 54, 85, 0.25);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            min-height: 100%;
            background: var(--navy-950);
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            padding: 24px;
            color: var(--text);
            font-family: var(--font-body);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            background:
                radial-gradient(
                    circle at 6% 8%,
                    rgba(32, 205, 185, 0.14),
                    transparent 27%
                ),
                radial-gradient(
                    circle at 94% 92%,
                    rgba(28, 111, 172, 0.14),
                    transparent 31%
                ),
                linear-gradient(
                    145deg,
                    #e9f3f5 0%,
                    #f8fbfc 46%,
                    #edf4f7 100%
                );
        }

        button,
        input {
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        a {
            color: inherit;
        }

        .auth-page {
            width: 100%;
            max-width: 1440px;
        }

        .auth-shell {
            position: relative;
            width: 100%;
            min-height: 800px;
            display: grid;
            grid-template-columns:
                minmax(0, 1.15fr)
                minmax(420px, 0.85fr);
            overflow: hidden;
            border: 1px solid rgba(7, 43, 64, 0.08);
            border-radius: var(--shell-radius);
            background: var(--white);
            box-shadow: var(--shell-shadow);
            isolation: isolate;
        }

        .auth-shell::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 20;
            pointer-events: none;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: inherit;
        }

        /* =========================================================
           HERO PANEL
        ========================================================= */

        .hero-panel {
            position: relative;
            min-width: 0;
            overflow: hidden;
            color: var(--white);
            background:
                linear-gradient(
                    145deg,
                    var(--navy-1000),
                    var(--navy-900)
                );
            isolation: isolate;
        }

        .hero-image {
            position: absolute;
            inset: -18px;
            z-index: -8;
            background-image:
                url('{{ asset('images/nexorin-hero.png') }}');
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
            transform: scale(1.045);
            transition: transform 0.25s ease-out;
            will-change: transform;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            z-index: -7;
            background:
                linear-gradient(
                    180deg,
                    rgba(2, 14, 25, 0.62) 0%,
                    rgba(2, 14, 25, 0.10) 38%,
                    rgba(2, 14, 25, 0.02) 66%,
                    rgba(2, 14, 25, 0.24) 100%
                ),
                linear-gradient(
                    90deg,
                    rgba(2, 14, 25, 0.66) 0%,
                    rgba(2, 14, 25, 0.16) 55%,
                    transparent 100%
                );
        }

        .hero-vignette {
            position: absolute;
            inset: 0;
            z-index: -6;
            pointer-events: none;
            box-shadow:
                inset 0 0 150px rgba(0, 8, 18, 0.58),
                inset 0 -110px 135px rgba(0, 8, 18, 0.29);
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            z-index: -5;
            opacity: 0.085;
            pointer-events: none;
            background-image:
                linear-gradient(
                    rgba(255, 255, 255, 0.08) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(255, 255, 255, 0.08) 1px,
                    transparent 1px
                );
            background-size: 58px 58px;
            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, 0.66),
                    transparent 82%
                );
        }

        .hero-glow {
            position: absolute;
            right: -170px;
            bottom: -185px;
            z-index: -4;
            width: 530px;
            height: 530px;
            border-radius: 50%;
            pointer-events: none;
            background:
                radial-gradient(
                    circle,
                    rgba(32, 205, 185, 0.25),
                    rgba(32, 205, 185, 0.07) 48%,
                    transparent 72%
                );
            filter: blur(12px);
            animation: heroGlow 7s ease-in-out infinite;
        }

        .energy-sweep {
            position: absolute;
            top: -35%;
            left: -35%;
            z-index: -3;
            width: 24%;
            height: 180%;
            pointer-events: none;
            opacity: 0;
            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, 0.06),
                    rgba(67, 226, 206, 0.18),
                    rgba(255, 255, 255, 0.05),
                    transparent
                );
            filter: blur(2px);
            transform: rotate(18deg);
            animation: energySweep 8.5s ease-in-out infinite;
        }

        .particle-layer {
            position: absolute;
            inset: 0;
            z-index: -2;
            overflow: hidden;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            top: var(--top);
            left: var(--left);
            width: var(--size);
            height: var(--size);
            border-radius: 50%;
            opacity: 0;
            background: var(--particle-color);
            box-shadow:
                0 0 12px var(--particle-color),
                0 0 24px rgba(79, 236, 215, 0.18);
            animation:
                particleFloat var(--duration)
                linear infinite;
            animation-delay: var(--delay);
        }

        .hero-content {
            position: relative;
            z-index: 4;
            min-height: 800px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 50px 38px;
            transition: transform 0.25s ease-out;
            will-change: transform;
        }

        .hero-header {
            max-width: 600px;
        }

        .platform-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 26px;
            padding: 10px 16px;
            color: #eafffb;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 999px;
            background: rgba(3, 25, 39, 0.48);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.06),
                0 12px 30px rgba(0, 0, 0, 0.14);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            font-family: var(--font-display);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.25px;
            text-transform: uppercase;
        }

        .badge-indicator {
            position: relative;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--cyan-400);
            box-shadow:
                0 0 12px rgba(67, 226, 206, 0.92);
        }

        .badge-indicator::after {
            content: "";
            position: absolute;
            inset: -6px;
            border: 1px solid rgba(67, 226, 206, 0.42);
            border-radius: 50%;
            animation: statusPulse 2.5s ease-out infinite;
        }

        .hero-title {
            max-width: 580px;
            margin-bottom: 20px;
            font-family: var(--font-display);
            font-size: clamp(38px, 3.45vw, 55px);
            font-weight: 700;
            line-height: 1.035;
            letter-spacing: -2.25px;
            text-shadow:
                0 5px 30px rgba(0, 0, 0, 0.28);
        }

        .hero-title span {
            color: var(--cyan-400);
            text-shadow:
                0 0 25px rgba(67, 226, 206, 0.21),
                0 5px 30px rgba(0, 0, 0, 0.20);
        }

        .hero-description {
            max-width: 545px;
            color: rgba(225, 238, 246, 0.85);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 400;
            line-height: 1.85;
            letter-spacing: -0.08px;
            text-shadow:
                0 3px 18px rgba(0, 0, 0, 0.32);
        }

        .hero-footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
        }

        .hero-status {
            min-width: 225px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 16px;
            color: rgba(226, 244, 246, 0.94);
            border: 1px solid rgba(255, 255, 255, 0.13);
            border-radius: 16px;
            background: rgba(3, 25, 39, 0.52);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow:
                0 15px 34px rgba(0, 0, 0, 0.14);
        }

        .hero-status-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background:
                rgba(32, 205, 185, 0.11);
        }

        .hero-status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--cyan-400);
            box-shadow:
                0 0 0 7px rgba(67, 226, 206, 0.10),
                0 0 16px rgba(67, 226, 206, 0.84);
            animation:
                statusDot 2.2s ease-in-out infinite;
        }

        .hero-status-copy strong {
            display: block;
            margin-bottom: 3px;
            font-family: var(--font-display);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.85px;
            text-transform: uppercase;
        }

        .hero-status-copy span {
            display: block;
            color: rgba(198, 219, 229, 0.74);
            font-size: 9px;
            font-weight: 500;
            line-height: 1.5;
        }

        .hero-caption {
            max-width: 265px;
            padding: 11px 15px;
            color: rgba(218, 234, 242, 0.84);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 13px;
            background: rgba(2, 18, 31, 0.43);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            font-family: var(--font-display);
            font-size: 9px;
            font-weight: 600;
            line-height: 1.55;
            letter-spacing: 0.95px;
            text-align: right;
            text-transform: uppercase;
        }

        /* =========================================================
           FORM PANEL
        ========================================================= */

        .form-panel {
            position: relative;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 48px 54px;
            background:
                radial-gradient(
                    circle at 100% 0%,
                    rgba(32, 205, 185, 0.08),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 0% 100%,
                    rgba(20, 93, 150, 0.06),
                    transparent 26%
                ),
                linear-gradient(
                    180deg,
                    #ffffff 0%,
                    #fbfdfe 100%
                );
        }

        .form-panel::before {
            content: "";
            position: absolute;
            top: -155px;
            right: -160px;
            width: 360px;
            height: 360px;
            border: 1px solid rgba(32, 205, 185, 0.11);
            border-radius: 50%;
            box-shadow:
                0 0 0 42px rgba(32, 205, 185, 0.024),
                0 0 0 88px rgba(32, 205, 185, 0.016);
            pointer-events: none;
        }

        .form-panel::after {
            content: "";
            position: absolute;
            left: -100px;
            bottom: -120px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            pointer-events: none;
            background:
                radial-gradient(
                    circle,
                    rgba(22, 97, 157, 0.08),
                    transparent 68%
                );
        }

        .form-container {
            position: relative;
            z-index: 3;
            width: 100%;
            max-width: 455px;
            animation:
                formEntrance 0.8s
                cubic-bezier(.22, .8, .24, 1) both;
        }

        .brand-logo-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 24px;
            padding: 11px 14px;
            border: 1px solid rgba(10, 49, 81, 0.08);
            border-radius: 20px;
            background:
                linear-gradient(
                    180deg,
                    rgba(255, 255, 255, 0.97),
                    rgba(248, 251, 253, 0.97)
                );
            box-shadow:
                0 16px 36px rgba(8, 35, 58, 0.075),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .brand-logo-wrapper::after {
            content: "";
            position: absolute;
            top: -40%;
            left: -35%;
            width: 25%;
            height: 180%;
            opacity: 0;
            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, 0.95),
                    transparent
                );
            transform: rotate(18deg);
            animation:
                logoSweep 6.8s ease-in-out infinite;
        }

        .brand-logo {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 335px;
            height: auto;
            display: block;
            object-fit: contain;
            mix-blend-mode: multiply;
        }

        .form-header {
            margin-bottom: 25px;
        }

        .form-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 11px;
            color: var(--cyan-600);
            font-family: var(--font-display);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.35px;
            text-transform: uppercase;
        }

        .form-eyebrow::before {
            content: "";
            width: 24px;
            height: 2px;
            border-radius: 99px;
            background:
                linear-gradient(
                    90deg,
                    var(--cyan-500),
                    transparent
                );
        }

        .form-header h2 {
            margin-bottom: 9px;
            color: var(--navy-950);
            font-family: var(--font-display);
            font-size: 31px;
            font-weight: 700;
            line-height: 1.16;
            letter-spacing: -1.15px;
        }

        .form-header p {
            max-width: 420px;
            color: var(--muted);
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 400;
            line-height: 1.75;
            letter-spacing: -0.05px;
        }

        .alert {
            margin-bottom: 19px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.55;
        }

        .alert-danger {
            color: var(--danger);
            border: 1px solid var(--danger-border);
            background: var(--danger-bg);
        }

        .alert-success {
            color: var(--success);
            border: 1px solid var(--success-border);
            background: var(--success-bg);
        }

        .alert div + div {
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-label {
            display: block;
            margin-bottom: 9px;
            color: var(--navy-950);
            font-family: var(--font-body);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.02px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 17px;
            z-index: 2;
            width: 20px;
            height: 20px;
            color: #8495a9;
            pointer-events: none;
            transform: translateY(-50%);
            transition:
                color 0.2s ease,
                transform 0.2s ease;
        }

        .form-control {
            width: 100%;
            height: 57px;
            padding: 0 50px;
            color: var(--navy-950);
            border: 1px solid var(--border);
            border-radius: var(--input-radius);
            outline: none;
            background:
                linear-gradient(
                    180deg,
                    #fbfdfe,
                    #f8fbfd
                );
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background-color 0.2s ease,
                transform 0.2s ease;
        }

        .form-control::placeholder {
            color: #9baaba;
            font-weight: 400;
        }

        .form-control:hover {
            border-color: #b8c8d7;
        }

        .form-control:focus {
            border-color: var(--cyan-500);
            background: var(--white);
            box-shadow:
                0 0 0 4px rgba(32, 205, 185, 0.11),
                0 10px 26px rgba(9, 70, 88, 0.07);
            transform: translateY(-1px);
        }

        .input-wrapper:focus-within .input-icon {
            color: var(--cyan-500);
            transform:
                translateY(-50%)
                scale(1.05);
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            z-index: 3;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #8191a3;
            border: none;
            border-radius: 11px;
            background: transparent;
            cursor: pointer;
            transform: translateY(-50%);
            transition:
                color 0.2s ease,
                background-color 0.2s ease,
                transform 0.2s ease;
        }

        .toggle-password:hover {
            color: var(--navy-900);
            background: var(--surface-soft);
            transform:
                translateY(-50%)
                scale(1.04);
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 3px 0 23px;
        }

        .remember-me {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 400;
            cursor: pointer;
            user-select: none;
        }

        .remember-checkbox {
            position: relative;
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
        }

        .remember-checkbox input {
            position: absolute;
            inset: 0;
            z-index: 2;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .checkbox-ui {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #bcc9d5;
            border-radius: 5px;
            background: var(--white);
            transition:
                border-color 0.2s ease,
                background-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .checkbox-ui svg {
            width: 12px;
            height: 12px;
            color: var(--white);
            opacity: 0;
            transform: scale(0.5);
            transition:
                opacity 0.2s ease,
                transform 0.2s ease;
        }

        .remember-checkbox input:checked + .checkbox-ui {
            border-color: var(--cyan-500);
            background: var(--cyan-500);
            box-shadow:
                0 0 0 4px
                rgba(32, 205, 185, 0.10);
        }

        .remember-checkbox input:checked + .checkbox-ui svg {
            opacity: 1;
            transform: scale(1);
        }

        .forgot-link {
            position: relative;
            color: var(--navy-800);
            font-family: var(--font-body);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-link::after {
            content: "";
            position: absolute;
            right: 0;
            bottom: -3px;
            left: 0;
            height: 1px;
            background: var(--cyan-500);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.25s ease;
        }

        .forgot-link:hover {
            color: var(--cyan-600);
        }

        .forgot-link:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .login-button {
            position: relative;
            width: 100%;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            overflow: hidden;
            color: var(--white);
            border: none;
            border-radius: var(--input-radius);
            background:
                linear-gradient(
                    110deg,
                    #06223c 0%,
                    #0f5f8f 50%,
                    #20cdb9 100%
                );
            box-shadow:
                var(--button-shadow),
                inset 0 1px 0 rgba(255, 255, 255, 0.18);
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.05px;
            cursor: pointer;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                filter 0.2s ease;
        }

        .login-button::before {
            content: "";
            position: absolute;
            top: -42%;
            left: -120px;
            width: 90px;
            height: 190%;
            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, 0.30),
                    transparent
                );
            transform: rotate(18deg);
            transition: left 0.72s ease;
        }

        .login-button::after {
            content: "";
            position: absolute;
            inset: 1px;
            pointer-events: none;
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius:
                calc(var(--input-radius) - 1px);
        }

        .login-button:hover {
            filter: saturate(1.08);
            transform: translateY(-2px);
            box-shadow:
                0 22px 42px rgba(6, 54, 85, 0.30),
                inset 0 1px 0 rgba(255, 255, 255, 0.20);
        }

        .login-button:hover::before {
            left: calc(100% + 40px);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button:disabled {
            cursor: wait;
            opacity: 0.88;
            transform: none;
        }

        .button-text {
            position: relative;
            z-index: 2;
        }

        .button-icon {
            position: relative;
            z-index: 2;
            width: 18px;
            height: 18px;
            transition: transform 0.2s ease;
        }

        .login-button:hover .button-icon {
            transform: translateX(3px);
        }

        .button-spinner {
            position: relative;
            z-index: 2;
            width: 18px;
            height: 18px;
            display: none;
            border: 2px solid rgba(255, 255, 255, 0.34);
            border-top-color: var(--white);
            border-radius: 50%;
            animation: spinner 0.7s linear infinite;
        }

        .login-button.is-loading .button-icon {
            display: none;
        }

        .login-button.is-loading .button-spinner {
            display: block;
        }

        .security-card {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            padding: 13px 14px;
            border: 1px solid rgba(11, 61, 86, 0.08);
            border-radius: 14px;
            background:
                linear-gradient(
                    180deg,
                    rgba(247, 251, 252, 0.97),
                    rgba(242, 248, 250, 0.97)
                );
        }

        .security-icon {
            width: 35px;
            height: 35px;
            flex: 0 0 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--cyan-500);
            border-radius: 10px;
            background:
                rgba(32, 205, 185, 0.10);
        }

        .security-icon svg {
            width: 18px;
            height: 18px;
        }

        .security-copy strong {
            display: block;
            margin-bottom: 3px;
            color: var(--navy-900);
            font-family: var(--font-display);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: -0.05px;
        }

        .security-copy span {
            display: block;
            color: #8090a1;
            font-family: var(--font-body);
            font-size: 9px;
            font-weight: 400;
            line-height: 1.5;
        }

        .copyright {
            margin-top: 17px;
            color: #9ba8b5;
            font-family: var(--font-body);
            font-size: 9px;
            font-weight: 400;
            line-height: 1.6;
            text-align: center;
        }

        /* =========================================================
           ANIMATIONS
        ========================================================= */

        @keyframes heroGlow {
            0%,
            100% {
                opacity: 0.78;
                transform: scale(1);
            }

            50% {
                opacity: 1;
                transform: scale(1.08);
            }
        }

        @keyframes energySweep {
            0%,
            20% {
                left: -35%;
                opacity: 0;
            }

            30% {
                opacity: 0.75;
            }

            65% {
                opacity: 0.50;
            }

            82%,
            100% {
                left: 125%;
                opacity: 0;
            }
        }

        @keyframes particleFloat {
            0% {
                opacity: 0;
                transform:
                    translate3d(0, 22px, 0)
                    scale(0.5);
            }

            15% {
                opacity: 0.70;
            }

            75% {
                opacity: 0.50;
            }

            100% {
                opacity: 0;
                transform:
                    translate3d(
                        var(--drift),
                        -125px,
                        0
                    )
                    scale(1.15);
            }
        }

        @keyframes statusPulse {
            0% {
                opacity: 0.7;
                transform: scale(0.7);
            }

            100% {
                opacity: 0;
                transform: scale(1.5);
            }
        }

        @keyframes statusDot {
            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.14);
            }
        }

        @keyframes formEntrance {
            from {
                opacity: 0;
                transform: translateY(22px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes logoSweep {
            0%,
            18% {
                left: -35%;
                opacity: 0;
            }

            28% {
                opacity: 1;
            }

            55% {
                opacity: 0.8;
            }

            72%,
            100% {
                left: 125%;
                opacity: 0;
            }
        }

        @keyframes spinner {
            to {
                transform: rotate(360deg);
            }
        }

        /* =========================================================
           RESPONSIVE DESIGN
        ========================================================= */

        @media (max-width: 1120px) {
            body {
                align-items: flex-start;
            }

            .auth-shell {
                grid-template-columns: 1fr;
            }

            .hero-content {
                min-height: 610px;
            }

            .hero-image {
                background-position: center 54%;
            }

            .form-panel {
                padding: 58px 40px;
            }
        }

        @media (max-width: 720px) {
            body {
                padding: 12px;
            }

            .auth-shell {
                border-radius: 24px;
            }

            .hero-content {
                min-height: 520px;
                padding: 28px 24px 24px;
            }

            .hero-header {
                max-width: 520px;
            }

            .hero-title {
                font-size: 39px;
                letter-spacing: -1.7px;
            }

            .hero-description {
                max-width: 500px;
                font-size: 13px;
            }

            .hero-footer {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .hero-caption {
                max-width: 100%;
                text-align: left;
            }

            .form-panel {
                padding: 40px 22px 32px;
            }

            .brand-logo-wrapper {
                margin-bottom: 23px;
            }

            .form-header h2 {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .hero-content {
                min-height: 420px;
            }

            .hero-image {
                background-position: 64% center;
            }

            .platform-badge {
                padding: 8px 12px;
                font-size: 9px;
            }

            .hero-title {
                font-size: 32px;
            }

            .hero-description {
                display: none;
            }

            .hero-caption {
                display: none;
            }

            .hero-status {
                min-width: auto;
            }

            .form-panel {
                padding: 30px 18px 27px;
            }

            .form-options {
                align-items: flex-start;
                flex-direction: column;
                gap: 13px;
            }

            .brand-logo-wrapper {
                padding: 10px;
            }

            .brand-logo {
                max-width: 300px;
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
</head>

<body>
    <main class="auth-page">
        <section class="auth-shell">

            <aside
                class="hero-panel"
                id="heroPanel"
            >
                <div
                    class="hero-image"
                    id="heroImage"
                ></div>

                <div class="hero-overlay"></div>
                <div class="hero-vignette"></div>
                <div class="hero-grid"></div>
                <div class="hero-glow"></div>
                <div class="energy-sweep"></div>

                <div
                    class="particle-layer"
                    id="particleLayer"
                    aria-hidden="true"
                ></div>

                <div
                    class="hero-content"
                    id="heroContent"
                >
                    <header class="hero-header">
                        <div class="platform-badge">
                            <span class="badge-indicator"></span>
                            Solar Intelligence Platform
                        </div>

                        <h1 class="hero-title">
                            Smarter energy planning.
                            <span>Confident decisions.</span>
                        </h1>

                        <p class="hero-description">
                            Integrated photovoltaic engineering,
                            quotation management, and data-driven
                            decision support in one secure platform.
                        </p>
                    </header>

                    <footer class="hero-footer">
                        <div class="hero-status">
                            <div class="hero-status-icon">
                                <span class="hero-status-dot"></span>
                            </div>

                            <div class="hero-status-copy">
                                <strong>System Operational</strong>
                                <span>All systems running optimally</span>
                            </div>
                        </div>

                        <p class="hero-caption">
                            Solar engineering, energy analysis,
                            and quotation intelligence
                        </p>
                    </footer>
                </div>
            </aside>

            <section class="form-panel">
                <div class="form-container">

                    <div class="brand-logo-wrapper">
                        <img
                            src="{{ asset('images/nexorin-logo.png') }}"
                            alt="NEXORIN Solar Engineering and Quotation Platform"
                            class="brand-logo"
                        >
                    </div>

                    <header class="form-header">
                        <div class="form-eyebrow">
                            Secure Access
                        </div>

                        <h2>Welcome Back</h2>

                        <p>
                            Enter your account credentials to access
                            the NEXORIN engineering platform.
                        </p>
                    </header>

                    @if (session('status'))
                        <div
                            class="alert alert-success"
                            role="status"
                        >
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div
                            class="alert alert-danger"
                            role="alert"
                        >
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('login') }}"
                        id="loginForm"
                    >
                        @csrf

                        <div class="form-group">
                            <label
                                for="email"
                                class="form-label"
                            >
                                Email Address
                            </label>

                            <div class="input-wrapper">
                                <svg
                                    class="input-icon"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <rect
                                        x="3.5"
                                        y="5.5"
                                        width="17"
                                        height="13"
                                        rx="2"
                                    />

                                    <path
                                        d="m4.5 7 7.5 5.5L19.5 7"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    value="{{ old('email') }}"
                                    placeholder="name@company.com"
                                    autocomplete="email"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label
                                for="password"
                                class="form-label"
                            >
                                Password
                            </label>

                            <div class="input-wrapper">
                                <svg
                                    class="input-icon"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <rect
                                        x="5"
                                        y="10"
                                        width="14"
                                        height="10"
                                        rx="2"
                                    />

                                    <path
                                        d="M8 10V7a4 4 0 0 1 8 0v3"
                                        stroke-linecap="round"
                                    />
                                </svg>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    id="togglePassword"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >
                                    <svg
                                        id="eyeIcon"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"
                                            stroke-linejoin="round"
                                        />

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="2.5"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="remember-me">
                                <span class="remember-checkbox">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        {{ old('remember') ? 'checked' : '' }}
                                    >

                                    <span class="checkbox-ui">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="3"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="m5 12 4 4L19 6"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </span>
                                </span>

                                <span>Remember me</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a
                                    href="{{ route('password.request') }}"
                                    class="forgot-link"
                                >
                                    Forgot password?
                                </a>
                            @endif
                        </div>

                        <button
                            type="submit"
                            class="login-button"
                            id="loginButton"
                        >
                            <span
                                class="button-text"
                                id="buttonText"
                            >
                                Sign In to NEXORIN
                            </span>

                            <svg
                                class="button-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                aria-hidden="true"
                            >
                                <path
                                    d="M5 12h14"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="m14 7 5 5-5 5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>

                            <span
                                class="button-spinner"
                                aria-hidden="true"
                            ></span>
                        </button>
                    </form>

                    <div class="security-card">
                        <div class="security-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 3 5 6v5c0 4.8 2.8 8.2 7 10 4.2-1.8 7-5.2 7-10V6l-7-3Z"
                                    stroke-linejoin="round"
                                />

                                <path
                                    d="m9 12 2 2 4-4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>

                        <div class="security-copy">
                            <strong>Protected System Access</strong>

                            <span>
                                Secure authentication safeguards your
                                engineering data and account activity.
                            </span>
                        </div>
                    </div>

                    <p class="copyright">
                        © {{ date('Y') }} NEXORIN.
                        Solar Engineering and Quotation Platform.
                    </p>
                </div>
            </section>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput =
                document.getElementById('password');

            const toggleButton =
                document.getElementById('togglePassword');

            const eyeIcon =
                document.getElementById('eyeIcon');

            const loginForm =
                document.getElementById('loginForm');

            const loginButton =
                document.getElementById('loginButton');

            const buttonText =
                document.getElementById('buttonText');

            const heroPanel =
                document.getElementById('heroPanel');

            const heroImage =
                document.getElementById('heroImage');

            const heroContent =
                document.getElementById('heroContent');

            const particleLayer =
                document.getElementById('particleLayer');

            if (
                passwordInput &&
                toggleButton &&
                eyeIcon
            ) {
                toggleButton.addEventListener(
                    'click',
                    function () {
                        const isVisible =
                            passwordInput.type === 'text';

                        passwordInput.type =
                            isVisible
                                ? 'password'
                                : 'text';

                        toggleButton.setAttribute(
                            'aria-pressed',
                            isVisible
                                ? 'false'
                                : 'true'
                        );

                        toggleButton.setAttribute(
                            'aria-label',
                            isVisible
                                ? 'Show password'
                                : 'Hide password'
                        );

                        eyeIcon.innerHTML =
                            isVisible
                                ? `
                                    <path
                                        d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"
                                        stroke-linejoin="round"
                                    />
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="2.5"
                                    />
                                `
                                : `
                                    <path
                                        d="M3 3l18 18"
                                        stroke-linecap="round"
                                    />
                                    <path
                                        d="M10.7 6.2A10 10 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-2.4 3.1"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6a9.6 9.6 0 0 0 4.1-.9"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                `;
                    }
                );
            }

            if (
                loginForm &&
                loginButton &&
                buttonText
            ) {
                loginForm.addEventListener(
                    'submit',
                    function () {
                        loginButton.disabled = true;

                        loginButton.classList.add(
                            'is-loading'
                        );

                        buttonText.textContent =
                            'Signing In...';
                    }
                );
            }

            if (
                heroPanel &&
                heroImage &&
                heroContent &&
                window.matchMedia(
                    '(pointer: fine)'
                ).matches
            ) {
                heroPanel.addEventListener(
                    'mousemove',
                    function (event) {
                        const rect =
                            heroPanel.getBoundingClientRect();

                        const x =
                            (
                                event.clientX -
                                rect.left
                            ) / rect.width - 0.5;

                        const y =
                            (
                                event.clientY -
                                rect.top
                            ) / rect.height - 0.5;

                        heroImage.style.transform =
                            `
                                scale(1.065)
                                translate(
                                    ${x * -11}px,
                                    ${y * -9}px
                                )
                            `;

                        heroContent.style.transform =
                            `
                                translate(
                                    ${x * 4}px,
                                    ${y * 3}px
                                )
                            `;
                    }
                );

                heroPanel.addEventListener(
                    'mouseleave',
                    function () {
                        heroImage.style.transform =
                            'scale(1.045)';

                        heroContent.style.transform =
                            'translate(0, 0)';
                    }
                );
            }

            if (particleLayer) {
                const particleCount =
                    window.innerWidth < 720
                        ? 12
                        : 24;

                const particleColors = [
                    '#43e2ce',
                    '#77f0de',
                    '#ffd04b',
                    '#ffffff'
                ];

                for (
                    let index = 0;
                    index < particleCount;
                    index++
                ) {
                    const particle =
                        document.createElement('span');

                    const size =
                        Math.random() * 3 + 1;

                    const left =
                        Math.random() * 100;

                    const top =
                        Math.random() * 100;

                    const duration =
                        Math.random() * 9 + 8;

                    const delay =
                        Math.random() * -14;

                    const drift =
                        Math.random() * 70 - 35;

                    const color =
                        particleColors[
                            Math.floor(
                                Math.random() *
                                particleColors.length
                            )
                        ];

                    particle.className = 'particle';

                    particle.style.setProperty(
                        '--size',
                        `${size}px`
                    );

                    particle.style.setProperty(
                        '--left',
                        `${left}%`
                    );

                    particle.style.setProperty(
                        '--top',
                        `${top}%`
                    );

                    particle.style.setProperty(
                        '--duration',
                        `${duration}s`
                    );

                    particle.style.setProperty(
                        '--delay',
                        `${delay}s`
                    );

                    particle.style.setProperty(
                        '--drift',
                        `${drift}px`
                    );

                    particle.style.setProperty(
                        '--particle-color',
                        color
                    );

                    particleLayer.appendChild(
                        particle
                    );
                }
            }
        });
    </script>
</body>
</html>
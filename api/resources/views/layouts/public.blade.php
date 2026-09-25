<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A1128">
    <meta name="description" content="@yield('description', 'Ovezi makes shared expenses simple. Split. Share. Settle.')">
    <meta property="og:title" content="@yield('title', 'Ovezi')">
    <meta property="og:description" content="@yield('description', 'Ovezi makes shared expenses simple. Split. Share. Settle.')">
    <meta property="og:type" content="website">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta property="og:image" content="{{ asset('brand/social-preview.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Ovezi. Shared expenses, made easy.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset('brand/social-preview.png') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <title>@yield('title', 'Ovezi') · Split. Share. Settle.</title>
    <style>
        :root {
            color-scheme: light dark;
            --background: #F7F8F6;
            --surface: #FFFFFF;
            --link: #006A50;
            --navy: #0A1128;
            --mint: #00F5A0;
            --ink: #0A1128;
            --muted: #5D6975;
            --line: #D5DDE0;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            background: var(--background);
            font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: inherit; }
        .shell { width: min(1120px, calc(100% - 40px)); margin: 0 auto; }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--background);
            border-bottom: 1px solid var(--line);
        }
        .nav { min-height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 28px; }
        .wordmark { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; font-size: 20px; font-weight: 600; letter-spacing: -.03em; }
        .wordmark .brand-mark { width: 34px; height: 34px; color: var(--link); }
        .nav-links { display: flex; align-items: center; gap: 24px; color: var(--muted); font-size: 14px; font-weight: 500; }
        .nav-links a { text-decoration: none; min-height: 48px; display: inline-flex; align-items: center; }
        .nav-links a:hover, .nav-links a:focus-visible, button:focus-visible, input:focus-visible { color: var(--link); }

        main { min-height: calc(100vh - 150px); }
        .hero { padding: clamp(76px, 12vw, 150px) 0 96px; text-align: center; }
        .hero-mark {
            width: clamp(112px, 16vw, 166px);
            height: clamp(112px, 16vw, 166px);
            margin: 0 auto 30px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 34%;
            color: var(--mint);
            background: linear-gradient(145deg, #121d3a, #070c1d);
            box-shadow: 0 16px 40px rgba(10,17,40,.12), inset 0 1px 0 rgba(255,255,255,.08);
        }
        .hero-mark .brand-mark { width: 68%; }
        .eyebrow { margin: 0 0 14px; color: var(--link); font-size: 13px; font-weight: 600; letter-spacing: .16em; text-transform: uppercase; }
        h1 { max-width: 820px; margin: 0 auto; font-size: clamp(48px, 8vw, 88px); line-height: .98; letter-spacing: -.065em; }
        .lead { max-width: 660px; margin: 26px auto 0; color: var(--muted); font-size: clamp(18px, 2.4vw, 22px); line-height: 1.65; }
        .pill { display: inline-flex; align-items: center; gap: 9px; margin-top: 34px; padding: 13px 18px; border: 1px solid rgba(0,245,160,.24); border-radius: 999px; color: var(--link); background: rgba(0,245,160,.08); font-size: 14px; font-weight: 600; }
        .pill-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--mint);  }

        .feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; padding-bottom: 100px; }
        .card { padding: 28px; border: 1px solid var(--line); border-radius: 24px; background: var(--surface); }
        .card-number { color: var(--link); font: 750 12px/1 ui-monospace, monospace; letter-spacing: .12em; }
        .card h2 { margin: 18px 0 10px; font-size: 21px; letter-spacing: -.025em; }
        .card p { margin: 0; color: var(--muted); line-height: 1.7; }

        .document { width: min(780px, calc(100% - 40px)); margin: 0 auto; padding: 72px 0 100px; }
        .document-header { padding-bottom: 38px; border-bottom: 1px solid var(--line); }
        .document h1 { margin: 0; font-size: clamp(40px, 7vw, 66px); line-height: 1; }
        .updated { margin: 18px 0 0; color: var(--muted); font-size: 14px; }
        .document-intro { margin: 28px 0 0; color: var(--ink); font-size: 19px; line-height: 1.7; }
        .document section { padding-top: 38px; }
        .document section + section { margin-top: 8px; border-top: 1px solid var(--line); }
        .document h2 { margin: 0 0 16px; font-size: 24px; letter-spacing: -.03em; }
        .document h3 { margin: 24px 0 8px; font-size: 17px; }
        .document p, .document li { color: var(--muted); font-size: 16px; line-height: 1.75; }
        .document p { margin: 12px 0; }
        .document ul { margin: 14px 0; padding-left: 22px; }
        .document a { color: var(--link); text-underline-offset: 3px; }
        .support-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 32px; }
        .support-card { padding: 24px; border: 1px solid var(--line); border-radius: 20px; background: var(--surface); }
        .support-card h2 { font-size: 19px; }
        .support-card p { margin-bottom: 0; }
        .email-button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; border: 0; font: inherit; cursor: pointer; margin-top: 14px; padding: 12px 16px; border-radius: 12px; color: var(--navy) !important; background: var(--mint); text-decoration: none; font-weight: 600; }

        footer { border-top: 1px solid var(--line); }
        .footer-inner { min-height: 78px; display: flex; align-items: center; justify-content: space-between; gap: 20px; color: var(--muted); font-size: 13px; }
        .footer-links { display: flex; gap: 18px; }
        .footer-links a { text-decoration: none; }

        @media (max-width: 760px) {
            .shell { width: min(100% - 28px, 1120px); }
            .nav { min-height: 64px; }
            .nav-links { gap: 14px; font-size: 13px; }
            .nav-links .optional { display: none; }
            .hero { padding-top: 64px; }
            .feature-grid, .support-grid { grid-template-columns: 1fr; }
            .document { width: min(100% - 32px, 780px); padding-top: 52px; }
            .footer-inner { padding: 22px 0; align-items: flex-start; flex-direction: column; }
        }
        a:focus-visible, button:focus-visible, input:focus-visible { outline: 3px solid var(--link); outline-offset: 5px; }
        h1, h2 { font-weight: 600; }
        @media (prefers-color-scheme: dark) {
            :root { --background: #0D1422; --surface: #172231; --ink: #F5F7FC; --muted: #B4BFCE; --line: #344356; --link: #70E8BF; }
        }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
    </style>
</head>
<body>
    <header class="site-header">
        <nav class="nav shell" aria-label="Main navigation">
            <a class="wordmark" href="{{ route('home') }}">
                <x-brand-mark />
                <span>ovezi</span>
            </a>
            <div class="nav-links">
                <a href="{{ route('privacy') }}">Privacy</a>
                <a href="{{ route('terms') }}">Terms</a>
                <a class="optional" href="{{ route('support') }}">Support</a>
            </div>
        </nav>
    </header>

    <main>@yield('content')</main>

    <footer>
        <div class="footer-inner shell">
            <span>© {{ date('Y') }} Ovezi. Split. Share. Settle.</span>
            <div class="footer-links">
                <a href="{{ route('privacy') }}">Privacy</a>
                <a href="{{ route('terms') }}">Terms</a>
                <a href="{{ route('support') }}">Support</a>
                <a href="{{ route('account-deletion.create') }}">Delete account</a>
            </div>
        </div>
    </footer>
</body>
</html>

@php
    $companyName      = \App\Models\SystemSetting::get('company_name', 'Skynet Digital Limited');
    $companyShortName = \App\Models\SystemSetting::get('company_short_name', 'Skynet');
    $appTagline       = \App\Models\SystemSetting::get('app_tagline', 'Digital POS & Inventory Terminal');
    $supportPhone     = \App\Models\SystemSetting::get('support_phone', '+234 803 207 2831');
    $supportEmail     = \App\Models\SystemSetting::get('support_email', 'support@skynetdigitalltd.com');
    $logoPath         = \App\Models\SystemSetting::get('company_logo_path');
    $companyLogo      = $logoPath ? asset('storage/' . $logoPath) : asset('images/logo.png');
    $cleanPhone       = preg_replace('/[^0-9]/', '', $supportPhone);

    // ── Resolve Splash Branding Context (Branch vs Platform) ─────────────────
    $segment1 = request()->segment(1);
    $currentBranch = null;
    if ($segment1 && !in_array($segment1, ['superadmin', 'api', 'build', 'storage', 'images', 'vendor', 'livewire', 'manifest.json', 'sw.js', 'robots.txt'])) {
        $currentBranch = function_exists('current_branch') ? current_branch() : null;
        if (!$currentBranch) {
            $currentBranch = \App\Models\Branch::where('slug', $segment1)->first();
        }
    }

    if ($currentBranch) {
        $splashName    = $currentBranch->name;
        $splashTagline = $currentBranch->address ?: ($companyShortName . ' Point of Sale');
        $splashLogo    = $currentBranch->logo_url ?: ($currentBranch->logo_path ? asset('storage/' . $currentBranch->logo_path) : $companyLogo);
    } else {
        $splashName    = $companyName;
        $splashTagline = $appTagline;
        $splashLogo    = $companyLogo;
    }

    $jsonLdData = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $companyName,
        'description' => $companyName . ' - ' . $appTagline . '. Streamlined sales processing, multi-branch inventory tracking, and business reporting.',
        'url' => url('/'),
        'logo' => $companyLogo,
        'sameAs' => [
            'https://wa.me/' . $cleanPhone,
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            '@type' => 'ContactPoint',
            'telephone' => $supportPhone,
            'contactType' => 'Customer Support',
            'availableLanguage' => 'English',
            'email' => $supportEmail,
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ $companyName }} - {{ $appTagline }}</title>

    <meta name="description"
        content="{{ $companyName }} - {{ $appTagline }}. Streamlined sales processing, multi-branch inventory tracking, real-time barcode scanning, and business reporting.">
    <meta name="keywords"
        content="{{ $companyName }}, {{ $companyShortName }}, POS System, Retail Management, Inventory Tracking, Point of Sale, Tech Solutions">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="robots" content="index, follow">

    @isset($meta)
        <meta property="og:title" content="{{ $meta['title'] }}">
        <meta property="og:description" content="{{ $meta['description'] }}">
        <meta property="og:image" content="{{ $meta['image'] }}">
        <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="fb:app_id" content="1440937930739472">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $meta['title'] }}">
        <meta name="twitter:description" content="{{ $meta['description'] }}">
        <meta name="twitter:image" content="{{ $meta['image'] }}">
    @else
        <meta property="og:title" content="{{ $companyName }}">
        <meta property="og:description"
            content="{{ $companyName }} - {{ $appTagline }}. Streamlined sales processing, multi-branch inventory tracking, real-time barcode scanning, and business reporting.">
        <meta property="og:type" content="website">
        <meta property="fb:app_id" content="1440937930739472">
        <meta property="og:image" content="{{ $companyLogo }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:url" content="{{ url()->current() }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $companyName }}">
        <meta name="twitter:description"
            content="{{ $companyName }} - {{ $appTagline }}. Professional POS & inventory management system.">
        <meta name="twitter:image" content="{{ $companyLogo }}">
    @endisset


    <script type="application/ld+json">
        {!! json_encode($jsonLdData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>



    <link rel="icon" type="image/png" href="{{ $companyLogo }}">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#10b981">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        (function() {
            try {
                var theme = localStorage.getItem('user-theme') || localStorage.getItem('theme');
                var isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme-mode', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme-mode', 'light');
                }
            } catch(e) {}

            // Pre-Login Splash Auto-Dismiss Controller
            window.dismissPreSplash = function() {
                var splash = document.getElementById('pre-login-splash');
                if (splash && !splash.classList.contains('splash-fade-out')) {
                    splash.classList.add('splash-fade-out');
                    setTimeout(function() {
                        if (splash && splash.parentNode) {
                            splash.parentNode.removeChild(splash);
                        }
                    }, 400);
                }
            };

            // Hard safety timeouts so the splash screen NEVER gets stuck on any network latency or slow CDN:
            window.addEventListener('load', function() {
                setTimeout(window.dismissPreSplash, 150);
            });
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(window.dismissPreSplash, 1200);
            });
            setTimeout(window.dismissPreSplash, 2200);
        })();
    </script>

    <style>
        /* ── Pre-Login Splash Screen Light Mode (Default) ── */
        #pre-login-splash {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 9999999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: #f8fafc;
            color: #0f172a;
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            overflow: hidden;
        }

        .splash-brand-logo {
            max-height: 64px;
            max-width: 220px;
            object-fit: contain;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.06));
            animation: splashLogoPulse 2.2s ease-in-out infinite;
        }

        .splash-brand-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.025em;
            margin: 0 0 6px 0;
            text-align: center;
            max-width: 90vw;
            line-height: 1.25;
        }

        .splash-brand-tagline {
            font-size: 0.8125rem;
            font-weight: 500;
            color: #64748b;
            margin: 0 0 28px 0;
            text-align: center;
            max-width: 320px;
            line-height: 1.4;
        }

        .splash-track {
            width: 140px;
            height: 3.5px;
            background: rgba(148, 163, 184, 0.25);
            border-radius: 9999px;
            overflow: hidden;
            position: relative;
        }

        .splash-bar {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 40%;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
            border-radius: 9999px;
            animation: splashIndeterminate 1.4s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }

        /* ── Pre-Login Splash Screen Dark Mode ── */
        html.dark #pre-login-splash {
            background-color: #0b0f19;
            color: #f8fafc;
        }
        html.dark #pre-login-splash .splash-brand-title {
            color: #ffffff !important;
        }
        html.dark #pre-login-splash .splash-brand-tagline {
            color: #94a3b8 !important;
        }
        html.dark #pre-login-splash .splash-track {
            background: rgba(255, 255, 255, 0.12) !important;
        }
        html.dark #pre-login-splash .splash-bar {
            background: linear-gradient(90deg, #818cf8, #a855f7, #f472b6);
        }

        @keyframes splashLogoPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.025); opacity: 0.92; }
        }

        @keyframes splashIndeterminate {
            0% { left: -45%; width: 35%; }
            50% { width: 60%; }
            100% { left: 100%; width: 35%; }
        }

        .splash-fade-out {
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
        }
    </style>

    @inertiaHead()
    @php
        $activeBranchSlug = $currentBranch?->slug 
            ?? (\Illuminate\Support\Facades\URL::getDefaultParameters()['branch'] ?? null)
            ?? \App\Models\Branch::orderBy('id')->value('slug');
        if ($activeBranchSlug) {
            \Illuminate\Support\Facades\URL::defaults(['branch' => $activeBranchSlug]);
        }
    @endphp
    @routes()
</head>

<body>
    <!-- ── Splash Screen #1: Pre-Login Instant First-Paint ── -->
    <div id="pre-login-splash" aria-hidden="true">
        @if ($splashLogo)
            <img src="{{ $splashLogo }}" alt="{{ $splashName }}" class="splash-brand-logo" onerror="this.style.display='none'">
        @endif
        <h1 class="splash-brand-title">{{ $splashName }}</h1>
        @if ($splashTagline)
            <p class="splash-brand-tagline">{{ $splashTagline }}</p>
        @endif
        <div class="splash-track">
            <div class="splash-bar"></div>
        </div>
    </div>

    @inertia()
</body>

</html>
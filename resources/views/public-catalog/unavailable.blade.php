<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog Not Available — {{ $companyName ?? 'POS' }}</title>
    <meta name="robots" content="noindex, nofollow">
    @php
        $theme = $theme ?? [
            'key'             => 'skynet',
            'theme'           => '#7b00ff',
            'hover'           => '#6200cc',
            'light'           => '#f2e6ff',
            'rgb'             => '123, 0, 255',
            'text'            => '#5900b3',
            'dark'            => '#c084fc',
            'secondary'       => '#fba43d',
            'secondary_hover' => '#e08b26',
        ];
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
            theme: {
                extend: {
                    colors: {
                        theme: {
                            DEFAULT: '{{ $theme['theme'] }}',
                            hover: '{{ $theme['hover'] }}',
                            light: '{{ $theme['light'] }}',
                            text: '{{ $theme['text'] }}',
                            dark: '{{ $theme['dark'] }}',
                            secondary: '{{ $theme['secondary'] }}',
                            'secondary-hover': '{{ $theme['secondary_hover'] }}',
                        },
                        emerald: {
                            50: '{{ $theme['light'] }}',
                            100: '{{ $theme['light'] }}',
                            200: '{{ $theme['light'] }}',
                            300: '{{ $theme['dark'] }}',
                            400: '{{ $theme['dark'] }}',
                            500: '{{ $theme['theme'] }}',
                            600: '{{ $theme['theme'] }}',
                            700: '{{ $theme['hover'] }}',
                            800: '{{ $theme['text'] }}',
                            900: '{{ $theme['text'] }}',
                            950: '{{ $theme['text'] }}',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --color-theme: {{ $theme['theme'] }};
            --color-theme-hover: {{ $theme['hover'] }};
            --color-theme-light: {{ $theme['light'] }};
            --color-theme-rgb: {{ $theme['rgb'] }};
            --color-theme-text: {{ $theme['text'] }};
            --color-theme-dark: {{ $theme['dark'] }};
            --color-secondary: {{ $theme['secondary'] }};
            --color-secondary-hover: {{ $theme['secondary_hover'] }};
        }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 min-h-screen flex flex-col justify-between antialiased selection:bg-emerald-500 selection:text-white">

    <div class="flex-1 flex items-center justify-center p-6">
        <div class="max-w-md w-full text-center space-y-6">
            
            <div class="mx-auto w-20 h-20 rounded-3xl bg-emerald-100/80 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 flex items-center justify-center shadow-sm">
                <svg class="w-10 h-10 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>

            <div class="space-y-2">
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Catalog Not Available
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    This branch price list is currently offline or not sharing a public catalog at this time. Please check back later or contact the shop directly.
                </p>
            </div>

            <div class="pt-4 border-t border-slate-200/80 dark:border-slate-800">
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    If you are the branch owner, you can enable your public catalog anytime under <strong class="text-slate-600 dark:text-slate-300">Settings &rarr; Public Branch Catalog</strong>.
                </p>
            </div>

        </div>
    </div>

    <footer class="py-6 text-center text-xs text-slate-400 dark:text-slate-600 border-t border-slate-200/60 dark:border-slate-800/60">
        Powered by {{ $companyName ?? 'Skynet POS' }}
    </footer>

</body>
</html>

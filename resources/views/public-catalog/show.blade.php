<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $branch['name'] }} — Price List</title>

    {{-- Strict SEO & Privacy: No indexing --}}
    <meta name="robots" content="noindex, nofollow, noarchive">

    {{-- Open Graph Meta Tags for WhatsApp & Social Sharing --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $branch['name'] }} — Price List">
    <meta property="og:description" content="View our live selling prices and available products at {{ $branch['name'] }}.">
    @if(!empty($branch['logo_url']))
        <meta property="og:image" content="{{ $branch['logo_url'] }}">
    @endif
    <meta property="og:url" content="{{ request()->fullUrl() }}">

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

    {{-- Fonts & Styling --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
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
                        mono: ['"JetBrains Mono"', 'monospace'],
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
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        
        /* Hide scrollbars across all browsers and devices */
        .no-scrollbar::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
            background: transparent !important;
        }
        .no-scrollbar {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }
        .scroll-touch {
            -webkit-overflow-scrolling: touch;
            touch-action: pan-x;
            overscroll-behavior-x: contain;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 min-h-screen flex flex-col justify-between antialiased selection:bg-emerald-500 selection:text-white">

    <!-- ══ STICKY TOP BRAND HEADER ════════════════════════════════════════════════ -->
    <header class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800/80 sticky top-0 z-30 shadow-xs">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5">
            <div class="flex items-center justify-between gap-4">
                
                <!-- Branch Identity -->
                <div class="flex items-center gap-3 min-w-0">
                    @if(!empty($branch['logo_url']))
                        <img src="{{ $branch['logo_url'] }}" alt="{{ $branch['name'] }}" class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl object-contain bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-1 shrink-0 shadow-xs">
                    @else
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl text-white font-black text-lg flex items-center justify-center shrink-0 shadow-xs uppercase tracking-tight" style="background: linear-gradient(135deg, {{ $theme['theme'] }}, {{ $theme['secondary'] }});">
                            {{ mb_substr($branch['name'], 0, 2) }}
                        </div>
                    @endif

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h1 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white truncate tracking-tight">
                                {{ $branch['name'] }}
                            </h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800 shrink-0">
                                Price List
                            </span>
                        </div>

                        @if(!empty($branch['address']) || !empty($branch['phone']))
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                @if(!empty($branch['address']))
                                    <span class="flex items-center gap-1 truncate">
                                        <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate">{{ $branch['address'] }}</span>
                                    </span>
                                @endif

                                @if(!empty($branch['phone']))
                                    <a href="tel:{{ $branch['phone'] }}" class="flex items-center gap-1 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium">
                                        <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span>{{ $branch['phone'] }}</span>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Live Tag -->
                <div class="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-semibold shrink-0">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live Stock</span>
                </div>

            </div>
        </div>
    </header>

    <!-- ══ MAIN CATALOG BODY ═════════════════════════════════════════════════════ -->
    <main class="max-w-6xl w-full mx-auto px-4 sm:px-6 py-6 space-y-6 flex-1">

        <!-- Search & Filter Controls (GET Form for full non-JS & JS compatibility) -->
        <form method="GET" action="{{ route('catalog.show', ['slug' => $branch['slug']]) }}" id="catalogFilterForm" class="space-y-3 w-full max-w-full min-w-0">
            
            <div class="flex flex-col sm:flex-row gap-2.5">
                <!-- Search Input -->
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <input
                        type="text"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Search items by name..."
                        autocomplete="off"
                        class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition shadow-xs"
                    >

                    @if(!empty($filters['search']))
                        <a href="{{ route('catalog.show', ['slug' => $branch['slug'], 'category' => $filters['category'], 'sort' => $filters['sort']]) }}" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif
                </div>

                <!-- Sort Dropdown -->
                <div class="flex items-center gap-2">
                    <select
                        name="sort"
                        onchange="document.getElementById('catalogFilterForm').submit()"
                        class="w-full sm:w-auto px-3.5 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition shadow-xs cursor-pointer"
                    >
                        <option value="name_asc" {{ ($filters['sort'] ?? '') === 'name_asc' ? 'selected' : '' }}>Name (A to Z)</option>
                        <option value="name_desc" {{ ($filters['sort'] ?? '') === 'name_desc' ? 'selected' : '' }}>Name (Z to A)</option>
                        <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_desc" {{ ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    </select>

                    <button type="submit" class="hidden sm:inline-flex items-center px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition shadow-xs">
                        Search
                    </button>
                </div>
            </div>

            <!-- Hidden field for active category -->
            <input type="hidden" name="category" id="categoryInput" value="{{ $filters['category'] ?? '' }}">

            <!-- Category Filter Chips (Horizontal Scroll Edge-to-Edge with Hidden Scrollbar) -->
            @if(!empty($categories))
                <div class="relative w-full max-w-full min-w-0 overflow-hidden">
                    <div
                        id="categoryScrollContainer"
                        class="flex flex-nowrap items-center gap-2 overflow-x-auto no-scrollbar scroll-touch py-1.5 px-4 -mx-4 sm:mx-0 sm:px-0 cursor-grab select-none"
                    >
                        <button
                            type="button"
                            onclick="selectCategory('')"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 whitespace-nowrap cursor-pointer {{ empty($filters['category']) ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700' }}"
                        >
                            All Categories
                        </button>

                        @foreach($categories as $cat)
                            <button
                                type="button"
                                onclick="selectCategory('{{ $cat['id'] }}')"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 whitespace-nowrap cursor-pointer {{ ($filters['category'] ?? '') === (string)$cat['id'] ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700' }}"
                            >
                                {{ $cat['name'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

        </form>

        <!-- Results Counter & Active Filters Indicator -->
        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800/80 pb-2">
            <div>
                @if($total_count > 0)
                    Showing <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $from }}–{{ $to }}</strong> of <strong class="text-slate-800 dark:text-slate-200 font-bold">{{ $total_count }}</strong> items
                @else
                    No items found
                @endif
                @if(!empty($filters['search']))
                    for &ldquo;<span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ $filters['search'] }}</span>&rdquo;
                @endif
            </div>

            @if(!empty($filters['search']) || !empty($filters['category']) || ($filters['sort'] ?? '') !== 'name_asc')
                <a href="{{ route('catalog.show', ['slug' => $branch['slug']]) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline font-semibold flex items-center gap-1">
                    <span>Reset Filters</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </a>
            @endif
        </div>

        <!-- ══ PRODUCT GRID ═══════════════════════════════════════════════════════ -->
        @if(!empty($items))
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                @foreach($items as $item)
                    <article class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-3 sm:p-4 flex flex-col justify-between hover:shadow-md hover:border-slate-300 dark:hover:border-slate-700 transition duration-150 group">
                        
                        <!-- Top Image & Category Tag -->
                        <div class="space-y-2.5">
                            <div class="relative w-full aspect-square rounded-xl bg-slate-100 dark:bg-slate-800/60 flex items-center justify-center overflow-hidden border border-slate-100 dark:border-slate-800">
                                @if(!empty($item['image_url']))
                                    <img
                                        src="{{ $item['image_url'] }}"
                                        alt="{{ $item['name'] }}"
                                        loading="lazy"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                        onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                                    >
                                    <div class="hidden w-full h-full flex items-center justify-center text-slate-300 dark:text-slate-600">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-300 dark:text-slate-600 p-4">
                                        <svg class="w-10 h-10 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">No Image</span>
                                    </div>
                                @endif

                                <!-- Category Badge -->
                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-300 backdrop-blur-xs border border-slate-200/60 dark:border-slate-700/60 shadow-xs max-w-[55%] truncate">
                                    {{ $item['category_name'] }}
                                </span>

                                @if(!empty($item['variants']))
                                    <!-- Multi-variant Popout Pill -->
                                    <button
                                        type="button"
                                        onclick="openVariantModal(this)"
                                        class="absolute top-2 right-2 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs backdrop-blur-xs transition flex items-center gap-1 cursor-pointer"
                                        title="Click to view all variant prices"
                                    >
                                        <span>{{ count($item['variants']) + 1 }} Sizes</span>
                                        <svg class="w-3 h-3 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                @endif
                            </div>

                            <!-- Product Name -->
                            <div>
                                <h2 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white line-clamp-2 leading-snug group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition" title="{{ $item['name'] }}">
                                    {{ $item['name'] }}
                                </h2>
                            </div>
                        </div>

                        <!-- Bottom Pricing & Variants Area -->
                        <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 space-y-2">
                            
                            <!-- Base/Selected Unit Selling Price -->
                            <div class="flex items-baseline justify-between gap-1">
                                <span class="card-display-price text-sm sm:text-base font-extrabold text-emerald-600 dark:text-emerald-400 font-mono tracking-tight">
                                    {{ $item['base_unit']['price_formatted'] }}
                                </span>
                                <span class="card-display-unit text-[11px] font-medium text-slate-400 dark:text-slate-500 truncate">
                                    / {{ $item['base_unit']['unit_name'] }}
                                </span>
                            </div>

                            <!-- Active Variants Dropdown & Popout Trigger -->
                            @if(!empty($item['variants']))
                                <div class="flex items-center gap-1.5 pt-0.5">
                                    <div class="relative flex-1 min-w-0">
                                        <select
                                            class="variant-select w-full appearance-none pl-2 pr-6 py-1.5 rounded-lg text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700/80 hover:border-slate-300 dark:hover:border-slate-600 focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer transition truncate"
                                            onchange="handleVariantSelectChange(this)"
                                            aria-label="Select unit variant"
                                        >
                                            <option
                                                value="{{ $item['base_unit']['price_formatted'] }}"
                                                data-unit="{{ $item['base_unit']['unit_name'] }}"
                                            >
                                                {{ $item['base_unit']['unit_name'] }} &bull; {{ $item['base_unit']['price_formatted'] }}
                                            </option>
                                            @foreach($item['variants'] as $v)
                                                <option
                                                    value="{{ $v['price_formatted'] }}"
                                                    data-unit="{{ $v['unit_name'] }}"
                                                >
                                                    {{ $v['unit_name'] }}@if(!empty($v['multiplier_label'])) {{ $v['multiplier_label'] }}@endif &bull; {{ $v['price_formatted'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1.5 text-slate-400">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Popout Modal Trigger Button -->
                                    <button
                                        type="button"
                                        onclick="openVariantModal(this)"
                                        class="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 bg-slate-100 dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-slate-200 dark:border-slate-700/80 transition shrink-0 cursor-pointer"
                                        title="Popout all variant prices"
                                        aria-label="Popout all variant prices"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Hidden JSON Data for Popout Modal -->
                                <script type="application/json" class="item-variant-data">
                                    {!! json_encode([
                                        'name' => $item['name'],
                                        'category_name' => $item['category_name'],
                                        'image_url' => $item['image_url'],
                                        'base_unit' => $item['base_unit'],
                                        'variants' => $item['variants'],
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
                                </script>
                            @endif

                        </div>

                    </article>
                @endforeach
            </div>

            <!-- ══ PAGINATION CONTROLS ════════════════════════════════════════════ -->
            @if($last_page > 1)
                <nav class="flex items-center justify-center gap-2 pt-4">
                    @if($current_page > 1)
                        <a
                            href="{{ route('catalog.show', array_merge(['slug' => $branch['slug']], $filters, ['page' => $current_page - 1])) }}"
                            class="px-4 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition shadow-xs flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span>Previous</span>
                        </a>
                    @endif

                    <span class="px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        Page {{ $current_page }} of {{ $last_page }}
                    </span>

                    @if($current_page < $last_page)
                        <a
                            href="{{ route('catalog.show', array_merge(['slug' => $branch['slug']], $filters, ['page' => $current_page + 1])) }}"
                            class="px-4 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition shadow-xs flex items-center gap-1.5"
                        >
                            <span>Next</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endif
                </nav>
            @endif

        @else
            <!-- Empty State -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 p-10 text-center space-y-4 max-w-md mx-auto my-8 shadow-xs">
                <div class="mx-auto w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">No items found</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        We couldn't find any products matching your current search or category filter.
                    </p>
                </div>
                <div>
                    <a href="{{ route('catalog.show', ['slug' => $branch['slug']]) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition shadow-xs">
                        View All Products
                    </a>
                </div>
            </div>
        @endif

    </main>

    <!-- ══ FOOTER & DISCLAIMER ═══════════════════════════════════════════════════ -->
    <footer class="bg-white/80 dark:bg-slate-900/80 border-t border-slate-200/80 dark:border-slate-800/80 py-6 mt-12 text-center space-y-2">
        <div class="max-w-4xl mx-auto px-4 space-y-1.5">
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                Prices and availability may change. Please confirm at the shop.
            </p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                &copy; {{ date('Y') }} {{ $branch['name'] }}. All rights reserved. &bull; Powered by {{ $company_name }}
            </p>
        </div>
    </footer>

    <!-- ══ VARIANT POPOUT MODAL ═════════════════════════════════════════════════ -->
    <div
        id="variantModalBackdrop"
        class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-200"
        onclick="closeVariantModal()"
    >
        <div
            id="variantModalPanel"
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden transform scale-95 transition-transform duration-200"
            onclick="event.stopPropagation()"
        >
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-start gap-3.5">
                <div id="modalItemImgContainer" class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center overflow-hidden shrink-0 border border-slate-200/60 dark:border-slate-700/60">
                    <img id="modalItemImg" src="" alt="" class="w-full h-full object-cover hidden">
                    <svg id="modalItemImgFallback" class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0 pr-2">
                    <span id="modalItemCategory" class="inline-block text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded-md mb-1"></span>
                    <h3 id="modalItemName" class="text-sm sm:text-base font-bold text-slate-900 dark:text-white leading-snug break-words"></h3>
                </div>
                <button
                    type="button"
                    onclick="closeVariantModal()"
                    class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition shrink-0 cursor-pointer"
                    aria-label="Close"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body (Variant List) -->
            <div class="p-4 sm:p-5 max-h-[60vh] overflow-y-auto space-y-2">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500 dark:text-slate-400 pb-1">
                    <span>Available Sizes & Units</span>
                    <span>Live Price</span>
                </div>
                <div id="modalVariantList" class="space-y-2">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span class="text-[11px]">Select a size to view on product card.</span>
                <button
                    type="button"
                    onclick="closeVariantModal()"
                    class="px-4 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-white font-semibold text-xs transition cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentModalCard = null;
        window._isDraggingCategory = false;

        function selectCategory(catId) {
            if (window._isDraggingCategory) return;
            document.getElementById('categoryInput').value = catId;
            document.getElementById('catalogFilterForm').submit();
        }

        // Initialize category drag-to-scroll and auto-centering on load
        (function initCategoryScroll() {
            const slider = document.getElementById('categoryScrollContainer');
            if (!slider) return;

            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;

            slider.addEventListener('mousedown', (e) => {
                isDown = true;
                slider.classList.add('cursor-grabbing');
                slider.classList.remove('cursor-grab');
                startX = e.pageX - slider.offsetLeft;
                scrollLeft = slider.scrollLeft;
            });

            window.addEventListener('mouseup', () => {
                if (!isDown) return;
                isDown = false;
                slider.classList.remove('cursor-grabbing');
                slider.classList.add('cursor-grab');
                setTimeout(() => { window._isDraggingCategory = false; }, 60);
            });

            slider.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - slider.offsetLeft;
                const walk = (x - startX) * 1.5;
                if (Math.abs(walk) > 4) {
                    window._isDraggingCategory = true;
                }
                slider.scrollLeft = scrollLeft - walk;
            });

            // Auto-scroll active category chip into view smoothly if off-screen
            const activeChip = slider.querySelector('.bg-emerald-600');
            if (activeChip && activeChip !== slider.firstElementChild) {
                setTimeout(() => {
                    activeChip.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }, 100);
            }
        })();

        function handleVariantSelectChange(selectEl) {
            const card = selectEl.closest('article');
            if (!card) return;
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            const price = selectedOpt.value;
            const unit = selectedOpt.getAttribute('data-unit');

            const priceEl = card.querySelector('.card-display-price');
            const unitEl = card.querySelector('.card-display-unit');

            if (priceEl && price) priceEl.textContent = price;
            if (unitEl && unit) unitEl.textContent = '/ ' + unit;
        }

        function openVariantModal(triggerEl) {
            const card = triggerEl.closest('article');
            if (!card) return;
            currentModalCard = card;

            const scriptEl = card.querySelector('.item-variant-data');
            if (!scriptEl) return;

            try {
                const item = JSON.parse(scriptEl.textContent);

                document.getElementById('modalItemName').textContent = item.name;
                document.getElementById('modalItemCategory').textContent = item.category_name;

                const imgEl = document.getElementById('modalItemImg');
                const fallbackEl = document.getElementById('modalItemImgFallback');
                if (item.image_url) {
                    imgEl.src = item.image_url;
                    imgEl.classList.remove('hidden');
                    fallbackEl.classList.add('hidden');
                } else {
                    imgEl.src = '';
                    imgEl.classList.add('hidden');
                    fallbackEl.classList.remove('hidden');
                }

                const listContainer = document.getElementById('modalVariantList');
                listContainer.innerHTML = '';

                // 1. Base unit card
                const baseRow = document.createElement('div');
                baseRow.className = 'flex items-center justify-between p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 hover:border-emerald-500/60 transition cursor-pointer group';
                baseRow.onclick = function() {
                    applyVariantToCard(card, item.base_unit.price_formatted, item.base_unit.unit_name);
                    closeVariantModal();
                };
                baseRow.innerHTML = `
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition">${escapeHtml(item.base_unit.unit_name)}</span>
                            <span class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-950/80 px-1.5 py-0.5 rounded">Standard Base</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-sm sm:text-base font-extrabold text-emerald-600 dark:text-emerald-400 font-mono tracking-tight">${escapeHtml(item.base_unit.price_formatted)}</span>
                    </div>
                `;
                listContainer.appendChild(baseRow);

                // 2. Additional variants
                if (item.variants && item.variants.length > 0) {
                    item.variants.forEach(function(v) {
                        const row = document.createElement('div');
                        row.className = 'flex items-center justify-between p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 hover:border-emerald-500/60 transition cursor-pointer group';
                        row.onclick = function() {
                            applyVariantToCard(card, v.price_formatted, v.unit_name);
                            closeVariantModal();
                        };
                        row.innerHTML = `
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition">${escapeHtml(v.unit_name)}</span>
                                    ${v.multiplier_label ? `<span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium">${escapeHtml(v.multiplier_label)}</span>` : ''}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm sm:text-base font-extrabold text-emerald-600 dark:text-emerald-400 font-mono tracking-tight">${escapeHtml(v.price_formatted)}</span>
                            </div>
                        `;
                        listContainer.appendChild(row);
                    });
                }

                const backdrop = document.getElementById('variantModalBackdrop');
                const panel = document.getElementById('variantModalPanel');
                backdrop.classList.remove('opacity-0', 'pointer-events-none');
                backdrop.classList.add('opacity-100');
                panel.classList.remove('scale-95');
                panel.classList.add('scale-100');
                document.body.style.overflow = 'hidden';
            } catch (e) {
                console.error('Failed to open variant popout', e);
            }
        }

        function applyVariantToCard(card, priceFormatted, unitName) {
            const select = card.querySelector('.variant-select');
            if (select) {
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].getAttribute('data-unit') === unitName) {
                        select.selectedIndex = i;
                        break;
                    }
                }
            }
            const priceEl = card.querySelector('.card-display-price');
            const unitEl = card.querySelector('.card-display-unit');
            if (priceEl) priceEl.textContent = priceFormatted;
            if (unitEl) unitEl.textContent = '/ ' + unitName;
        }

        function closeVariantModal() {
            const backdrop = document.getElementById('variantModalBackdrop');
            const panel = document.getElementById('variantModalPanel');
            if (backdrop && panel) {
                backdrop.classList.add('opacity-0', 'pointer-events-none');
                backdrop.classList.remove('opacity-100');
                panel.classList.add('scale-95');
                panel.classList.remove('scale-100');
                document.body.style.overflow = '';
            }
            currentModalCard = null;
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeVariantModal();
        });

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>

</body>
</html>

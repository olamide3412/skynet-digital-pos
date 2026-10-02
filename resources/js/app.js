import './bootstrap';
import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link, router } from '@inertiajs/vue3'
import Layout from './Layouts/Layout.vue';
import PosLayout from './Layouts/PosLayout.vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import { formatDate } from './Utils/dateFormat';
import 'aos/dist/aos.css';
import { createPinia } from 'pinia';
import { useThemeStore } from './Stores/themeStore';
import FlashMessages from './Components/FlashMessages.vue';

const pinia = createPinia();

import { FontAwesomeIcon } from './fontawesome';

const LUMINOUS_MAP = {
    skynet: '#C084FC',
    emerald: '#34D399',
    blue: '#60A5FA',
    indigo: '#818CF8',
    violet: '#A78BFA',
    teal: '#2DD4BF',
    cyan: '#22D3EE',
    amber: '#FBBF24',
    rose: '#FB7185',
};

function applyTheme(themeColor, customPrimaryHex, customSecondaryHex, darkModeAccentMode, customDarkPrimaryHex) {
    if (typeof document === 'undefined') return;

    const rootStyle = document.documentElement.style;
    const mode = darkModeAccentMode || 'white';
    
    let darkHex = '#FFFFFF';
    if (mode === 'white') {
        darkHex = '#FFFFFF';
    } else if (mode === 'luminous') {
        darkHex = LUMINOUS_MAP[themeColor] || customDarkPrimaryHex || customPrimaryHex || '#C084FC';
    } else if (mode === 'custom') {
        darkHex = customDarkPrimaryHex || '#FFFFFF';
    }

    let hexD = darkHex.replace('#', '');
    if (hexD.length === 3) hexD = hexD.split('').map(c => c + c).join('');
    const dr = parseInt(hexD.substring(0, 2), 16) || 255;
    const dg = parseInt(hexD.substring(2, 4), 16) || 255;
    const db = parseInt(hexD.substring(4, 6), 16) || 255;

    rootStyle.setProperty('--color-theme-dark', darkHex);
    rootStyle.setProperty('--color-theme-dark-rgb', `${dr}, ${dg}, ${db}`);

    if (themeColor === 'custom' && customPrimaryHex) {
        document.documentElement.setAttribute('data-theme', 'custom');
        
        let hexP = customPrimaryHex.replace('#', '');
        if (hexP.length === 3) hexP = hexP.split('').map(c => c + c).join('');
        const pr = parseInt(hexP.substring(0, 2), 16) || 123;
        const pg = parseInt(hexP.substring(2, 4), 16) || 0;
        const pb = parseInt(hexP.substring(4, 6), 16) || 255;
        const phoverHex = '#' + [Math.max(0, Math.floor(pr * 0.85)), Math.max(0, Math.floor(pg * 0.85)), Math.max(0, Math.floor(pb * 0.85))].map(x => x.toString(16).padStart(2, '0')).join('');

        rootStyle.setProperty('--color-theme', customPrimaryHex);
        rootStyle.setProperty('--color-theme-hover', phoverHex);
        rootStyle.setProperty('--color-theme-light', `rgba(${pr}, ${pg}, ${pb}, 0.15)`);
        rootStyle.setProperty('--color-theme-rgb', `${pr}, ${pg}, ${pb}`);
        rootStyle.setProperty('--color-theme-text', phoverHex);

        const secHex = customSecondaryHex || '#FBA43D';
        let hexS = secHex.replace('#', '');
        if (hexS.length === 3) hexS = hexS.split('').map(c => c + c).join('');
        const sr = parseInt(hexS.substring(0, 2), 16) || 251;
        const sg = parseInt(hexS.substring(2, 4), 16) || 164;
        const sb = parseInt(hexS.substring(4, 6), 16) || 61;
        const shoverHex = '#' + [Math.max(0, Math.floor(sr * 0.85)), Math.max(0, Math.floor(sg * 0.85)), Math.max(0, Math.floor(sb * 0.85))].map(x => x.toString(16).padStart(2, '0')).join('');

        rootStyle.setProperty('--color-theme-secondary', secHex);
        rootStyle.setProperty('--color-theme-secondary-hover', shoverHex);
        rootStyle.setProperty('--color-theme-secondary-light', `rgba(${sr}, ${sg}, ${sb}, 0.15)`);
        rootStyle.setProperty('--color-theme-secondary-rgb', `${sr}, ${sg}, ${sb}`);
    } else {
        document.documentElement.setAttribute('data-theme', themeColor || 'skynet');
        rootStyle.removeProperty('--color-theme');
        rootStyle.removeProperty('--color-theme-hover');
        rootStyle.removeProperty('--color-theme-light');
        rootStyle.removeProperty('--color-theme-rgb');
        rootStyle.removeProperty('--color-theme-text');

        rootStyle.removeProperty('--color-theme-secondary');
        rootStyle.removeProperty('--color-theme-secondary-hover');
        rootStyle.removeProperty('--color-theme-secondary-light');
        rootStyle.removeProperty('--color-theme-secondary-rgb');
    }
}

// Helper to reliably extract the active branch slug from props, URL, or defaults
export function getActiveBranchSlug(pageProps) {
    if (typeof window === 'undefined') return null;
    const pathname = window.location.pathname;
    const segments = pathname.split('/').filter(Boolean);
    const firstSegment = segments[0] || '';
    const reserved = ['superadmin', 'org', 'api', 'build', 'storage', 'images', 'vendor', 'livewire', 'login', 'logout', 'register', 'password'];
    const urlBranch = (!reserved.includes(firstSegment) && firstSegment) ? firstSegment : null;

    return urlBranch
        || pageProps?.current_branch?.slug
        || pageProps?.branch?.slug
        || pageProps?.auth?.user?.branch?.slug
        || pageProps?.default_branch?.slug
        || (window.Ziggy?.defaults?.branch || null);
}

function syncZiggyDefaults(branchSlug) {
    if (typeof window !== 'undefined' && window.Ziggy) {
        if (!window.Ziggy.defaults || Array.isArray(window.Ziggy.defaults)) {
            window.Ziggy.defaults = {};
        }
        if (branchSlug) {
            window.Ziggy.defaults.branch = branchSlug;
        } else if (
            (window.location.pathname.startsWith('/superadmin') || window.location.pathname.startsWith('/org')) &&
            window.Ziggy.defaults.branch
        ) {
            delete window.Ziggy.defaults.branch;
        }
    }
}

// Early sync on start of navigation
router.on('start', (event) => {
    try {
        const urlStr = event?.detail?.visit?.url?.pathname || event?.detail?.visit?.url?.href || '';
        const path = urlStr.startsWith('http') ? new URL(urlStr).pathname : urlStr;
        const first = path.split('/').filter(Boolean)[0];
        const reserved = ['superadmin', 'org', 'api', 'build', 'storage', 'images', 'vendor', 'livewire', 'login', 'logout', 'register', 'password'];
        if (first && !reserved.includes(first)) {
            syncZiggyDefaults(first);
        }
    } catch {
        // Fallback silently
    }
});

// Update on success (when page props arrive)
router.on('success', (event) => {
    const props = event.detail.page.props;
    const branchSlug = getActiveBranchSlug(props);
    syncZiggyDefaults(branchSlug);
});

// Update on navigate (final phase)
router.on('navigate', (event) => {
    const props = event.detail.page.props;
    const branchSlug = getActiveBranchSlug(props);
    applyTheme(props.theme_color, props.custom_primary_hex, props.custom_secondary_hex, props.dark_mode_accent_mode, props.custom_dark_primary_hex);
    syncZiggyDefaults(branchSlug);
});

createInertiaApp({
    title: (title) => `${title}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const pageResolver = pages[`./Pages/${name}.vue`];
        if (!pageResolver) {
            console.error(`Page component "./Pages/${name}.vue" not found.`);
        }

        return pageResolver()
            .then((module) => {
                if (module.default.layout !== undefined) {
                    return module;
                }

                if (
                    name.startsWith('Admin/') ||
                    name.startsWith('SuperAdmin/') ||
                    name.startsWith('Org/') ||
                    name.startsWith('AI/') ||
                    name.startsWith('Auth/') ||
                    name.startsWith('Notifications/') ||
                    name.startsWith('Printer/') ||
                    name === 'Home' ||
                    name === 'Error' ||
                    name === 'BranchUnavailable'
                ) {
                    // Handled inside page components with explicit layout or standalone
                    return module;
                }

                if (
                    name.startsWith('Pos/') ||
                    name.startsWith('Items/') ||
                    name.startsWith('Categories/') ||
                    name.startsWith('Customers/') ||
                    name.startsWith('Sales/') ||
                    name.startsWith('Purchases/') ||
                    name.startsWith('Vendors/') ||
                    name.startsWith('Inventory/') ||
                    name.startsWith('Reports/') ||
                    name.startsWith('Settings/') ||
                    name.startsWith('Discounts/') ||
                    name.startsWith('GroupAddresses/') ||
                    name.startsWith('Users/') ||
                    name.startsWith('Roles/')
                ) {
                    module.default.layout = PosLayout;
                } else {
                    module.default.layout = Layout;
                }
                return module;
            })
            .catch((err) => {
                console.error(`Failed to load page module "${name}":`, err);
                if (typeof window !== 'undefined' && !sessionStorage.getItem('retry_chunk_' + name)) {
                    sessionStorage.setItem('retry_chunk_' + name, '1');
                    window.location.reload();
                }
                throw err;
            });
    },
    setup({ el, App, props, plugin }) {
        // Initialize theme color and Ziggy defaults
        applyTheme(props.initialPage.props.theme_color, props.initialPage.props.custom_primary_hex, props.initialPage.props.custom_secondary_hex);

        const initialSlug = getActiveBranchSlug(props.initialPage.props);
        syncZiggyDefaults(initialSlug);

        const app = createApp({ render: () => h(App, props) });
        app.use(plugin)
            .use(ZiggyVue)
            .use(Toast)
            .use(pinia);

        // Smart route wrapper to ensure 'pos.*' routes never throw due to missing branch parameter
        const rawRoute = app.config.globalProperties.route || (typeof window !== 'undefined' ? window.route : null);
        if (rawRoute) {
            const smartRoute = (name, params, absolute, config) => {
                if (typeof name === 'string' && name.startsWith('pos.')) {
                    const currentSlug = getActiveBranchSlug(router.page?.props)
                        || window.Ziggy?.defaults?.branch
                        || getActiveBranchSlug(props.initialPage.props);
                    if (currentSlug) {
                        syncZiggyDefaults(currentSlug);
                    }

                    const ziggyConfig = config || (typeof window !== 'undefined' ? window.Ziggy : null);
                    const routeDef = ziggyConfig?.routes?.[name];
                    const routeParams = routeDef?.parameters || [];
                    const hasBranch = routeParams.includes('branch');
                    const nonBranchParams = routeParams.filter(p => p !== 'branch');

                    if (hasBranch) {
                        if (params === undefined || params === null) {
                            if (currentSlug) {
                                params = { branch: currentSlug };
                            }
                        } else if (Array.isArray(params)) {
                            if (params.length === 1 && nonBranchParams.length === 1) {
                                params = { branch: currentSlug, [nonBranchParams[0]]: params[0] };
                            }
                        } else if (typeof params === 'object') {
                            if (!params.branch && currentSlug) {
                                params = { branch: currentSlug, ...params };
                            }
                        } else if (typeof params !== 'object') {
                            if (nonBranchParams.length === 1) {
                                params = { branch: currentSlug, [nonBranchParams[0]]: params };
                            } else if (currentSlug) {
                                params = { branch: currentSlug, id: params };
                            }
                        }
                    }
                }
                return rawRoute(name, params, absolute, config);
            };

            app.config.globalProperties.route = smartRoute;
            app.provide('route', smartRoute);
            if (typeof window !== 'undefined') {
                window.route = smartRoute;
            }
        }

        const themeStore = useThemeStore();
        themeStore.loadTheme();

        app.config.globalProperties.$formatDate = formatDate;

        app.component('Head', Head)
            .component('Link', Link)
            .component('FlashMessages', FlashMessages)
            .component('font-awesome-icon', FontAwesomeIcon);

        app.mount(el);

        // ── Dismiss Pre-Login Splash Screen smoothly on app mount ──
        if (typeof document !== 'undefined') {
            const preSplash = document.getElementById('pre-login-splash');
            if (preSplash) {
                requestAnimationFrame(() => {
                    preSplash.classList.add('splash-fade-out');
                    setTimeout(() => {
                        if (preSplash.parentNode) {
                            preSplash.parentNode.removeChild(preSplash);
                        }
                    }, 400);
                });
            }
        }

        // ✅ Dynamically import AOS to split the chunk (Non-critical)
        import('aos').then((AOS) => {
            const aos = AOS.default || AOS;
            if (aos && aos.init) {
                aos.init({
                    duration: 1000,
                    once: true,
                });
            }
        }).catch(err => console.warn('AOS failed to load:', err));

        // ✅ Register PWA Service Worker for offline shell caching
        if (typeof window !== 'undefined' && 'serviceWorker' in navigator && process.env.NODE_ENV === 'production') {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => {
                    console.warn('ServiceWorker registration error:', err);
                });
            });
        }
    },
    progress: {
        delay: 250,
        color: '#ad14f4ff',
        includeCSS: true,
        showSpinner: false,
    },
});

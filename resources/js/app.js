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

function applyTheme(themeColor, customPrimaryHex, customSecondaryHex) {
    if (typeof document === 'undefined') return;

    const rootStyle = document.documentElement.style;

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

// Listen to Inertia navigation events to update Ziggy defaults & theme_color
router.on('navigate', (event) => {
    const props = event.detail.page.props;
    const branchSlug = props.current_branch?.slug;
    applyTheme(props.theme_color, props.custom_primary_hex, props.custom_secondary_hex);

    if (typeof window !== 'undefined' && window.Ziggy) {
        window.Ziggy.defaults = {
            ...(window.Ziggy.defaults || {}),
            branch: branchSlug || window.Ziggy.defaults?.branch || 'felix-enterprise',
        };
    }
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

                if (name.startsWith('Admin/') || name.startsWith('SuperAdmin/') || name.startsWith('Auth/') || name === 'Home' || name === 'Error' || name === 'BranchUnavailable') {
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

        if (typeof window !== 'undefined' && window.Ziggy) {
            const initialSlug = props.initialPage.props.current_branch?.slug;
            window.Ziggy.defaults = {
                ...(window.Ziggy.defaults || {}),
                branch: initialSlug || 'felix-enterprise',
            };
        }

        const app = createApp({ render: () => h(App, props) });
        app.use(plugin)
            .use(ZiggyVue)
            .use(Toast)
            .use(pinia);

        const themeStore = useThemeStore();
        themeStore.loadTheme();

        app.config.globalProperties.$formatDate = formatDate;

        app.component('Head', Head)
            .component('Link', Link)
            .component('FlashMessages', FlashMessages)
            .component('font-awesome-icon', FontAwesomeIcon);

        app.mount(el);

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
    },
    progress: {
        delay: 250,
        color: '#ad14f4ff',
        includeCSS: true,
        showSpinner: false,
    },
});

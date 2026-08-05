import './bootstrap';
import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link, router } from '@inertiajs/vue3'
import Layout from './Layouts/Layout.vue';
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

// Listen to Inertia navigation events to update Ziggy defaults for branch slug
router.on('navigate', (event) => {
    const branchSlug = event.detail.page.props.current_branch?.slug;
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
        const page = pages[`./Pages/${name}.vue`]();

        return page.then((module) => {
            if (!name.startsWith('Admin/')
                && !name.startsWith('Auth/')
                && !name.startsWith('SuperAdmin/')
                && !name.startsWith('Pos/')
                && name !== 'Error'
                && name !== 'BranchUnavailable'
            ) {
                module.default.layout = module.default.layout || Layout;
            }
            return module;
        });
    },
    setup({ el, App, props, plugin }) {
        // Initialize Ziggy defaults for branch slug
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

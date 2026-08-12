import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vuePlugin from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        vuePlugin(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        chunkSizeWarningLimit: 2000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('@fortawesome') || id.includes('fontawesome')) {
                            return 'fontawesome';
                        }
                        if (id.includes('vue') || id.includes('@inertiajs') || id.includes('pinia')) {
                            return 'vue-vendor';
                        }
                        if (id.includes('chart.js') || id.includes('chartjs')) {
                            return 'chart-vendor';
                        }
                        if (id.includes('jspdf') || id.includes('html2canvas')) {
                            return 'pdf-vendor';
                        }
                        return 'vendor';
                    }
                },
            },
        },
    },
});

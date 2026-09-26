import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/whiteboard.jsx'],
            refresh: true,
        }),
        tailwindcss(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.js',
            outDir: 'public',
            injectRegister: null,
            manifest: false,
            includeAssets: ['favicon.svg', 'manifest.webmanifest', 'icons/*.png', 'offline.html'],
            injectManifest: {
                globPatterns: [
                    'build/assets/app-*.{js,css}',
                    'fonts/*.woff2',
                    'icons/*.png',
                    'favicon.svg',
                    'manifest.webmanifest',
                    'offline.html',
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

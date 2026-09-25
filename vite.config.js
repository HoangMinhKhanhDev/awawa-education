import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/whiteboard.jsx'],
            refresh: true,
            fonts: [
                bunny('Be Vietnam Pro', {
                    weights: [400, 500, 600, 700],
                    optimizedFallbacks: false,
                }),
            ],
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
                    'build/assets/fonts-*.css',
                    'build/assets/*.woff2',
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

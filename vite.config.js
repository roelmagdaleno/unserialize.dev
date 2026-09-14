import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/code.css',
                'resources/js/app.js',
                'resources/js/clipboard.js',
                'resources/js/highlight.js'
            ],
            refresh: true,
            detectTls: 'unserialize.test',
        }),
    ],
    server: {
        cors: {
            origin: [
                // Supports: SCHEME://DOMAIN.laravel[:PORT]
                /^https?:\/\/.*\.test(:\d+)?$/,
            ],
        },
    },
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/echo.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            external: ['laravel-echo', 'pusher-js'],
            onwarn(warning, warn) {
                // Suppress warnings for unresolved external modules
                if (warning.code === 'UNRESOLVED_IMPORT' && 
                    (warning.source === 'laravel-echo' || warning.source === 'pusher-js')) {
                    return;
                }
                warn(warning);
            },
        },
    },
});

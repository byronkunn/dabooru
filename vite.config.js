import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    build: {
        target: 'es2020',
        minify: 'esbuild',
        cssMinify: 'esbuild',
        rollupOptions: {
            output: {
                // Change the URL namespace so edge caches don't reuse old
                // four-hour entries after the production cache policy changes.
                assetFileNames: 'assets/[name]-[hash]-v2[extname]',
                entryFileNames: 'assets/[name]-[hash]-v2.js',
                chunkFileNames: 'assets/[name]-[hash]-v2.js',
            },
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwind from '@tailwindcss/vite';
import statamic from '@statamic/cms/vite-plugin'; 

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/statamic-tabs.js',
                'resources/css/statamic-tabs.css',
            ],
            hotFile: 'dist/vite.hot',
            publicDirectory: 'dist',
        }),
        statamic(),
        tailwind(),
    ],
});
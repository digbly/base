import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/views/app.tsx'],
            refresh: ['resources/views/**/*.blade.php'],
            publicDirectory: '../../public',
            buildDirectory: 'themes/default',
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': `${import.meta.dirname}/resources/views`,
        },
    },
    build: {
        emptyOutDir: true,
    },
});

import path from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT || 5173),
        strictPort: true,
        origin: process.env.VITE_DEV_SERVER_URL || undefined,
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
            port: Number(process.env.VITE_HMR_PORT || process.env.VITE_HOST_PORT || process.env.VITE_PORT || 5173),
            clientPort: Number(process.env.VITE_HMR_PORT || process.env.VITE_HOST_PORT || process.env.VITE_PORT || 5173),
        },
        watch: {
            // Docker Desktop (Windows/macOS) bind mounts often miss inotify events
            usePolling: process.env.CHOKIDAR_USEPOLLING === 'true' || process.env.VITE_USE_POLLING === 'true',
            interval: 300,
        },
    },
});

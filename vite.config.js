import path from 'node:path';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL || process.env.APP_URL || 'http://localhost:8000';
    const viteOrigin =
        process.env.VITE_DEV_SERVER_URL ||
        `http://localhost:${process.env.VITE_HOST_PORT || process.env.VITE_PORT || 5173}`;

    const appOrigin = (() => {
        try {
            return new URL(appUrl).origin;
        } catch {
            return 'http://localhost:8000';
        }
    })();

    return {
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
            // Absolute URLs in the @vite directive (browser loads modules from :5173)
            origin: viteOrigin,
            // Page is served from APP_URL (:8000) — allow that origin to fetch Vite modules
            cors: {
                origin: [
                    appOrigin,
                    'http://localhost:8000',
                    'http://127.0.0.1:8000',
                    'http://localhost:5173',
                    'http://127.0.0.1:5173',
                ],
                credentials: true,
            },
            hmr: {
                host: process.env.VITE_HMR_HOST || 'localhost',
                port: Number(
                    process.env.VITE_HMR_PORT ||
                        process.env.VITE_HOST_PORT ||
                        process.env.VITE_PORT ||
                        5173,
                ),
                clientPort: Number(
                    process.env.VITE_HMR_PORT ||
                        process.env.VITE_HOST_PORT ||
                        process.env.VITE_PORT ||
                        5173,
                ),
            },
            watch: {
                // Docker Desktop (Windows/macOS) bind mounts often miss inotify events
                usePolling:
                    process.env.CHOKIDAR_USEPOLLING === 'true' ||
                    process.env.VITE_USE_POLLING === 'true',
                interval: 300,
            },
        },
    };
});

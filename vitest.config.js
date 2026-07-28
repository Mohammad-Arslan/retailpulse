import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    // Mirrors vite.config.js's JSX handling (automatic runtime) so component
    // modules (.jsx) transform the same way under test as they do in the real
    // build — without this, JSX compiles to bare React.createElement() calls
    // with no auto-import, and any test importing a .jsx component fails with
    // "React is not defined".
    esbuild: {
        jsx: 'automatic',
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.js'],
    },
});

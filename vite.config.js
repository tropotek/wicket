import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const host = env.VITE_HOST || 'localhost';
    const port = parseInt(env.VITE_PORT || '5777', 10);

    return {
        plugins: [
            laravel({
                input: ['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/menu-editor.js', 'resources/js/editor.js'],
                refresh: true,
            }),
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            origin: `http://${host}:${port}`,
            cors: {
                origin: env.APP_URL || `http://${host}:${port}`,
            },
            hmr: { host, port },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/*
 * Preline's per-plugin builds (preline/plugins/*) only register window.HS* globals and export
 * nothing, but the package marks them side-effect free, so the bundler would drop them.
 * Keep them (docs/07 D-12).
 */
const prelinePlugins = {
    name: 'woodex:preline-plugins',
    enforce: 'pre',
    async resolveId(source, importer, options) {
        if (!source.startsWith('preline/plugins/')) {
            return null;
        }

        const resolved = await this.resolve(source, importer, { ...options, skipSelf: true });

        return resolved ? { ...resolved, moduleSideEffects: true } : null;
    },
};

/*
 * Assets are built locally or in GitHub Actions and shipped in public/build.
 * Hostinger never runs Node (docs/07 D-10).
 */
export default defineConfig({
    plugins: [
        prelinePlugins,
        laravel({
            input: [
                'resources/css/admin.css',
                'resources/js/admin.js',
                'resources/css/site.css',
                'resources/js/site.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

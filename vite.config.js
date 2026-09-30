import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { minify } from 'vite'

/**
 * Builds the dashboard assets into dist/ with content-hashed names and a
 * manifest.json. The package serves them itself (AssetController), so
 * applications never publish assets.
 */
export default defineConfig({
    plugins: [
        tailwindcss(),
        {
            // theme-init.js is inlined in <head> (with a CSP nonce) to avoid a
            // flash of the wrong theme, so it is copied as-is (minified).
            name: 'auditor-theme-init',
            async closeBundle() {
                const source = readFileSync('resources/js/theme-init.js', 'utf8')
                const result = await minify('theme-init.js', source)
                mkdirSync('dist', { recursive: true })
                writeFileSync('dist/theme-init.js', result.code.trim() + '\n')
            },
        },
    ],
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: 'manifest.json',
        target: 'es2020',
        rolldownOptions: {
            input: {
                auditor: 'resources/js/auditor.js',
                styles: 'resources/css/auditor.css',
            },
            output: {
                entryFileNames: '[name]-[hash].js',
                chunkFileNames: '[name]-[hash].js',
                assetFileNames: '[name]-[hash][extname]',
            },
        },
    },
})

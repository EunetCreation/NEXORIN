import { defineConfig } from 'vite';

export default defineConfig({
    publicDir: false,
    build: {
        outDir: 'public/js',
        emptyOutDir: false,
        sourcemap: false,
        minify: true,
        lib: {
            entry: 'resources/js/nexorin-sld-editor.js',
            name: 'NexorinSldEditor',
            formats: ['iife'],
            fileName: () => 'nexorin-sld-editor.js',
        },
    },
});

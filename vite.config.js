import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Permitir el host del túnel
        allowedHosts: [
            'hdhtph-ip-206-1-141-125.tunnelmole.net', // Host específico
            '.tunnelmole.net' // O cualquier subdominio de tunnelmole
        ],
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
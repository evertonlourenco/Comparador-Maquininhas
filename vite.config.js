import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // O comparador e entrada propria: Alpine e os dois motores so
            // pesam na pagina que os usa (etapa 07).
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/comparador.js',
            ],
            refresh: true,
            // Manual de marca do Maquina Certa (etapa 14). O plugin baixa e
            // serve local: nenhuma requisicao a terceiro na visita, que e o
            // que a regra 9 pede da pagina publica — o <link> do Google Fonts
            // que o manual sugere nao entra.
            fonts: [
                bunny('Saira', {
                    weights: [400, 600, 700],
                }),
                bunny('Figtree', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

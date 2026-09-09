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
            // Direcao "Boletim" (etapa 06). O plugin baixa e serve local:
            // nenhuma requisicao a terceiro na visita, que e o que a regra 9
            // pede da pagina publica.
            fonts: [
                bunny('Newsreader', {
                    weights: [400, 500, 600],
                }),
                bunny('IBM Plex Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('IBM Plex Mono', {
                    weights: [400, 500],
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

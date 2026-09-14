/**
 * Etapa 12: escreve public/build/.htaccess depois do `vite build`.
 *
 * O `emptyOutDir` do Vite apaga o conteudo inteiro de public/build/ a cada
 * build, entao um .htaccess colocado ali a mao desapareceria no proximo
 * `npm run build` sem aviso nenhum. Rodar isto DEPOIS do `vite build` (ver o
 * script "build" do package.json) garante que o arquivo sempre volta.
 *
 * A primeira tentativa amarrava o cache no .htaccess de public/ (que o Vite
 * nao toca) via variavel de ambiente do mod_rewrite (`E=CACHE_IMUTAVEL:1`)
 * lida pelo mod_headers (`env=CACHE_IMUTAVEL`). Funciona no Apache real, mas
 * o LiteSpeed da Hostinger nao repassou a variavel entre os dois modulos —
 * conferido em producao, o cache-control saia sempre a regra generica de 30
 * dias, nunca a immutable. Este arquivo, sem nenhuma condicao, e mais simples
 * e nao depende dessa comunicacao entre modulos.
 */
import { writeFileSync } from 'node:fs';

const conteudo = `# Etapa 12: tudo aqui dentro e gerado pelo Vite com hash no nome do arquivo
# (ex.: app-C3x9F2kq.css). Conteudo novo sempre nasce com nome novo, entao
# cache "para sempre" e seguro — nunca vai servir um arquivo trocado com o
# nome antigo. Gerado por scripts/gera-htaccess-do-build.mjs a cada build;
# nao editar a mao, o proximo \`npm run build\` sobrescreve.
<IfModule mod_headers.c>
    Header set Cache-Control "public, max-age=31536000, immutable"
</IfModule>
`;

writeFileSync(new URL('../public/build/.htaccess', import.meta.url), conteudo);
console.log('public/build/.htaccess gerado.');

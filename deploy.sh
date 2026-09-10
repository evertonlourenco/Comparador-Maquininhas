#!/usr/bin/env bash
#
# deploy.sh — ciclo de atualizacao do Maquina Certa em producao.
#
# Idempotente: rodar duas vezes seguidas sem nada novo no repositorio termina
# em sucesso e nao muda nada. Rodar depois de um `git push` traz o codigo novo,
# as dependencias, as migrations e os caches.
#
#   ssh comparador '~/comparador/deploy.sh'
#
# O QUE ESTE SCRIPT NAO FAZ, DE PROPOSITO:
#
#   `php artisan db:seed`. Os seeders da etapa 04 usam updateOrCreate, o que os
#   torna seguros de reexecutar no sentido de nao duplicar — mas "seguro" ali
#   significa SOBRESCREVER. Uma taxa corrigida no painel voltaria ao valor do
#   arquivo, em silencio. Carga inicial e coisa de uma vez so, feita a mao.
#
#   `npm run build`. O servidor nao tem Node, e nao precisa ter: o public/build
#   e versionado (ver .gitignore). Rode `npm run build` no Mac e commite o
#   resultado junto da mudanca de front-end — este script confere se o bundle
#   chegou inteiro, mas nao tem como saber se voce esqueceu de recompilar.
#
set -euo pipefail

PHP="${PHP_BIN:-/opt/alt/php84/usr/bin/php}"
COMPOSER="${COMPOSER_BIN:-/usr/local/bin/composer}"

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$RAIZ"

passo() { printf '\n[%s] ==> %s\n' "$(date '+%H:%M:%S')" "$1"; }
erro()  { printf '\n[%s] ERRO: %s\n' "$(date '+%H:%M:%S')" "$1" >&2; exit 1; }

# ---------------------------------------------------------------- guardas ---
# Tudo que pode abortar o deploy roda ANTES de o site sair do ar.

[ -f .env ]            || erro "sem .env em $RAIZ. Este nao parece o diretorio de producao."
[ -f artisan ]         || erro "sem artisan em $RAIZ."
[ -x "$PHP" ]          || erro "PHP nao encontrado em $PHP. O /usr/bin/php da Hostinger nao serve: tem proc_open e symlink desabilitados."
[ -x "$COMPOSER" ]     || erro "Composer nao encontrado em $COMPOSER."

if [ -n "$(git status --porcelain)" ]; then
    git status --short
    erro "ha alteracoes locais nao commitadas no servidor. Producao nao e lugar de editar arquivo: resolva acima antes de seguir."
fi

RAMO="$(git rev-parse --abbrev-ref HEAD)"
[ "$RAMO" = "main" ] || erro "o repositorio esta no ramo '$RAMO', nao em 'main'."

ANTES="$(git rev-parse HEAD)"

# ------------------------------------------------------------ manutencao ---
# public/index.php checa storage/framework/maintenance.php ANTES de carregar o
# autoload do Composer, entao o aviso continua de pe mesmo enquanto o vendor/
# esta sendo reescrito. O trap garante que o site volta em qualquer saida —
# inclusive erro, Ctrl-C ou falha de rede no meio do composer.
MANUTENCAO=0
levantar() {
    if [ "$MANUTENCAO" = "1" ]; then
        "$PHP" artisan up >/dev/null 2>&1 || true
        MANUTENCAO=0
        printf '\n[%s] site no ar novamente.\n' "$(date '+%H:%M:%S')"
    fi
}
trap levantar EXIT INT TERM

passo "tirando o site do ar"
"$PHP" artisan down --retry=60 >/dev/null
MANUTENCAO=1

# ---------------------------------------------------------------- codigo ---
passo "buscando o codigo novo"
git fetch --quiet origin main
# --ff-only: se a historia divergiu, aborta em vez de criar merge no servidor.
git merge --ff-only origin/main

DEPOIS="$(git rev-parse HEAD)"
if [ "$ANTES" = "$DEPOIS" ]; then
    echo "    ja estava em $(git rev-parse --short HEAD) — nada novo."
else
    echo "    $(git rev-parse --short "$ANTES") -> $(git rev-parse --short "$DEPOIS")"
    git --no-pager log --oneline "$ANTES..$DEPOIS" | sed 's/^/    /'
fi

passo "dependencias do PHP (sem as de desenvolvimento)"
"$PHP" -d memory_limit=-1 "$COMPOSER" install \
    --no-dev --optimize-autoloader --no-interaction --no-progress

# ----------------------------------------------------------------- build ---
# Pega bundle ausente ou pela metade. NAO pega bundle desatualizado: para isso
# nao ha como o servidor saber, e a disciplina e commitar o build junto.
passo "conferindo o bundle do Vite"
"$PHP" <<'PHPCHECK'
<?php
$manifesto = 'public/build/manifest.json';
if (! is_file($manifesto)) {
    fwrite(STDERR, "    manifest.json ausente. Rode `npm run build` no Mac e commite o public/build.\n");
    exit(1);
}
$entradas = json_decode(file_get_contents($manifesto), true);
if (! is_array($entradas) || $entradas === []) {
    fwrite(STDERR, "    manifest.json vazio ou invalido.\n");
    exit(1);
}
$faltando = [];
foreach ($entradas as $entrada) {
    foreach (array_merge([$entrada['file'] ?? null], $entrada['css'] ?? []) as $arquivo) {
        if ($arquivo !== null && ! is_file('public/build/'.$arquivo)) {
            $faltando[] = $arquivo;
        }
    }
}
if ($faltando !== []) {
    fwrite(STDERR, "    o manifesto aponta para arquivos que nao existem:\n");
    foreach ($faltando as $arquivo) {
        fwrite(STDERR, "      - $arquivo\n");
    }
    exit(1);
}
printf("    %d entradas no manifesto, todos os arquivos presentes.\n", count($entradas));
PHPCHECK

passo "link do storage publico"
# --force recria se ja existir, o que torna o passo idempotente.
"$PHP" artisan storage:link --force

# ----------------------------------------------------------------- banco ---
passo "migrations"
"$PHP" artisan migrate --force

# ---------------------------------------------------------------- caches ---
# optimize:clear antes de optimize: o optimize sozinho sobrescreve os caches
# que ele mesmo gera, mas nao remove os que deixaram de existir.
passo "reconstruindo os caches"
"$PHP" artisan optimize:clear
"$PHP" artisan optimize
"$PHP" artisan filament:optimize

# ------------------------------------------------------------- regra 9 ----
# O JSON estatico que o comparador consome no navegador. Sem --rascunhos, so
# entra taxa publicada (regra 10).
passo "gerando o JSON do comparador (regra 9)"
"$PHP" artisan comparador:gerar-json

# ------------------------------------------------------------------ fim ---
levantar
trap - EXIT INT TERM

passo "deploy concluido"
echo "    versao no ar: $(git rev-parse --short HEAD) — $(git log -1 --pretty=%s)"

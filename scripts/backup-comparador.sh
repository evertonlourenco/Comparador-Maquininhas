#!/usr/bin/env bash
#
# backup-comparador.sh — backup diario do Maquina Certa.
#
# Guarda tres coisas, porque perder qualquer uma delas custa caro:
#
#   banco-*.sql.gz     mysqldump comprimido: as taxas, os planos, os cupons,
#                      as filas de revisao da etapa 10 e os usuarios do painel.
#   arquivos-*.tar.gz  storage/app: os logos de marca e fotos de equipamento
#                      convertidos para WebP (disco `public`) e os anexos de
#                      proposta dos lojistas (disco `local`, privado).
#   env-*              o .env. NAO e zelo excessivo: o segredo do 2FA e os
#                      codigos de recuperacao ficam em users.* com cast
#                      `encrypted`, e quem os decifra e a APP_KEY. Restaurar o
#                      banco sem a APP_KEY original devolve um painel com 2FA
#                      obrigatorio e nenhum segundo fator legivel — e o app nao
#                      envia e-mail, entao nao ha "esqueci minha senha".
#
# As credenciais do MySQL NAO moram aqui. Elas ficam em ~/.comparador-backup.cnf
# com permissao 600, e este script recusa rodar se a permissao estiver frouxa.
#
# Chamada (o cron do hPanel usa exatamente esta linha):
#   /bin/bash /home/u835756808/comparador/scripts/backup-comparador.sh
#
set -euo pipefail

# Tudo que este script cria nasce 600 (arquivo) / 700 (diretorio). O dump
# carrega o banco inteiro e o env-* carrega a APP_KEY: num servidor
# compartilhado, nenhum dos dois tem por que ser legivel por outro usuario.
umask 077

CONFIG="${BACKUP_CNF:-$HOME/.comparador-backup.cnf}"
APP="${APP_DIR:-$HOME/comparador}"
DESTINO="${BACKUP_DIR:-$HOME/backups/comparador}"
DIAS="${BACKUP_DIAS:-14}"

mkdir -p "$DESTINO"
chmod 700 "$DESTINO"
LOG="$DESTINO/backup.log"

registrar() { printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1" | tee -a "$LOG"; }
falhar()    { printf '%s  FALHOU: %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1" | tee -a "$LOG" >&2; exit 1; }

# ---------------------------------------------------------------- guardas ---
[ -f "$CONFIG" ] || falhar "sem $CONFIG. Crie o arquivo com as credenciais e ponha em 600."

PERM="$(stat -c '%a' "$CONFIG" 2>/dev/null || stat -f '%Lp' "$CONFIG")"
[ "$PERM" = "600" ] || falhar "$CONFIG esta com permissao $PERM. Esperado 600. Rode: chmod 600 $CONFIG"

[ -f "$APP/.env" ] || falhar "sem $APP/.env."

BANCO="$(sed -n 's/^DB_DATABASE=//p' "$APP/.env" | head -1 | tr -d '"'\''' | tr -d '[:space:]')"
[ -n "$BANCO" ] || falhar "nao consegui ler DB_DATABASE de $APP/.env."

command -v mysqldump >/dev/null || falhar "mysqldump nao encontrado."

CARIMBO="$(date '+%Y-%m-%d_%H%M%S')"
DUMP="$DESTINO/banco-$CARIMBO.sql.gz"
ARQS="$DESTINO/arquivos-$CARIMBO.tar.gz"
ENVB="$DESTINO/env-$CARIMBO"

registrar "--- inicio ($BANCO) ---"

# ------------------------------------------------------------------ banco ---
# --single-transaction: dump consistente sem travar tabela (InnoDB).
# --no-tablespaces: hospedagem compartilhada nao da o privilegio PROCESS, e sem
#   esta flag o mysqldump do MySQL 8 aborta por causa disso.
# O `set -o pipefail` la em cima faz o gzip nao mascarar uma falha do mysqldump.
mysqldump --defaults-extra-file="$CONFIG" \
    --single-transaction --quick --no-tablespaces \
    --default-character-set=utf8mb4 \
    "$BANCO" | gzip -9 > "$DUMP"

# Verificar todo dia e o que separa backup de arquivo com nome de backup.
gzip -t "$DUMP" 2>/dev/null || falhar "o gzip de $DUMP esta corrompido."

# O mysqldump escreve esta linha por ultimo. Sem ela, o dump foi interrompido
# no meio — e um .sql.gz truncado abre normalmente, so restaura pela metade.
gunzip -c "$DUMP" | tail -5 | grep -q 'Dump completed' \
    || falhar "$DUMP nao termina com 'Dump completed': dump truncado."

TABELAS="$(gunzip -c "$DUMP" | grep -c '^CREATE TABLE' || true)"
[ "$TABELAS" -gt 0 ] || falhar "$DUMP nao tem nenhum CREATE TABLE."

registrar "banco:    $(basename "$DUMP") ($(du -h "$DUMP" | cut -f1), $TABELAS tabelas)"

# --------------------------------------------------------------- arquivos ---
# storage/app cobre os dois discos: `public` (logos e fotos em WebP, servidos
# pelo symlink public/storage) e `local` (anexos de proposta, privados).
if [ -d "$APP/storage/app" ]; then
    tar -czf "$ARQS" -C "$APP" storage/app
    gzip -t "$ARQS" 2>/dev/null || falhar "o tar.gz de $ARQS esta corrompido."
    ITENS="$(tar -tzf "$ARQS" | wc -l | tr -d ' ')"
    registrar "arquivos: $(basename "$ARQS") ($(du -h "$ARQS" | cut -f1), $ITENS itens)"
else
    registrar "arquivos: storage/app nao existe — nada a guardar."
fi

# -------------------------------------------------------------------- env ---
cp "$APP/.env" "$ENVB"
chmod 600 "$ENVB"
registrar "env:      $(basename "$ENVB") (600)"

# --------------------------------------------------------------- rotacao ---
# -mtime +N e "modificado ha mais de N dias". Com 14, o mais antigo mantido tem
# no maximo 14 dias — duas semanas de historico.
#
# Sem substituicao de processo (`< <(...)`) aqui, de proposito: ela depende de
# /dev/fd, que nao existe no ambiente da Hostinger. A primeira versao deste
# script usava, e falhou em producao com "/dev/fd/63: No such file or
# directory" — gravando o backup e morrendo antes de rotacionar. Pipe simples
# funciona, e `find -delete` dispensa o laco.
REMOVIDOS=0
for PADRAO in 'banco-*.sql.gz' 'arquivos-*.tar.gz' 'env-*'; do
    N="$(find "$DESTINO" -maxdepth 1 -type f -name "$PADRAO" -mtime +"$DIAS" | wc -l | tr -d ' ')"
    if [ "$N" -gt 0 ]; then
        find "$DESTINO" -maxdepth 1 -type f -name "$PADRAO" -mtime +"$DIAS" -delete
        REMOVIDOS=$((REMOVIDOS + N))
    fi
done

MANTIDOS="$(find "$DESTINO" -maxdepth 1 -type f -name 'banco-*.sql.gz' | wc -l | tr -d ' ')"
registrar "rotacao:  $REMOVIDOS removidos (> $DIAS dias), $MANTIDOS dumps mantidos"
registrar "--- fim, ok ---"

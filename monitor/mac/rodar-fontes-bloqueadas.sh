#!/bin/bash
# Roda, a partir do Mac do Everton, as fontes marcadas com roda_no_mac=true
# em fontes.json — as que o GitHub Actions não consegue ler porque o IP de
# datacenter toma bloqueio de Cloudflare/Akamai. Cron/launchd chamam este
# script; ele só existe porque cron não tem como ter um IP residencial.
#
# Configurar no crontab do Mac (`crontab -e`), por exemplo todo dia às 8h:
#   0 8 * * * /caminho/completo/para/mac/rodar-fontes-bloqueadas.sh >> ~/Library/Logs/monitor-maquina-certa.log 2>&1
#
# As variáveis de ambiente (MONITOR_API_URL, MONITOR_API_TOKEN,
# TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, GEMINI_API_KEY) precisam estar
# exportadas antes — cron não lê o .env sozinho. Uma forma simples: colar um
# `export ...` para cada uma no topo deste arquivo (nunca commitar com o
# valor preenchido) ou apontar para um arquivo de secrets fora do repositório:
#   set -a; source "$HOME/.config/monitor-maquina-certa.env"; set +a

set -euo pipefail

DIR_DO_SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DIR_DO_SCRIPT"

node bin/monitorar.mjs --somente-mac

# O estado commitado por esta execução precisa ser enviado para o
# repositório, senão a próxima execução (aqui ou no GitHub Actions) perde a
# memória do que já foi visto.
git add estado
git diff --cached --quiet || git commit -m "estado: coleta no Mac (fontes bloqueadas) $(date -u +%Y-%m-%dT%H:%M:%SZ)"
git push

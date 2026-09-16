# Monitor de mudanças — Máquina Certa

Etapa 13 do [Comparador de Maquininhas](https://github.com/evertonlourenco/Comparador-Maquininhas)
— vive nesta pasta (`monitor/`) do mesmo repositório, como um projeto Node.js
**separado de propósito** do app Laravel (`package.json` próprio, sem
misturar dependência com o PHP), mas sem repositório do GitHub à parte. Este
código não alimenta o site nem o admin: ele só avisa no Telegram quando uma
fonte externa (tabela de taxas, página de equipamento/cupom, contrato de
credenciamento em PDF) muda de conteúdo. **Nenhum dado publicado no site vem
daqui.** Toda mudança detectada vira uma linha pendente em
`deteccoes_de_mudanca` no admin (Filament → "Monitor de mudanças"), e só
entra no ar se um humano aprovar — regra 10 do produto, na forma mais forte.

Os workflows do GitHub Actions ficam em `.github/workflows/monitor-*.yml`,
na raiz do repositório (é onde o GitHub exige que fiquem), mas todos rodam
com `working-directory: monitor` — o código em si é só o desta pasta.

## Como funciona

Um monitor genérico, não um parser por marca:

1. **Coleta** a URL de uma fonte (`src/coletor.mjs`) — fetch simples para
   HTML estático e PDF, Playwright quando a página precisa de JavaScript
   para renderizar o conteúdo.
2. **Normaliza** o texto visível (`src/normalizar.mjs`), removendo datas,
   tokens de sessão e contadores que mudam sozinhos sem significar nada.
3. **Gera hash** (SHA-256) do texto normalizado e compara com o hash salvo
   da coleta anterior.
4. **Se mudou de verdade**, calcula um diff de linhas (`src/diff.mjs`),
   manda só o trecho alterado para a API gratuita do Gemini resumir em
   português (`src/gemini.mjs`), registra a detecção no admin
   (`POST /api/monitor/deteccoes`) e avisa no Telegram com o resumo e o
   link direto para a tela de edição da marca.
5. **Se não conseguir ler a fonte**, registra uma falha e avisa no
   Telegram do mesmo jeito — **falha nunca é silenciosa**.

### Onde mora o estado entre execuções

O GitHub Actions roda em runners efêmeros: nada sobrevive de uma execução
para a próxima, a menos que seja commitado. Por isso o hash e o texto
normalizado de cada fonte ficam em `estado/<fonte_id>.json`, **versionados
neste repositório** — cada workflow commita esse diretório de volta ao
final da execução. Efeito colateral bom: `git log -- estado/` vira um
histórico de quando cada página realmente mudou.

Isso também significa que este repositório **não expõe nem lê configuração
nenhuma do admin Laravel** — só chama dois endpoints, ambos protegidos por
token fixo (`MONITOR_API_TOKEN`, o mesmo valor nos dois lados):

| Endpoint | Uso |
|---|---|
| `POST /api/monitor/deteccoes` | Registra uma mudança ou uma falha |
| `GET /api/monitor/resumo-semanal` | Os dois números do resumo semanal |

### `fontes.json` — o que é monitorado

Lista versionada de URLs, cada uma com `id` (chave estável, usada tanto no
nome do arquivo de estado quanto na detecção registrada no admin),
`categoria`, frequência implícita pela categoria (ver workflows), e as
flags `requer_navegador`, `tipo_conteudo` (`html`/`pdf`), `roda_no_mac` e
`ativo`. Uma entrada com `ativo: false` (ou `url: null`) fica no arquivo
como lembrete de pendência, mas o monitor a ignora.

**Frequências** (cada uma é um workflow separado em `.github/workflows/`):

| Categoria | Frequência |
|---|---|
| `equipamento_cupom` | Diária |
| `tabela_taxas` | Duas vezes por semana (segunda e quinta) |
| `contrato_credenciamento` (PDF) | Semanal (segunda) |

## O que já está preenchido, e o que falta

**`tabela_taxas`** tem 4 fontes ativas, vindas das `url_fonte` reais já
cadastradas em `taxas_divulgadas` (etapa 04): PagBank, InfinitePay e as duas
páginas da SumUp (receba na hora / receba em D+1).

**`contrato_credenciamento`** tem as 4 fontes ativas — pesquisadas e
testadas com o próprio coletor deste projeto em 14/09/2026 (o PDF foi
baixado de verdade e teve texto extraído, não só uma busca): Cielo, Rede,
GetNet e Stone (que não usa mais o termo "contrato de credenciamento" —
a fonte cadastrada é o "Termos Gerais de Contratação" atual). O grau de
confiança de cada uma está no campo `observacao`; a da Cielo é a mais baixa
(o PDF encontrado tem só 3 páginas, contra 28 da Rede e 70 da GetNet) — vale
abrir uma vez para confirmar que é o corpo do contrato.

**`equipamento_cupom`** tem 4 fontes ativas — os próprios links de afiliado
do Everton (com cupom já aplicado), testados com o coletor em 14/09/2026:
Ton, PagBank, Mercado Pago e InfinitePay. Todos trazem preço/desconto real
via fetch simples.

**Achado no processo, sem ser sobre o monitor:** o Everton também passou
links de afiliado de Yelly, SidePay, TrincaPay e FacilityPay — mas essas
quatro marcas **ainda não existem** na tabela `marcas` do app (só eram
citadas como exemplo da regra 7 no `CLAUDE.md`). As URLs ficaram guardadas
em `fontes.json`, com `ativo: false`, pra não se perderem; cadastrar essas
marcas de verdade é trabalho de catálogo, fora do escopo deste monitor.

**Pendência que continua em aberto, sem inventar URL nenhuma** (regra 6 do
produto vale aqui também — campo vazio é honesto, URL errada é monitorar a
coisa errada):

- **Ton (`tabela_taxas`)**: pesquisado em 14/09/2026, sem solução. Não
  existe página pública com a tabela completa — o próprio Ton diz que a
  taxa é consultada dentro do app ("Minhas taxas e prazos"), depende do
  faturamento do mês e fica atrás de login. Resolver isso exigiria login
  automatizado na conta do Everton, fora do escopo deste monitor.

Para ativar qualquer fonte nova (ou uma das quatro pendentes acima): edite
`fontes.json`, preencha/confirme `url` e marque `ativo: true`, e faça
commit.

### Atenção: quatro marcas atrás de Cloudflare/Akamai

**Ton, PagBank, Stone e InfinitePay** provavelmente bloqueiam o IP de
datacenter do GitHub Actions. Por isso as fontes já cadastradas dessas
marcas apontam para páginas de tarifas/central de ajuda simples (HTML
estático, sem Playwright) — o tipo de página com menor chance de acionar um
desafio. `src/coletor.mjs` detecta sinais comuns de bloqueio (HTTP
403/429/503, texto de desafio da Cloudflare/Akamai) e registra isso como
**falha explícita**, nunca como "sem mudança".

**Se uma dessas fontes começar a falhar sempre por bloqueio**: edite a
entrada correspondente em `fontes.json`, mude `roda_no_mac` para `true`, e
rode-a a partir do Mac do Everton (IP residencial) via `mac/
rodar-fontes-bloqueadas.sh` — ver instruções no próprio script para
agendar no `crontab` do Mac. O mecanismo já está pronto neste repositório;
só falta confirmar, na prática, quais fontes precisam dele.

### `ignora_status_http` — quando o status HTTP mente

Achado em 16/09/2026: o `yelly-equipamento-cupom` começou a falhar todo dia
(`HTTP 404 ao buscar/renderizar`), mas o Everton confirmou abrindo a URL no
próprio navegador que a página funciona normalmente. A causa: a Yelly
redesenhou o checkout como um app React renderizado no cliente (por isso
`requer_navegador` também virou `true` nessa fonte — um fetch simples só
pega a casca `<div id="root">` vazia), **e** o CloudFront/S3 dela devolve
HTTP 404 (`x-amz-error-code: NoSuchKey` para `monetizando/index.html`) mesmo
servindo o HTML/JS reais, que rodam e mostram os planos certinho no
navegador — uma SPA mal configurada, sem status sobrescrito na resposta de
erro customizada do CloudFront. Confirmado batendo o coletor via Playwright
contra a mesma URL: texto renderizado idêntico ao que o navegador mostra,
status 404 nos dois.

Isso é do lado da Yelly, fora do nosso controle — não é uma fonte quebrada
de verdade. `fonte.ignora_status_http: true` avisa `src/coletor.mjs` (nas
duas vias, fetch simples e via navegador) para não tratar esse status como
falha automática; o sinal de bloqueio por conteúdo (`pareceBloqueado`, que
olha o texto da resposta, não o status) continua valendo como rede de
segurança contra desafio de verdade. **Não é o padrão** — só ligue para uma
fonte específica depois de confirmar, como aqui, que o navegador de verdade
mostra a página funcionando apesar do status.

## Configuração necessária (secrets)

No **mesmo** repositório do GitHub do app Laravel (Settings → Secrets and
variables → Actions — os workflows deste monitor, em
`.github/workflows/monitor-*.yml`, leem esses secrets normalmente mesmo
rodando só o código de `monitor/`):

| Secret no GitHub (nome real) | Env var que o script recebe | Onde conseguir o valor |
|---|---|---|
| `PORTAL_API_URL` | `MONITOR_API_URL` | `https://maquinacerta.com.br` (produção) |
| `COLETA_TOKEN` | `MONITOR_API_TOKEN` | Gerado com `php artisan tinker --execute="echo Str::random(48);"` na raiz do repositório — precisa ser **o mesmo valor** salvo em `MONITOR_API_TOKEN` no `.env` de produção do app |
| `TELEGRAM_BOT_TOKEN` | `TELEGRAM_BOT_TOKEN` | Criar um bot com [@BotFather](https://t.me/BotFather) no Telegram |
| `TELEGRAM_CHAT_ID` | `TELEGRAM_CHAT_ID` | Mandar uma mensagem para o bot e consultar `https://api.telegram.org/bot<TOKEN>/getUpdates` |
| `GEMINI_API_KEY` | `GEMINI_API_KEY` | [aistudio.google.com/apikey](https://aistudio.google.com/apikey) — camada gratuita |

**Achado em 16/09/2026: os dois primeiros secrets existem no GitHub com
nomes diferentes do que `src/config.mjs` espera** (`PORTAL_API_URL` e
`COLETA_TOKEN`, não `MONITOR_API_URL`/`MONITOR_API_TOKEN`) — provavelmente
um nome de rascunho da etapa 13 que nunca foi alinhado com o código. Os
quatro workflows (`monitor-diario`, `monitor-bissemanal`, `monitor-semanal`,
`monitor-resumo-semanal`) rodavam o `Roda o monitor`/`Envia o resumo
semanal` sem nenhuma das duas variáveis — `carregarConfig()` falhava direto
em `obrigatoria('MONITOR_API_URL')`, e a Action nunca chegava a checar fonte
nenhuma, desde que o secret foi criado (**não é fonte quebrada nenhuma que
causou isso** — a falha era 100% antes de qualquer coleta, achado só depois
que o Everton conseguiu ver o log completo logado no GitHub). O `env:` de
cada workflow foi corrigido para ler `secrets.PORTAL_API_URL` /
`secrets.COLETA_TOKEN` do lado direito, mantendo `MONITOR_API_URL`/
`MONITOR_API_TOKEN` como nome da variável que o script recebe — assim
`src/config.mjs` não precisou mudar. **Não renomeie os secrets no GitHub**
sem também atualizar os quatro `.yml`; o caminho mais simples pra manter
isso alinhado é sempre editar o lado direito do `env:`, nunca o nome da
variável à esquerda.

Nenhum destes eu (Claude) tenho ou posso gerar sozinho — precisam ser
criados/copiados pelo Everton.

## Rodando local

```bash
cd monitor
cp .env.example .env
# preencha o .env
export $(grep -v '^#' .env | xargs)

npm install
npx playwright install chromium   # só na primeira vez, se for testar requer_navegador

npm run monitorar -- --categoria=tabela_taxas
npm run monitorar -- --somente-mac
npm run resumo-semanal

npm test   # testes unitários de normalizar/hash/diff — não fazem rede
```

## Estrutura

Tudo abaixo é relativo a esta pasta (`monitor/`), exceto os workflows —
GitHub só reconhece `.github/workflows/` na raiz do repositório, por isso
eles moram lá (com `working-directory: monitor` em cada job).

```
fontes.json              lista de URLs monitoradas (ver acima)
estado/<fonte_id>.json   snapshot da última coleta de cada fonte (commitado pela Action)
src/
  config.mjs             lê e valida variáveis de ambiente
  fontes.mjs              carrega e filtra fontes.json
  estado.mjs              lê/grava estado/<id>.json
  coletor.mjs             fetch simples, Playwright ou PDF -> texto bruto
  normalizar.mjs           remove data/sessão/contador do texto
  hash.mjs                 SHA-256 do texto normalizado
  diff.mjs                 trecho de linhas alteradas, para o Gemini
  gemini.mjs                resumo em português via API gratuita do Gemini
  telegram.mjs               envio de alertas e as mensagens padrão
  apiLaravel.mjs              os dois endpoints do admin
  monitorar.mjs                orquestração por fonte
  resumoSemanal.mjs             o resumo semanal
bin/monitorar.mjs         CLI: --categoria=<...> [--somente-mac]
bin/resumo-semanal.mjs    CLI do resumo semanal
mac/rodar-fontes-bloqueadas.sh   wrapper para cron no Mac (fontes com roda_no_mac=true)
test/                     testes unitários (node --test) de normalizar/hash/diff

../.github/workflows/monitor-*.yml   os quatro agendamentos (diário, bissemanal, semanal, resumo)
```

## Decisões que valem registrar

- **Sem tabela de configuração no banco do Laravel.** A lista de fontes e o
  estado entre execuções vivem aqui, versionados — não há endpoint de
  leitura de config no admin. Isso mantém a superfície pública do Laravel
  pequena (só dois endpoints, ambos de escrita/consulta pontual) e este
  repositório autossuficiente.
- **Normalização é heurística, documentada como tal** em
  `src/normalizar.mjs` — a lista de padrões cresce quando um falso positivo
  aparecer na prática, não tenta prever tudo de antemão.
- **O link do Telegram vai para a tela de edição da *marca***, não para um
  campo específico — resolvido pelo Laravel (que conhece as próprias rotas
  do Filament), nunca construído aqui.
- **Falha do próprio monitor** (não conseguir falar com a API do admin, ou
  crashar por outro motivo) também tenta avisar no Telegram
  (`mensagemFalhaDoMonitor`), e o processo sempre sai com código diferente
  de zero nesse caso — a Action aparece vermelha como segundo sinal, para o
  caso de o Telegram também estar fora do ar.

# Construção do Comparador de Maquininhas

Plano de etapas do **Máquina Certa** (`maquinacerta.com.br`), portal comparador
de taxas de maquininhas de cartão do canal Monetizando Negócios.

Uma etapa por sessão. O `CLAUDE.md` registra o **estado e as regras** do que já
existe; este arquivo registra **o que falta e em que ordem**. Quando os dois
divergirem, o `CLAUDE.md` vence sobre o que está feito e este vence sobre o que
vem depois.

> **Onde vive o plano.** Em duas formas, e as duas sao mantidas juntas:
>
> - **Artifact "Construcao do Comparador de Maquininhas"** — https://claude.ai/code/artifact/bb649f45-0ffd-4261-9690-079dca5927ed
>   A versao formatada, com os prompts prontos de cada etapa. **Nao e arquivo
>   no disco**: e um Artifact publicado no Claude.ai, e foi por isso que uma
>   busca no sistema de arquivos nao o encontrou em 11/09/2026.
> - **Este arquivo** — a mesma ordem, versionada junto do codigo, para entrar
>   no diff e nao depender de memoria de sessao.
>
> Ao mudar a ordem das etapas, atualize os dois. Nao confundir com
> `~/Downloads/plano-portal-maquininhas.md` (14/06/2026), um antecessor de 6
> fases que descrevia outro projeto — XAMPP, Slim, coletor em Node/Playwright/
> Gemini — e esta superado, com uma excecao util registrada na etapa 14.
>
> **Reordenado em 11/09/2026.** Até a etapa 11 nada mudou. Da 12 em diante a
> ordem foi refeita a pedido do Everton, e três etapas novas entraram (imagens,
> painel de saúde e manual do administrador). O motivo da reordenação está em
> "A decisão que reorganizou o plano", no fim.

---

## Situação em 11/09/2026

O portal está **em produção e fechado ao público**. `SITE_EM_BREVE=true`
devolve 503 com `noindex` em toda rota pública; o `/admin` continua de pé, e é
por ele que o trabalho de dados acontece enquanto o site espera.

- 964 taxas divulgadas carregadas, **todas em rascunho** (regra 10)
- Nenhuma faixa reportada — depende da captação de relatos
- Nenhum cupom cadastrado
- Nenhuma imagem: sem logo de marca, sem foto de equipamento, sem bandeira
- Identidade visual própria **não existe** — o site usa o design system
  genérico da etapa 06

---

## Etapas concluídas

- [x] **01** — Ambiente local, Filament, Git e CLAUDE.md
- [x] **02** — Schema do banco
- [x] **03** — Painel admin no Filament
- [x] **04** — Carga dos dados reais
- [x] **05** — Motor de cálculo
- [x] **06** — Identidade visual e design system (provisório)
- [x] **07** — O comparador
- [x] **08** — Páginas de marca e listagem
- [x] **09** — Página de cupons e rastreamento de cliques
- [x] **10** — Metodologia, LGPD e captação de relatos
- [x] **11** — Deploy, SSH, backup e commits
- [x] **12** — Cloudflare, medição, SEO, segurança e performance
- [x] **13** — Monitor de mudanças (pasta `monitor/`, projeto Node à parte; ver CLAUDE.md)
- [x] **14** — Identidade visual e reforma da interface (Máquina Certa, 14/09/2026; ver CLAUDE.md)
- [x] **15** — Imagens: logos de marca, equipamentos e bandeiras (14/09/2026; ver CLAUDE.md)
- [x] **16** — Painel de saúde e observabilidade do administrador (15/09/2026; ver CLAUDE.md)
- [x] **18** — Manual do administrador (17/09/2026; ver CLAUDE.md)

---

## Etapas restantes

### 14 — Identidade visual e reforma da interface

> **Concluída em 14/09/2026.** O registro do que foi feito e decidido está no
> `CLAUDE.md`, seção "Identidade visual Máquina Certa (etapa 14)". O texto abaixo
> fica como o planejamento que a orientou.

A identidade do Máquina Certa ainda vai ser criada — logo, paleta, tipografia.
Esta etapa aplica o resultado ao site inteiro.

O que a etapa 11 mediu sobre o custo disso:

| Camada | Estado hoje | Custo de trocar |
|---|---|---|
| Cor | 544 usos semânticos, **0 hexadecimais**, 0 classes de cor do Tailwind | Editar `:root` no `app.css` |
| Fonte | `--font-titulo` no `app.css`, 11 referências nas views | Declaração + lista no `vite.config.js` |
| Raio/forma | 68 usos de `rounded-bloco`/`rounded-selo`, 0 do Tailwind padrão | Token |
| Sombra | 0 usos (a direção "Boletim" as proíbe) | Acréscimo, não reescrita |
| **Layout** | **166 utilitários de grid/flex em 24 arquivos** | **Trabalho de verdade** |

Cor, fonte e forma são troca de token. Layout não — se a direção "Boletim"
(papel, fio de 1px, hierarquia por régua e tipografia) for descartada, são 9
páginas e 15 componentes a revisar.

**Ponto de partida encontrado em 11/09/2026.** Existe um documento anterior
do projeto, `~/Downloads/plano-portal-maquininhas.md` (14/06/2026), superado em
quase tudo — ele descrevia XAMPP, Slim e um coletor em Node/Playwright/Gemini,
e o schema dele agrupava parcelas em faixas (`credito_parcelado_2x_6x`), que a
regra 2 proibe. Mas as **diretrizes de design** dele contradizem a etapa 06 em
quatro de cinco pontos, e isso provavelmente explica a insatisfacao:

| O documento de junho pedia | A etapa 06 entregou |
|---|---|
| Cantos arredondados ~12px em cards, ~8px em botoes | Formas discretas, raio pequeno |
| Sombras suaves (`0 2px 8px rgba(0,0,0,0.06)`) | **Zero sombras** — a direcao "Boletim" as proibe |
| Transicoes em hover (0.2s), micro-animacoes de entrada | Praticamente nenhuma |
| **Mobile-first** — "a maioria dos MEIs vai acessar pelo celular" | Desenhado do desktop para baixo |
| Verde/vermelho para indicar melhor/pior taxa | Cor semantica por **estado do dado** (aferido/reportado/vencido) |

O ultimo item merece cuidado: a cor por estado do dado **nao e capricho
estetico**, e como as regras 4, 6 e 8 aparecem na tela — o leitor distingue taxa
publicada pela marca de mediana de relatos pela cor. Se a identidade nova
introduzir verde/vermelho por ranking, os dois sistemas de cor vao competir, e
o que informa perde para o que decora. Vale decidir isso explicitamente, nao por
acidente.

O mobile-first tambem nao e detalhe de gosto: se o publico chega pelo celular
apos um video, e a metrica que a etapa 12 vai medir.

Cobrado nesta etapa:

- `node scripts/verifica-contraste.mjs` tem que passar com a paleta nova
- `npm run build` obrigatório junto da mudança — o `@source` do Tailwind lê o
  cache de views, e classe nova sem build é estilo faltando em produção
- Logo aplicado no cabeçalho, no rodapé, no favicon e na página "em breve"
- Revisão das oito páginas públicas no navegador, não só em teste

### 15 — Imagens: logos de marca, equipamentos e bandeiras

> **Concluída em 14/09/2026.** O registro do que foi feito e decidido está no
> `CLAUDE.md`, seção "Imagens (etapa 15)". O texto abaixo fica como o
> planejamento que a orientou — inclusive a decisão de incluir o logo também no
> resultado do comparador, tomada com o Everton durante a etapa (custo: tocar
> os dois motores e o teste de paridade), e a de manter o candidato em memória
> (base64) até a aprovação, nunca em disco.

Hoje não há **nenhuma** imagem no catálogo. O schema já as prevê
(`marcas.logo_path`, `equipamentos.imagem_path`, `bandeiras.logo_path`) e o
`ImagemSeguraWebp` já valida por conteúdo e converte para WebP.

**Sobre coletar automaticamente dos sites das marcas** — a pergunta do Everton.
A resposta honesta tem três camadas:

1. **A melhor fonte não é o site: é o programa de afiliados.** Toda marca
   aqui tem um, e programas de afiliado **entregam** kit de mídia com logo em
   alta, foto de produto e regras de uso. É material aprovado, em melhor
   resolução, e com permissão explícita. Como o projeto já é afiliado de cada
   uma, esse acesso já existe.
2. **Raspar site de marca é ruim por três motivos.** Foto de produto é obra
   protegida, e o uso do logo é defensável em comparação mas o da fotografia é
   bem mais frágil; os termos de uso de várias delas proíbem; e a regra 8 já
   cravou o princípio ao proibir raspagem do Reclame Aqui. Abrir exceção aqui
   contradiz o que o `/metodologia` promete ao leitor.
3. **O meio-termo que respeita as duas coisas:** uma ação no painel que
   **recebe uma URL** (kit de mídia, página de imprensa, ou a própria página do
   produto), busca o candidato — `og:image`, `apple-touch-icon`, ou o link
   direto —, passa pelo `ImagemSeguraWebp` e **mostra para aprovação humana**
   antes de gravar. Automatiza o trabalho chato sem automatizar a decisão, no
   mesmo espírito da regra 10.

Também nesta etapa:

- Placeholder honesto para marca sem logo (não inventar, não usar genérico de
  banco de imagem)
- Onde a imagem aparece é decisão de layout e depende da etapa 14 — por isso
  esta etapa vem depois dela
- `loading="lazy"`, `width`/`height` declarados e formato WebP servido do
  próprio domínio (regra 9)

### 16 — Painel de saúde e observabilidade do administrador

> **Concluída em 15/09/2026.** O registro do que foi feito e decidido está no
> `CLAUDE.md`, seção "Painel de saúde do administrador (etapa 16)". O texto
> abaixo fica como o planejamento que a orientou. Uma pendência real ficou:
> o cron do `links:verificar` precisa ser criado no hPanel à mão (mesmo
> motivo do cron do backup — não há `crontab` na linha de comando neste
> servidor; o comando exato está no CLAUDE.md). `BACKUP_LOG_PATH` já entrou
> no `.env` de produção nesta mesma etapa.

Um lugar só, no `/admin`, para responder "o site está bem?" sem abrir SSH nem
hPanel. Parte disso já existe (`PainelInicial` soma três alertas operacionais);
o resto é novo.

**Já dá para fazer com o que existe hoje:**

- **Backup**: última execução, tamanho do dump e se terminou em `--- fim, ok
  ---`. Lê `~/backups/comparador/backup.log`. Transforma a conferência manual
  da etapa 11 em um cartão no painel.
- **Fila parada**: contagem de `jobs`. Com `QUEUE_CONNECTION=sync` isso é
  sempre zero — número diferente de zero significa que alguém mudou a
  configuração, e foi exatamente esse silêncio que custou uma hora na etapa 11.
- **Cliques em cupom**: a tabela `eventos_cupom` existe desde a etapa 09 e
  nunca foi visualizada. Cliques por dia, por marca, por cupom.
- **Link de afiliado quebrado**: verificação periódica com `HEAD` em cada URL
  de cupom e de marca, marcando o que não responde 200. **É o alerta de maior
  valor da lista** — link quebrado é receita perdida e absolutamente invisível
  hoje.
- **Banco**: tamanho por tabela, crescimento, contagem por entidade.
- **Segurança**: `APP_DEBUG` desligado, permissão do `.env`, usuários sem 2FA
  configurado, validade do certificado SSL.
- **Frescor**: já existe, expandir — taxas por faixa de idade, e não só a
  contagem acima de 30 dias.

**Depende da etapa 12:**

- **Acessos diários e fonte de tráfego.** Duas fontes possíveis: a API de
  Analytics do Cloudflare (server-side, não depende de consentimento de cookie)
  e a Data API do GA4 (precisa de conta de serviço). A do Cloudflare é a mais
  honesta para um site que respeita recusa de cookie — ela conta o que o
  servidor viu, não o que o navegador deixou rastrear.

**Fica de fora, e por quê:** uso de disco e CPU da hospedagem. O PHP web da
Hostinger tem `exec` e `shell_exec` desabilitados, e `disk_free_space()` numa
hospedagem compartilhada reporta o volume inteiro, não a cota da conta — número
que mente é pior que número ausente. Isso continua no hPanel.

### 17 — Curadoria e validação das taxas

O trabalho de dados, feito **depois** da reforma visual, por decisão do Everton:
avaliar taxa e avaliar tela ao mesmo tempo confunde as duas coisas.

- Aprovar as 964 taxas em lote (regra 10) e rodar `comparador:gerar-json`
- Fechar os buracos listados em "Pendente ao fim da etapa 05" no `CLAUDE.md`:
  mensalidade de Ton e PagBank, `taxa_antecipacao_mensal`, equipamentos da
  SumUp sem preço, Pix de Ton e SumUp, voucher, parcelamento da adesão
- Reavaliar o Mercado Pago como plano promocional, hoje possível
- Primeiras faixas reportadas de Cielo, Rede, GetNet e Stone, se a captação de
  relatos tiver rendido
- Cupons reais cadastrados, com `valido_ate`

### 18 — Manual do administrador

> **Concluída em 17/09/2026.** O registro do que foi feito está no
> `CLAUDE.md`, seção "Manual do administrador (etapa 18)". O texto abaixo
> fica como o planejamento que a orientou.

Didático, claro e simplificado. Escrito **agora** e não antes porque só aqui o
painel está na forma final — manual escrito sobre alvo em movimento nasce
errado. E **antes** do lançamento, não depois: é durante o pico de tráfego que
se precisa saber operar.

- Como cadastrar marca, plano, equipamento e cupom, na ordem certa
- Como lançar a tabela de taxas de uma marca em lote
- O que significa cada estado: rascunho, publicado, aferido, reportado, vencido
- Como aprovar taxa e **por que é preciso regenerar o JSON depois**
- Como ler as duas filas de revisão de relatos de lojistas
- Como ler o painel de saúde e o que fazer com cada alerta
- Como restaurar um backup
- O que **nunca** fazer: publicar taxa sem fonte, editar código pelo servidor,
  rodar seeder em produção

Formato: página dentro do próprio painel, para não virar um PDF que envelhece
numa pasta.

### 19 — Lançamento

> **Trava de aprovação da marca, adicionada em 17/09/2026** (ver `CLAUDE.md`,
> seção "A trava da marca"): nenhuma marca com taxa publicada aparece no site
> sem um clique em "Aprovar marca" em `/admin/marcas`, condicionado o motor
> conferir que ela fecha conta nas quatro formas de pagamento. Passo novo
> antes da conferência final: aprovar Ton, PagBank, SidePay, FacilityPay,
> Yelly e TrincaPay uma a uma no painel.

- Aprovar cada marca pronta no painel (`/admin/marcas`, botão "Aprovar
  marca") — sem isso o JSON sai só com "sem dado publicado"
- Conferência final das oito páginas com conteúdo real e identidade nova
- `SITE_EM_BREVE=false` e `deploy.sh` — o único passo que reabre o site
- Sitemap, indexação e Search Console liberados — a etapa 12 deixou a
  verificação pronta (registro TXT) mas não enviou `sitemap.xml`; enviar
  agora ao Search Console e reimportar no Bing Webmaster Tools
- Vídeo do canal apontando para o portal

### 20 — Decisão sobre programa de parceiros

Era a 15. Vai para o fim porque é decisão de negócio, não bloqueio técnico.

**Atenção herdada da etapa 11:** se isso trouxer cadastro público de usuário,
`User::canAccessPanel()` precisa de critério de verdade — hoje ele devolve
`true` para qualquer usuário da tabela, e com cadastro aberto todo cadastrado
entraria no `/admin`.

### 21 — Cadastro de taxas por imagem (IA de visão)

Pedida pelo Everton em 15/09/2026, na etapa 17: em vez de descrever a tabela
por texto, subir a captura de tela e a ferramenta ler e propor o
cadastro sozinha — o que hoje só acontece manualmente, via chat com o
Claude. Fica para **depois do lançamento** de propósito: enquanto isso, o
Everton manda print por aqui mesmo, e o cadastro segue manual (Tabela do
Plano, etapa 17).

- Upload de imagem no admin (mesmo padrão de segurança do `ImagemSeguraWebp`
  da etapa 15 — valida pelo conteúdo, não pela extensão)
- Chamada a uma API de visão (a chave e o custo por chamada precisam existir
  — não é gratuito como a busca de imagem por URL da etapa 15)
- **Regra 10 não é opcional aqui**: o que a IA leu vai para uma prévia, nunca
  direto ao banco — o admin confere linha por linha antes de aprovar, no
  mesmo espírito da busca de imagem por URL (etapa 15): "automatiza o
  trabalho chato sem automatizar a decisão"
- Reaproveita a grade da Tabela do Plano (etapa 17) como tela de conferência
  — a IA pré-preenche as células, a pessoa só corrige o que estiver errado
  antes de salvar

---

## A decisão que reorganizou o plano

Em 11/09/2026 o Everton disse que não gostou da estética do front-end — cores,
fontes, layout — e que criaria uma identidade visual própria, o que levaria
alguns dias. A pergunta era se valia parar e esperar.

A medição do custo (tabela da etapa 14) mostrou que **cor e fonte são troca de
token e layout não é**, mas também que **nenhuma etapa restante acrescenta tela
pública nova** — exceto talvez a de parceiros. Adiar a reforma visual não
acumula dívida: os 24 arquivos de hoje são os mesmos de depois.

O que **não** podia esperar era o lançamento. A primeira impressão de um canal
de 500 mil inscritos não se gasta duas vezes, e o logo nem existe. Por isso a
identidade entra antes do lançamento, e não numa fase depois dele.

E o site saiu do ar no mesmo dia. Não fazia sentido receber visita — nem ser
indexado — com a cara provisória e sem nenhuma taxa publicada.

# Construção do Comparador de Maquininhas

Plano de etapas do **Máquina Certa** (`maquinacerta.com.br`), portal comparador
de taxas de maquininhas de cartão do canal Monetizando Negócios.

Uma etapa por sessão. O `CLAUDE.md` registra o **estado e as regras** do que já
existe; este arquivo registra **o que falta e em que ordem**. Quando os dois
divergirem, o `CLAUDE.md` vence sobre o que está feito e este vence sobre o que
vem depois.

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

---

## Etapas restantes

### 12 — Cloudflare, medição e performance

Sem mudança de escopo. É o que vem agora.

- Cloudflare à frente do domínio: DNS, cache de borda, regras por rota
- `GA4_MEASUREMENT_ID` preenchido — o gate do banner de cookies já existe e
  está inerte desde a etapa 10, só falta o ID
- Medição de performance com o site ainda fechado: LCP, CLS, peso do bundle
- Proxy reverso e cabeçalhos: HSTS, cache-control por tipo de arquivo

**Ressalva registrada:** as fontes são 410 KB dos 552 KB do bundle. A etapa 14
troca as famílias, então a otimização de carregamento de fonte vai precisar ser
remedida. O mecanismo (auto-hospedado no bundle, regra 9) sobrevive; só os
arquivos mudam. Não é motivo para adiar a 12 — é motivo para não gastar tempo
afinando `preload` de fonte agora.

### 13 — Monitor de mudanças de taxa

Era a 14. Subiu porque é backend puro e não encosta em nada visual.

- Tabela de staging própria para taxa capturada (a etapa 11 registrou que "não
  há histórico de taxa": aprovar é editar no lugar)
- Coleta periódica das páginas de tarifa que já têm `url_fonte`
- Comparação com o valor vigente e fila de revisão no painel — **nada publicado
  sem aprovação humana** (regra 10)
- Alerta quando uma `url_fonte` deixa de responder ou muda de estrutura

### 14 — Identidade visual e reforma da interface

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

Cobrado nesta etapa:

- `node scripts/verifica-contraste.mjs` tem que passar com a paleta nova
- `npm run build` obrigatório junto da mudança — o `@source` do Tailwind lê o
  cache de views, e classe nova sem build é estilo faltando em produção
- Logo aplicado no cabeçalho, no rodapé, no favicon e na página "em breve"
- Revisão das oito páginas públicas no navegador, não só em teste

### 15 — Imagens: logos de marca, equipamentos e bandeiras

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

- Conferência final das oito páginas com conteúdo real e identidade nova
- `SITE_EM_BREVE=false` e `deploy.sh` — o único passo que reabre o site
- Sitemap, indexação e Search Console liberados
- Vídeo do canal apontando para o portal

### 20 — Decisão sobre programa de parceiros

Era a 15. Vai para o fim porque é decisão de negócio, não bloqueio técnico.

**Atenção herdada da etapa 11:** se isso trouxer cadastro público de usuário,
`User::canAccessPanel()` precisa de critério de verdade — hoje ele devolve
`true` para qualquer usuário da tabela, e com cadastro aberto todo cadastrado
entraria no `/admin`.

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

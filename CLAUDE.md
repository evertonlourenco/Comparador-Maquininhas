# Comparador de Maquininhas

Portal comparador de taxas de maquininhas de cartão para microempreendedores.
Projeto do canal Monetizando Negócios (YouTube, +500 mil inscritos).
Monetização: links de afiliado com cupom de desconto na adesão.

## Stack

| Camada | Tecnologia |
|---|---|
| Framework | Laravel 13.30 |
| Admin | Filament 5.7 (painel em `/admin`) |
| Banco | MySQL 8.0.40 em local; **MariaDB 11.8.9 em produção** (ver etapa 11) |
| PHP | 8.4.23 |
| Ambiente local | Laravel Herd + DBngin (macOS ARM) |
| Produção | Hostinger Cloud Startup + Cloudflare → https://maquinacerta.com.br |

Local: `/Users/Everton/Claude Code/Herd/comparador-maquininhas` → http://comparador-maquininhas.test
Repositório: `git@github.com:evertonlourenco/Comparador-Maquininhas.git` (privado)

Nota: o caminho do projeto contém um espaço ("Claude Code"). Sempre entre aspas em comandos de shell.

### Acesso ao GitHub deste Mac

**Não há `gh` CLI instalado.** O push/pull funciona por SSH, com uma chave já
autorizada na conta do Everton: `~/.ssh/github_everton`, mapeada para
`Host github.com` em `~/.ssh/config` (mesmo arquivo que tem o atalho
`comparador` da etapa 11). Qualquer `git clone`/`push` para
`git@github.com:evertonlourenco/...` já funciona sem senha nenhuma.

**Criar um repositório novo, porém, não dá pra fazer só com a chave SSH** —
isso é uma chamada à API/web do GitHub, que pede token ou login, e nenhum
dos dois existe nesta máquina. Repositório novo (como o do monitor de
mudanças, etapa 13) precisa ser criado pelo Everton no site do GitHub (New
repository → privado → sem README/gitignore, pra não conflitar com o que já
foi commitado local) — depois disso, `git remote add origin <url>` e
`git push -u origin main` funcionam normalmente com a chave já configurada.
Registrar isso aqui existe exatamente para não redescobrir isso do zero
numa sessão futura.

## Comandos

```bash
# o PATH do Herd precisa estar carregado em sessões não interativas
export PATH="$HOME/Library/Application Support/Herd/bin:$PATH"

php artisan migrate
php artisan test
php artisan optimize:clear

# Regra 9: o JSON estático que alimenta o comparador no navegador.
# Sem --rascunhos só entra taxa publicada (regra 10).
php artisan comparador:gerar-json
php artisan comparador:gerar-json --rascunhos   # só para conferir em localhost
```

```bash
npm run build      # fontes entram no bundle, servidas do proprio dominio
node scripts/verifica-contraste.mjs   # le a paleta do app.css e confere WCAG AA
```

**`npm run build` roda `php artisan view:cache` antes do `vite build`, e isso nao e
enfeite.** O `app.css` tem `@source '../../storage/framework/views/*.php'`: o Tailwind
descobre as classes varrendo o **cache de views compiladas**, nao as `.blade.php`
diretamente. Sem o `view:cache`, o CSS gerado depende de quais paginas foram
renderizadas antes na maquina — medido na etapa 11: 73 views em cache produziam
42,09 KB de CSS, e as 187 views completas produzem 63,38 KB. Um terco dos estilos
sumia, e as paginas cujas views nao estavam em cache subiriam sem formatacao. O
build commitado ate a etapa 10 estava incompleto por esse motivo (61,7 KB).

O teste de paridade entre o motor em PHP e o motor em JavaScript chama `node`.
Sem Node no PATH ele se marca como skipped em vez de passar em silêncio.

## Regras de domínio inegociáveis

Estas regras vêm da análise de viabilidade e não devem ser simplificadas:

1. **Prazo de recebimento é dimensão da taxa**, não atributo da marca. A chave de uma
   taxa é: marca + plano + tipo de operação + número de parcelas + prazo.
2. **Parcelas são inteiro de 1 a 21**, nunca faixas agrupadas. Agrupar só na exibição.
3. **Plano é entidade própria**, com `tipo_enquadramento`: automatico | escolhido |
   negociado | **promocional**. O promocional é o único que expira — tabela de entrada
   com prazo (30 dias, ou um teto de volume processado, o que vier antes). Ele nunca
   disputa posição com preço permanente no comparador.
4. **Duas classes de dado de taxa, que nunca se misturam:**
   - `taxa_divulgada` — publicada pela marca, com `url_fonte` e `data_verificacao`
   - `faixa_reportada` — mediana/mín/máx/`n_relatos`/período, para Cielo, Rede, GetNet e
     Stone, que não publicam tabela. Nunca exibir como número exato.
5. **A taxa do afiliado é igual à do site oficial.** Não existe campo de taxa paralela.
   A vantagem do link é o cupom de desconto na adesão — entidade `cupons` separada.
   **`valido_ate` é opcional** (revisto na etapa 17: cupom de afiliado via de regra
   não tem prazo — o que muda de vez em quando é o link, o código ou o percentual,
   não uma data de expiração publicada pela marca). Com data, a ocultação automática
   ao vencer continua valendo; sem data, o cupom fica vigente até alguém marcar como
   inativo no painel. `valor`/`tipo_desconto` também são opcionais, para o caso de
   desconto real mas não quantificado (ver PagBank e Mercado Pago, etapa 17) — nesse
   caso a tela mostra a `descricao` em vez de um número, nunca zero.
6. **Nenhuma taxa entra sem `fonte` e `data_verificacao`.** Campo vazio é honesto;
   número errado é risco de CDC. Selo de frescor degrada após 45 dias.
7. **Marcas podem ter `adquirente_subjacente`** — informação de transparência
   interna quando conhecida, não de deduplicação e não é dado que o site precisa
   publicar (revisto na etapa 17: o campo era obrigatório, virou opcional — a
   TrincaPay entrou sem ele porque o Everton não sabe e não faz sentido inventar).
   Yelly, SidePay e FacilityPay compartilham adquirente (o PagSeguro/PagBank, dito
   pelo Everton — e as tabelas de taxa batem idênticas entre Yelly e SidePay, achado
   nesta mesma etapa) mas são empresas distintas, com suporte, atendimento e política
   de adesão próprios, e concorrem como opções independentes. O desempate entre
   marcas de taxa idêntica é por reputação e custo total, nunca por adquirente.
8. **A nota do Reclame Aqui é campo manual** com data de consulta e link. Nunca raspar.
9. **O comparador roda no navegador** sobre um JSON estático gerado por comando artisan.
   Sem consulta ao banco por visita — a carga é em picos de vídeo, não constante.
10. **Nenhum dado coletado automaticamente vai ao ar sem aprovação humana** no admin.
11. **Formatação brasileira em todo número exibido**, sem exceção — site público, painel
    admin, PDFs, e-mails e exportações:
    - Separador de milhar: ponto. Separador decimal: vírgula. `1.234.567,89`
    - Taxas: sempre 2 casas decimais e símbolo de porcentagem. `2,49%` — nunca `2.49%`,
      nunca `2,5%`, nunca `2,4900%`
    - Dinheiro: `R$ 1.234,56`, com espaço após `R$`
    - Datas: `dd/mm/aaaa`. Fuso `America/Sao_Paulo`
    - **Campos de entrada também**: o usuário digita `10.000,00` e não `10000.00`.
      Converter para float só na fronteira do cálculo, e formatar de volta na saída.
    - No JavaScript do comparador, usar `Intl.NumberFormat('pt-BR', ...)`.
    - Guardar sempre em `decimal` no banco, nunca `float`, para não perder centavo.
12. **O Máquina Certa compara custo de operar a maquininha, nunca custo de conta
    digital** (decisão do Everton, 18/09/2026 — ver "Regra nova: Máquina Certa não
    compara custo de conta digital" na seção da trava da marca). O lojista não é
    obrigado a usar a conta digital da adquirente — pode receber na conta do próprio
    banco, e nesse caso tarifa de saque, TED e Pix (recebido ou enviado) da
    adquirente simplesmente não se aplicam a ele. Entram na conta: taxa de venda,
    custo de adesão/aluguel do aparelho, mensalidade do plano (quando existe). Não
    entram: tarifa de saque, TED, Pix recebido, Pix enviado — mesmo que a marca
    publique esses números, o motor não os soma nem os cobra como dado obrigatório.

## Schema

12 tabelas. O núcleo é dimensional: a taxa é o fato, qualificada por plano, tipo de
operação, grupo de bandeiras, parcelas e prazo de recebimento.

### Diagrama

```
adquirentes
    │ 1:N  (transparência, nunca deduplicação — regra 7)
    ▼
  marcas ──────────────N:N──────────────► bandeiras
    │  │                (bandeira_marca: grupo_bandeira_id)
    │  │                          │
    │  │                          └──────► grupos_bandeiras ◄──┐
    │  │                                                       │
    │  ├── 1:N ──► equipamentos ──┐                            │
    │  │                          │ N:N (equipamento_plano)    │
    │  ├── 1:N ──► planos ────────┘  preco_adesao              │
    │  │              │              preco_adesao_promocional  │
    │  │              │              aluguel_mensal            │
    │  │              │                                        │
    │  │              ├── 1:N ──► taxas_divulgadas ────────────┤
    │  │              │              (classe A: marca publica) │
    │  │              │                                        │
    │  │              └── 1:N ──► faixas_reportadas ───────────┤
    │  │                             (classe B: mediana/faixa) │
    │  │                                        │              │
    │  └── 1:N ──► cupons ──0:1──► equipamentos │              │
    │                                           ▼              │
    └───────── marca_id denormalizado ────► prazos_recebimento ┘
              (sincronizado do plano)
```

### Chave da taxa (regra 1)

As duas tabelas de taxa carregam a mesma chave dimensional:

```
UNIQUE (plano_id, tipo_operacao, grupo_bandeira_id, parcelas, prazo_recebimento_id)
```

`plano_id` já implica a marca. `marca_id` existe denormalizado nas duas tabelas para o
gerador de JSON estático e os filtros do admin — escrito **só** pelo hook em
`TemChaveDeTaxa`, nunca à mão.

### Tabelas

| Tabela | Papel |
|---|---|
| `adquirentes` | Quem processa por trás. Transparência, não deduplicação. |
| `marcas` | Marca comercial. `publica_tabela` decide qual classe de taxa aceita. |
| `bandeiras` | Visa, Mastercard, Elo, Amex, Alelo… |
| `bandeira_marca` | Bandeiras aceitas **+ `grupo_bandeira_id`** — cada marca agrupa do seu jeito. |
| `grupos_bandeiras` | Dimensão: `visa_master`, `demais`, `voucher` e `pix` (etapa 05, decisão 1). |
| `planos` | Regra 3. Custos da conta: mensalidade, saque, TED, Pix, antecipação avulsa. |
| `equipamentos` | Só o que é do aparelho. Preço não mora aqui. |
| `equipamento_plano` | Adesão e aluguel — variam por plano para o mesmo aparelho. |
| `prazos_recebimento` | Dimensão: `na_hora`, `d_1`, `d_14`, `d_30`, `parcela_a_parcela` + `antecipacao_embutida`. |
| `taxas_divulgadas` | Classe A: publicada pela marca, com `url_fonte`. |
| `faixas_reportadas` | Classe B: mediana/mín/máx/`n_relatos`/período. |
| `cupons` | Regra 5. `valido_ate` NOT NULL. Nenhum campo de taxa. |

### Decisões que o schema carrega

**Prazo é tabela, não coluna inteira.** `parcela_a_parcela` tem `dias = null` — cada
parcela cai no mês dela. Não cabe em um `int`.

**Duas tabelas de taxa, nunca uma com coluna `classe`.** Separadas, é impossível ler
mediana como número publicado. E `faixas_reportadas` não tem nenhuma coluna chamada
`percentual` — só `percentual_mediana`, `percentual_minimo`, `percentual_maximo`.
Um `$taxa->percentual` acidental numa view não compila silenciosamente: vem nulo.

**Pix é grupo de bandeira também — um grupo técnico.** `grupo_bandeira_id` é NOT
NULL nas duas tabelas de taxa e o Pix não passa por bandeira nenhuma. Tornar a
coluna nullable custaria a chave única da regra 1: em MySQL e em SQLite, NULL é
distinto de NULL dentro de um índice UNIQUE, então a mesma linha de Pix poderia
entrar duas vezes para o mesmo plano e prazo sem o banco reclamar — e todo leitor
passaria a precisar de LEFT JOIN. O grupo `pix` mantém a coluna NOT NULL, mantém
a chave valendo e não pede migration, porque grupo é linha e não enum. O que
custa: uma linha na tabela de grupos que não agrupa bandeira nenhuma. O preço é
pago com duas guardas em `TemChaveDeTaxa` — taxa de Pix só no grupo `pix`, grupo
`pix` só aceita Pix — e com o escopo `GrupoBandeira::deCartao()`, que tira o
grupo do cadastro de bandeiras da marca e do lançamento em lote. Ver etapa 05.

**`prazos_recebimento.antecipacao_embutida` diz quem já cobrou o adiantamento.**
Ligado em `na_hora`, `d_1` e `d_14` — os três só existem porque a marca antecipa
o recebível e cobra por isso dentro do percentual. É coluna, e não uma lista de
códigos no PHP, porque o painel permite cadastrar prazo novo: um `d_7` criado
amanhã precisa declarar isso, e adivinhar aqui é cobrar em dobro ou não cobrar
nada. `PrazoRecebimento::mesesDeAntecipacao()` converte o prazo na unidade de
`taxa_antecipacao_mensal`: `dias / 30`, ou `(n + 1) / 2` quando cada parcela cai
no mês dela.

**Voucher é grupo de bandeira, não tipo de operação.** Vale-refeição é débito à vista;
o que muda são as bandeiras, o prazo e o percentual — os três já capturados. Manter
`voucher` também em `tipo_operacao` criaria combinações sem sentido (voucher em 12x).
`tipo_operacao`: `debito` | `credito_avista` | `credito_parcelado` | `pix`.

**O grupo de bandeiras mora no pivot, não em `bandeiras`.** Cada marca decide onde Elo
e Amex caem — numa está com Visa/Master, noutra nas demais.

**Adesão pertence ao par equipamento+plano.** A mesma maquininha custa diferente em
cada plano da marca.

**Frescor não é coluna.** `dias_desde_verificacao`, `esta_fresca` e `nivel_frescor` são
calculados sobre `data_verificacao` (trait `TemFrescor`, 45 dias). Coluna congelaria e
passaria a mentir no dia seguinte.

**Enums como `string` + enum do PHP**, não `ENUM` do MySQL: alterar um `ENUM` no MySQL 8
exige SQL cru. Os enums vivem em `app/Enums` e implementam `HasLabel` do Filament.

**Decimais sempre `decimal`, com cast `decimal:N`** — retorna string e não perde centavo.
Percentuais em `decimal(6,4)`, dinheiro em `decimal(10,2)`.

### Integridade no banco (MySQL; pulada no SQLite dos testes)

- `chk_*_parcelas` — `credito_parcelado` entre 2 e 21; qualquer outro tipo exige
  exatamente 1 parcela.
- `chk_faixas_reportadas_ordem` — `minimo <= mediana <= maximo`.
- `chk_faixas_reportadas_periodo` e `chk_cupons_validade` — início antes do fim.

Em PHP, `TaxaDivulgada` recusa marca com `publica_tabela = false` (regra 4), lançando
`DomainException`.

### Dimensões curadas

`DimensoesSeeder` popula `prazos_recebimento` (5) e `grupos_bandeiras` (3). São
estrutura, não dados de etapa 04 — os models referenciam esses códigos por constante
(`PrazoRecebimento::NA_HORA`, `GrupoBandeira::VISA_MASTER`).

### Carga de dados (etapa 04)

Os dados reais vivem em seeders versionados, não em dump — assim a carga é
reproduzível, revisável no diff e reexecutável. Todos usam `updateOrCreate`:
rodar `php artisan db:seed` de novo atualiza, nunca duplica.

**Reseed nunca desfaz decisão do admin — achado em produção em 15/09/2026,
etapa 17.** `updateOrCreate` originalmente regravava `status` (e, no caso de
`Plano`, também `nome`) em toda linha que já existia, porque o array de
valores do seeder sempre trazia o padrão dele (`rascunho`/`ativo`/o nome da
tabela oficial). Efeito real: o Everton aprovava taxas no painel, alguém
(inclusive o Claude, sugerindo `db:seed --force` pra pegar uma marca nova)
rodava o seeder de novo, e a aprovação sumia — sem aviso nenhum, porque
`updateOrCreate` não distingue "criando" de "sobrescrevendo". `SeederDeMarca`
(taxa e plano), `MarcasSeeder` e `CuponsAfiliadoSeeder` agora conferem se o
registro já existe antes de montar o array de valores, e removem `status`
(e `nome`, só em `Plano`) desse array quando já existe — a criação continua
recebendo o padrão normalmente. `tests/Feature/Dominio/
ReseedNaoDesfazAprovacaoTest.php` cobre isso pros quatro modelos, incluindo
que o reseed **continua** atualizando percentual/fonte de uma taxa não
aprovada (a correção não virou seeder-que-não-faz-nada). **Todo seeder novo
que usa `updateOrCreate` sobre uma tabela que o admin edita precisa do mesmo
cuidado** — não é específico dessas quatro classes, é o risco de qualquer
`updateOrCreate` cujo array de valores inclua um campo que o painel também
edita.

| Seeder | O que carrega |
|---|---|
| `AdquirentesSeeder` | 8 adquirentes, cada um confirmado no rodapé ou no texto institucional do site da própria marca |
| `BandeirasSeeder` | 13 bandeiras da carga original, só as que apareciam em alguma marca já carregada. Ben Visa Vale e Green Card entraram depois, direto (não pelo seeder) — ver "Curadoria e validação das taxas (etapa 17)" |
| `MarcasSeeder` | 9 marcas + pivot `bandeira_marca` com o grupo de cada uma |
| `PagBankSeeder`, `InfinitePaySeeder`, `TonSeeder`, `SumUpSeeder` | planos, equipamentos e a tabela de taxas de cada marca |

**`DatabaseSeeder` não usa `WithoutModelEvents`, e isso é deliberado.** O trait
vem do scaffolding do Laravel e desligaria os eventos de model — justamente o
hook de `TemChaveDeTaxa`, único lugar que preenche `marca_id` a partir do plano
(regra 1), e a guarda de `TaxaDivulgada` que recusa marca com
`publica_tabela = false` (regra 4). Com eventos desligados a carga gravaria
`marca_id` nulo e furaria a regra 4 em silêncio.

**Estado da carga, verificado em 08/09/2026** — 964 taxas divulgadas (960 da
etapa 04 + 4 de Pix destravadas na etapa 05), todas em rascunho (regra 10),
todas com `url_fonte` e `data_verificacao`:

| Marca | Publica tabela | Planos | Taxas |
|---|---|---|---|
| Ton | sim | 6 faixas de faturamento | 528 (2 prazos × 2 grupos × 1x a 21x) |
| InfinitePay | sim | 4 faixas de faturamento | 284 (3 prazos × 2 grupos × 1x a 12x, + Pix) |
| SumUp | sim | 3 faixas de faturamento | 78 (2 prazos × 1 grupo × 1x a 12x) |
| PagBank | sim | 3 | 74 (3 prazos × 2 grupos, parcelado único de 2x a 12x) |
| Mercado Pago, Stone, Cielo, Rede, GetNet | não | — | 0 — ver abaixo |

A SumUp publica percentual **só para Visa e Mastercard** — toda tabela do site
dela traz essa nota. Elo, Amex e os vouchers estão vinculados como bandeira
aceita, sem taxa. E os aparelhos dela entraram sem preço: a página publica só o
valor da parcela, e num dos modelos com dois valores sem dizer qual vigora.

**O que ainda não entrou, e por quê:**

- **Faixas reportadas: nenhuma.** Cielo, Rede, GetNet, Stone e Mercado Pago
  estão cadastradas como marca, mas `faixas_reportadas` exige `n_relatos`,
  `periodo_inicio` e `periodo_fim`. Isso não se levanta em site oficial — vem
  da captação de relatos da etapa 10. Marca sem dado nenhum é o estado honesto,
  e o Painel Inicial já sinaliza.
- **Mercado Pago entrou com `publica_tabela = false`**, somando-se às quatro
  que a regra 4 já nomeava. A página pública dele traz só a taxa promocional
  dos primeiros 30 dias; a tabela padrão varia por faturamento e só aparece no
  simulador dentro da conta, atrás de login. Sem tabela pública não há
  `url_fonte` para citar, e carregar só a promocional venderia como permanente
  uma taxa que dura 30 dias. Se a tabela aparecer em página aberta, o campo é
  um clique no painel.

  **Revisitável desde a etapa 05:** o impedimento era não haver como carregar
  uma tabela promocional sem vendê-la como permanente. Agora há —
  `tipo_enquadramento: promocional`. Se a taxa dos 30 dias estiver em página
  aberta e com prazo declarado, ela pode entrar como plano promocional, e o
  comparador a exibe em bloco próprio, com a validade à vista.
- **Pix: resolvido na etapa 05.** O impedimento era de schema, não de dado —
  não havia grupo de bandeira onde a linha coubesse. Com o grupo técnico `pix`
  criado, entrou a carga da InfinitePay: 0% nas quatro faixas, prazo "na hora",
  da mesma leitura de `infinitepay.io/taxas` que já sustentava "Pix gratuito"
  nos campos de tarifa do plano. Ton e SumUp continuam sem linha de Pix porque
  a etapa 04 não registrou percentual verificado para elas — agora é um clique
  no painel, não mais um bloqueio.
- ~~**PagBank: os planos Essencial e Super Max entraram sem taxa.**~~
  **Resolvido em 16/09/2026:** a página "Taxas e Planos" (`URL_PLANOS`) só dá
  percentual solto, sem prazo nem grupo de bandeiras — mas o simulador de
  taxas na própria home da PagBank (`pagbank.com.br`) abre a tabela
  dimensional completa por plano, com alternador "período promocional" /
  "após o período promocional" (e, no Super Max, mais um alternador por
  faixa de faturamento). O Everton mandou os prints desse simulador; daí
  veio a carga real. As taxas do PagBank continuam vindo em dois lugares:
  a página Taxas e Tarifas alimenta o plano "Taxas iniciais" (automático,
  dimensional desde a carga original), e o simulador da home alimenta
  Essencial e Super Max (`PagBankSeeder::planosComerciais()`).

  **Cada plano comercial virou dois ou três planos**, seguindo a regra 3 (a
  oferta de entrada nunca é coluna a mais no plano permanente, é um `Plano`
  `promocional` à parte):
  - `Essencial` (permanente, `escolhido`) + `Essencial — período
    promocional` (`promocional`, 30 dias ou R$ 1.500 processados,
    `promocional_sucessor_id` apontando de volta pro Essencial permanente —
    aqui dá pra declarar um sucessor único porque não há faixa de
    faturamento envolvida).
  - `Super Max — até R$ 2.000` e `Super Max — acima de R$ 2.000`
    (permanentes, `automático`, faixas de faturamento — o antigo plano
    único "Super Max", `escolhido`, foi renomeado/reclassificado pra virar
    a faixa "até R$ 2.000") + `Super Max — período promocional`
    (`promocional`, 30 dias ou R$ 5.000 processados, **sem**
    `promocional_sucessor_id` — depois da promoção o lojista cai na faixa
    automática que bater com o faturamento dele, não num plano único).
  - Só o prazo "na hora" é publicado nessas telas — nenhum D+1/D+30
    declarado pra Essencial/Super Max.
  - Preço de aparelho (`equipamento_plano`) segue vinculado às duas faixas
    permanentes do Super Max (`até` e `acima de R$ 2.000`) — é a mesma
    compra de hardware, só a taxa pós-promoção muda com o faturamento.

  **Achado no processo, cuidado pra próxima vez que um `nome` de plano
  mudar num seeder:** a chave do `updateOrCreate` em `SeederDeMarca::plano()`
  é `[marca_id, slug]`, e o `slug` é derivado do `nome` via `Str::slug()` —
  então trocar o `nome` passado ao seeder sem também corrigir o `slug` já
  gravado no banco (se o registro tiver sido criado por fora, como aconteceu
  aqui) faz o próximo `db:seed` criar um plano **duplicado** em vez de
  atualizar o existente. Testado rodando o seeder duas vezes seguidas depois
  do ajuste: 6 planos, 209 taxas, sem duplicar — e uma taxa marcada
  "publicado" à mão continuou "publicado" depois do reseed (a proteção da
  etapa 17 contra reseed desfazendo aprovação, conferida de novo aqui).

  **Aconteceu na prática, em produção, no mesmo dia:** o ajuste de slug
  acima só foi aplicado no banco local antes de rodar o seeder — production
  tem banco próprio, separado, e o `deploy.sh` nunca roda `db:seed` (carga
  é sempre manual, de propósito). Rodar o seeder em produção sem primeiro
  corrigir o slug lá criou um "Super Max — até R$ 2.000" **duplicado**
  (plano novo com as 27 taxas certas, o antigo "Super Max" órfão do lado).
  Corrigir isso pediu `forceDelete()`, não `delete()`: `Plano` usa
  `SoftDeletes`, então um `delete()` comum só marca `deleted_at` e a linha
  continua ocupando o índice único `(marca_id, slug)` — a primeira tentativa
  de corrigir bateu de frente nisso (`Duplicate entry` no slug que a linha
  soft-deletada ainda segurava), mas como estava dentro de `DB::transaction`
  o rollback foi automático e limpo, sem sujar nada pela metade. **Lição:**
  ajuste de dado que um seeder depende (slug, principalmente) precisa ser
  replicado em todo banco onde o seeder vai rodar, produção incluída - e
  apagar um `Plano` de verdade (não só soft-delete) exige `forceDelete()`.
- **Voucher: nenhuma taxa.** Ton e SumUp aceitam vale-refeição (vinculados no
  pivot, no grupo `voucher`), mas nenhuma das duas publica o percentual. O
  PagBank diz explicitamente que voucher é negociado com a bandeira.
- **Produtos "celular como maquininha" ficaram de fora** — TapTon, InfiniteTap,
  Tap On e Tap to Pay. Têm tabela própria e faixas de faturamento próprias, e
  virariam plano separado dentro da mesma marca. Vale decidir na etapa 07 se o
  comparador os trata como maquininha.

### Limitações conhecidas

- **Taxa por bandeira individual não é representável.** A granularidade é o grupo. Se
  uma marca publicar Amex separada de Elo, cria-se um grupo novo (é linha, não enum —
  não precisa de migration).
- **Sem histórico de taxa.** Aprovar é editar no lugar. Isso continua valendo para o
  domínio (`taxas_divulgadas`/`faixas_reportadas`/`equipamento_plano`/`cupons`): não
  existe versão anterior de uma taxa depois de editada. O que a etapa 13 resolveu foi
  um problema vizinho, não este — `deteccoes_de_mudanca` é staging do que uma fonte
  externa mudou, não histórico do que o admin já aprovou.

## Painel admin (Filament)

Recursos em `app/Filament/Resources`, agrupados na navegação:

| Grupo | Recursos |
|---|---|
| Catálogo | Marcas, Planos, Equipamentos, Cupons |
| Taxas | Taxas Divulgadas, Faixas Reportadas |
| Dimensões | Adquirentes, Bandeiras, Grupos de bandeiras, Prazos de recebimento |

Cada um segue o padrão gerado pelo `filament:make-resource` (Resource + `Schemas/*Form`
+ `Tables/*Table` + `Pages`), não embutido. **Toda tabela do domínio tem CRUD pelo
painel** — incluir uma marca, um equipamento, uma bandeira ou até um grupo de bandeiras
novo não exige tocar em código nem rodar seeder.

**Dimensão curada não é editável à vontade.** `grupos_bandeiras` e `prazos_recebimento`
ganharam CRUD, mas os códigos que o código-fonte referencia por constante
(`GrupoBandeira::RESERVADOS`, `PrazoRecebimento::RESERVADOS`) têm o campo `codigo`
desabilitado e não dehidratado no formulário, e a exclusão bloqueada. Renomear
`visa_master` pelo painel quebraria silenciosamente quem compara com a constante.
Criar um grupo ou prazo **novo** continua livre — é exatamente o caso que motivou
essas dimensões serem tabela e não enum. Adquirente com marca apontando para ele
(inclusive soft-deletada, que a FK `restrictOnDelete` ainda enxerga) também não é
excluível.

**Cupom pluraliza errado em inglês.** `Str::plural('cupom')` dá `cupoms`. O slug da
rota e os labels do `CupomResource` são fixados manualmente (`cupons`) — se um novo
resource tiver plural irregular em português, o mesmo cuidado se aplica.

**Select com `->options(EnumClass)` pode entregar o enum já resolvido no `$get()`/
`$state`**, não só a string crua. Todo closure de formulário que recebe o valor de um
Select enum-backed (`afterStateUpdated`, `visible`, `minValue`/`maxValue`, `prefix`)
precisa aceitar `EnumClass|string|null` e normalizar antes de comparar ou construir o
enum — comparar direto com `=== Enum::Caso->value` ou chamar `Enum::from($get(...))`
quebra quando o valor chega como instância. Ver `tipoOperacaoDe()` em
`TaxaDivulgadaForm`/`FaixaReportadaForm` e `enquadramentoDe()` em `PlanoForm`.

**Regras de fechadura em `->rules([...])` do FileUpload precisam de um wrapper.**
Filament avalia (`evaluate()`) cada item do array de `rules()` antes de usá-lo — um
Closure de validação no formato do Laravel (`fn (string $attribute, $value, Closure
$fail)`) é interpretado como "resolva isto para obter a regra", não como a regra em
si, e quebra porque `$attribute` não é injetável. Por isso `ImagemSeguraWebp::
regraDeValidacao()` é passada como `fn () => ImagemSeguraWebp::regraDeValidacao()` —
o wrapper de zero argumentos é avaliado (trivial), e o que ele retorna é a regra real.

**Upload de imagem (logo de marca, foto de equipamento) valida pelo conteúdo, não
pela extensão**, e converte para WebP no disco — `app/Support/Uploads/
ImagemSeguraWebp.php`, usado via `->saveUploadedFileUsing()`. `finfo_file` lê os
bytes reais; um `.jpg` que na verdade é texto é rejeitado antes de chegar ao GD.

**Lançamento em lote de taxas** (`TaxaDivulgadaResource\Pages\LancamentoEmLote`,
acessível pelo botão na listagem) resolve o pedido de "lançar a tabela inteira de
uma marca sem criar um registro por vez": grade fixa de 21 colunas (1x a 21x) por
seção de prazo de recebimento, com metadados (fonte, verificação, status) comuns ao
lote inteiro. 1x sempre grava como `credito_avista`; 2x–21x como `credito_parcelado`
(regra 2) — débito e Pix não têm a dimensão parcelas e continuam no cadastro normal.
Cada célula preenchida faz `updateOrCreate` pela chave da regra 1, então relançar o
lote atualiza em vez de duplicar. O Select de marca já filtra `publica_tabela = true`
(regra 4); mesmo assim o método `lancar()` captura `DomainException` do model e avisa
por notificação em vez de estourar erro 500.

**Tabela do plano** (`TaxaDivulgadaResource\Pages\TabelaDoPlano`, rota
`/admin/taxas-divulgadas/plano/{plano}`) é a etapa 17: editar taxa por taxa era o
maior atrito do dia a dia do painel, segundo o Everton. Mostra a tabela inteira de
um plano — Pix, débito e crédito de 1x a 21x, por grupo de bandeiras e por prazo —
numa tela só, **pré-preenchida** com o que já existe (diferente do Lançamento em
Lote, que sempre abre em branco). O nome do plano é editável no topo da mesma
tela, porque a marca às vezes renomeia sem mudar as taxas. Acessível pela coluna
"Plano" e pelo botão "Tabela do plano" na listagem de Taxas Divulgadas, e pelo
botão homônimo na listagem de Planos.

Só aparecem as colunas de grupo de bandeiras que a marca de fato usa
(`marca->bandeiras()`, união com qualquer grupo que já tenha taxa gravada, pra
nunca esconder dado existente) — mostrar uma coluna que a marca não usa é
exatamente o tipo de erro que confunde o cliente no site (achado real: SidePay
entrou com o grupo `elo` isolado numa primeira leitura da etapa 17, quando a
marca publica "Elo + Outros" como uma coluna só).

Célula em branco que tinha valor = a taxa foi apagada (regra 6, ausência é
honesta). Célula alterada grava com a fonte/data/status do bloco comum da tela;
célula que não mudou não é tocada — mantém a fonte e a data de quando aquele
número foi lido de verdade.

**Achado escrevendo o teste, não por inspeção:** a primeira versão comparava o
estado novo contra uma propriedade **privada** (`$original`) preenchida no
`mount()`. O Livewire só re-hidrata propriedade **pública** entre uma interação
e outra — cada clique ou edição em campo é uma request nova, e `mount()` só roda
na primeira. Uma propriedade privada guardada ali volta vazia em todo `salvar()`
seguinte, o que fazia a tela regravar a fonte de células que ninguém tocou (toda
célula preenchida parecia "nova") e "apagar" nunca apagar nada (célula limpa
parecia já ter sido branco antes). A correção: `salvar()` relê o banco na hora,
nunca depende de estado guardado do `mount()`. `tests/Feature/Admin/
TabelaDoPlanoTest.php` cobre os três comportamentos (grava só o que mudou, não
toca no que não mudou, apaga o que foi limpo) — remover a correção faz 2 dos 6
testes falharem.

**Dois ajustes pedidos pelo Everton no primeiro uso real da tela:**

- **Publicar o plano inteiro**, sem passar pela listagem selecionando linha por
  linha: botões "Publicar toda a tabela" / "Voltar tudo para rascunho" no topo,
  que mudam só o `status` de toda taxa do plano — separado da grade de células
  (que mexe em número, fonte e data). O motivo de serem ações à parte: misturar
  os dois no mesmo "Salvar" faria uma edição de status silenciosamente arrastar
  fonte/data de células que ninguém tocou.
- **`url_fonte` deixou de ser obrigatório**: "e se eu receber a tabela direto da
  marca?" (PDF por e-mail, WhatsApp do gerente de contas) — não há URL nesse
  caso, e forçar uma inventaria a fonte. `taxas_divulgadas` ganhou
  `fonte_descricao` (texto livre), no mesmo padrão que `faixas_reportadas` já
  usava desde a etapa 10. O formulário (aqui, no Lançamento em Lote e no
  cadastro de uma taxa só) exige um dos dois — URL ou descrição —, nunca
  nenhum: regra 8 continua de pé, só deixou de significar especificamente "uma
  URL".

**Bug relatado pelo Everton em 16/09/2026, corrigido na mesma sessão: o bloco
"Fonte e verificação" sempre abria vazio e em "Rascunho", mesmo com dado real
gravado.** O `mount()` original nunca lia o banco para esses cinco campos —
só chutava um padrão fixo toda vez (`site_oficial`, hoje, rascunho, URL e
descrição em branco), mesmo depois de "Publicar toda a tabela" já ter
publicado as 44 taxas do plano. `metadadosAtuais()` agora lê a
`TaxaDivulgada` mais recentemente verificada do plano (`orderByDesc
('data_verificacao')`, com `updated_at` como desempate) e usa ela como
referência do bloco. Como cada célula pode ter sua própria fonte/data/status
(regra 1), não existe um valor "certo" único para a tabela inteira — mas essa
referência é também a que reflete o status real depois de "Publicar toda a
tabela"/"Voltar tudo para rascunho", porque as duas mudam o status de toda
taxa do plano de uma vez, então a mais recente carrega o mesmo status que
todas as outras. As duas ações de publicar/rascunhar em massa também
passaram a atualizar `$this->data['status']` na hora, para o select da tela
não continuar mostrando o valor antigo até a próxima navegação. Sem taxa
nenhuma no plano, o bloco abre com o padrão honesto de antes.
`tests/Feature/Admin/TabelaDoPlanoTest.php` ganhou quatro testes cobrindo
esses cenários (com e sem taxa, e os dois botões de massa atualizando o
select em tempo real).

**Pedido do Everton na mesma sessão: um jeito fácil de ver o status de
publicação de todos os planos, de todas as marcas.** A listagem de Planos
(`PlanosTable`) ganhou a coluna "Situação das taxas" — `withCount` conta
taxas totais e publicadas por plano, e o badge mostra "X/Y publicadas"
(verde se todas publicadas, laranja se parcial, vermelho se nenhuma, cinza
para "Sem taxa cadastrada"). Ganhou também agrupamento por marca
(`->groups([Group::make('marca.nome')])`, disponível no seletor "Agrupar
por") e dois filtros novos — "Com taxas em rascunho" e "Sem taxa
cadastrada" — para achar rápido o que falta revisar sem abrir plano por
plano. `tests/Feature/Admin/PlanosTableTest.php` cobre os estados do badge
e os dois filtros novos.

**Painel inicial** (`app/Filament/Widgets/PainelInicial.php`) soma três alertas
operacionais: taxas com `data_verificacao` há mais de 30 dias (um aviso antecipado ao
selo de frescor de 45 dias da regra 8, não o mesmo limite), cupons vigentes vencendo
em até 7 dias, e marcas sem nenhuma taxa/faixa cadastrada.

**Autenticação de dois fatores é obrigatória no painel**, via app autenticador
(TOTP) nativo do Filament — `AdminPanelProvider::multiFactorAuthentication(...,
isRequired: true)`. O segredo e os códigos de recuperação ficam em
`users.app_authentication_secret`/`app_authentication_recovery_codes`, cifrados
(cast `encrypted`) — nunca em texto puro. Todo usuário novo é obrigado a configurar
no primeiro login; não há como pular.

## Convenções

- Commits com prefixo da etapa: `etapa-09: pagina de cupons e rastreamento de cliques`
- Tags nos marcos: `v1.0-lancamento`
- Commit sempre que houver um bloco de trabalho coerente e verificado — sem esperar
  pedido. Direto na `main`, seguindo a linha da etapa-01.
- **Depois do commit, push e deploy — também sem esperar pedido** (autorizado pelo
  Everton em 14/09/2026). `git push origin main` e
  `ssh comparador '~/domains/maquinacerta.com.br/comparador/deploy.sh'`, conferindo
  que a linha `versao no ar` do log traz o hash do commit. O motivo é concreto: o
  deploy busca do GitHub, e commit só local sobe a versão antiga sem erro nenhum —
  foi o que aconteceu no primeiro deploy da etapa 14. Não vale para
  `SITE_EM_BREVE`: reabrir o site continua sendo decisão explícita da etapa 19.
- Uma etapa por sessão. O plano completo está em **`PLANO.md`**, na raiz do
  repositório — o que falta e em que ordem. Este arquivo registra o estado e as
  regras do que já existe; quando os dois divergirem, o `CLAUDE.md` vence sobre
  o que está feito e o `PLANO.md` sobre o que vem depois.
- A versão formatada do plano, com os prompts prontos de cada etapa, é o
  **Artifact "Construção do Comparador de Maquininhas"**:
  https://claude.ai/code/artifact/bb649f45-0ffd-4261-9690-079dca5927ed
  **Ele não é arquivo no disco** — é um Artifact publicado no Claude.ai, e uma
  busca no sistema de arquivos não o acha (aconteceu na etapa 11). Para lê-lo ou
  atualizá-lo, use a ferramenta de Artifact com essa URL. Mudou a ordem das
  etapas: atualize o Artifact **e** o `PLANO.md`.

## Motor de cálculo (etapa 05)

`app/Motor` come um catálogo em **array puro** — o mesmo array que vira o JSON
estático da regra 9 — e devolve array puro. Não toca em Eloquent, não consulta
banco e não lê o relógio: o `hoje` entra pelo cenário. É isso que permite a
implementação gêmea em JavaScript receber exatamente a mesma entrada.

| Arquivo | Papel |
|---|---|
| `App\Support\Dinheiro` | Regra 11 inteira: arredondamento, decimal→float, entrada pt-BR, saída pt-BR |
| `App\Motor\CatalogoDoComparador` | Banco → array. A única peça que conhece Eloquent |
| `App\Motor\Cenario` + `VendaDoCenario` | O que o lojista informa. Aceita dinheiro em pt-BR |
| `App\Motor\MotorDeCalculo` | As três contas e os quatro estados |
| `App\Motor\EstadoDoResultado` | `calculado` / `incompleto` / `faixa_reportada` / `sem_dado_publicado` |
| `App\Console\Commands\GerarJsonDoComparador` | `comparador:gerar-json` |
| `resources/js/comparador/{dinheiro,motor}.mjs` | O gêmeo em JavaScript |
| `scripts/verifica-motor-js.mjs` | Compara os dois sobre os mesmos casos |

### As três contas

```
custo da venda    = valor × percentual + quantidade × valor_fixo     (chave da regra 1)
custo da conta    = mensalidade + saque + TED + Pix + antecipação avulsa
custo do aparelho = aluguel mensal + adesão amortizada
```

**Adesão é amortizada em 12 meses**, e o horizonte é campo do cenário. 12 não é
número escolhido a esmo: é o parcelamento que as próprias marcas oferecem para a
adesão — a InfinitePay publica "R$ 199,00 à vista ou 12x de R$ 16,58" na mesma
página de onde a carga tirou o preço. A adesão cheia anda junto com a amortizada
em todo resultado, porque 12 × R$ 16,58 dá R$ 198,96 e não R$ 199,00: o
arredondamento ao centavo não pode esconder a conta.

**O motor nunca estima.** Faltou a taxa naquele prazo, a mensalidade ou o preço
do aparelho, o resultado sai `incompleto` com a lista do que falta — e o total
muda de nome, para `total_mensal_parcial`. É o mesmo truque de `faixas_reportadas`
não ter coluna `percentual`: um número que não fecha não pode ter o nome do
número que fecha.

**Tarifa nula é desconhecida, não zero** — mas só vira falta quando o cenário usa
o serviço. Quem não faz TED nenhum no mês não precisa saber a tarifa de TED.
A mensalidade é a exceção: ela é cobrada sempre.

### Promoção de entrada não é preço permanente

Marcas oferecem tabela de entrada válida por ~30 dias **ou** até um teto de volume
processado, o que vier antes; depois o lojista cai automaticamente em outro plano.
A carga da etapa 04 gravou a do Ton como faixa de faturamento de R$ 2 mil a R$ 5
mil — mas esses R$ 5 mil são teto de volume processado, não faturamento. O motor
a ranqueava contra planos permanentes e ela ganhava de todo mundo, inclusive do
plano regular da própria marca.

A resposta não é escolher entre comparar só o regular ou só o promocional: é
**comparar por preço permanente e manter a promoção visível em bloco próprio**,
com a validade colada nela. `EstadoDoResultado::Promocional` fica entre
`calculado` e `faixa_reportada`, e o total **não se chama `total_mensal`** — é
`total_mensal_promocional`. O bloco `promocao` carrega os dois limites e o plano
em que o lojista cai depois (o sucessor declarado, ou o enquadramento automático
da marca para aquele faturamento).

### Taxa condicionada ≠ taxa promocional

`taxas_divulgadas.condicao` é o que o lojista precisa **fazer** para o número
valer — o Pix a 0% do Ton depende de ativar a chave Pix no aplicativo. Isso não
vence, então não é promoção; e não é `observacao`, que é nota interna. A condição
sai colada no número, sempre — desde a etapa 20 só em `vendas[].condicao`, que a
tela mostra num "?" ao lado da taxa (hover no mouse, toque no celular). Não entra
mais em `avisos`: como frase solta no cartão ela ficava sem contexto ("Grátis o
quê?", Everton, 18/09/2026). Pix a 0% **por 30 dias** é outra coisa: isso é plano
promocional.

### Parcela da marca ≠ amortização do motor

`equipamento_plano.parcelas_adesao` guarda em quantas vezes sem juros a marca
parcela a adesão. Sem esse campo os dois números colidiam: "12x de R$ 16,58"
(oferta da marca, fato) e a amortização em 12 meses (critério nosso para trazer
custo único ao comparativo mensal). O resultado traz `parcela_da_marca` e
`por_mes` separados, e **só `por_mes` entra na soma do custo mensal**. Nulo em
`parcelas_adesao` é "a marca não declarou", nunca "só à vista".

### Os cinco estados (regra 4 no formato do resultado)

Só `calculado` é ranqueado por preço. Os outros quatro vêm em blocos próprios,
depois, com o motivo à vista — assim uma mediana de relatos, ou um preço de 30
dias, nunca disputa a primeira posição com um número publicado e permanente.

| Estado | Chave do total |
|---|---|
| `calculado` | `total_mensal` |
| `promocional` | `total_mensal_promocional` |
| `faixa_reportada` | `total_mensal_minimo` / `_mediana` / `_maximo` |
| `incompleto` | `total_mensal_parcial` |
| `sem_dado_publicado` | nenhuma — `custos` é nulo |

O nome da chave muda junto com o estado de propósito: é o mesmo truque de
`faixas_reportadas` não ter coluna `percentual`. Um número que não é permanente,
não é exato ou não fecha não pode ter o nome do número que é. Marca sem dado
nenhum aparece com o motivo e sem número: zero seria mentira.

### Regra 8 e regra 5 são recalculadas, nunca gravadas

O JSON é estático e os dias passam. O nível de frescor e a vigência do cupom são
calculados pelo motor contra o `hoje` do cenário — um arquivo gerado há 60 dias
diz "desatualizada" sozinho, e um cupom vencido some sozinho, sem depender de
alguém lembrar de regerar.

### Prazo: a quinta dimensão da chave

> **Revisto na etapa 20 (18/09/2026, Everton):** "é sempre um único prazo de
> recebimento, não existe como combinar prazos no mesmo plano". O texto abaixo
> é a regra atual; a anterior (escolher o prazo mais barato **por linha** e
> avisar da mistura) foi removida — ela gerava a frase "comparado usando mais
> de um prazo (Em 1 dia útil, Na hora)" na tela, e a causa era o Pix.

- **Os cartões de um plano caem num prazo só.** Com prazo pedido, é aquele ou
  nada — se o plano não vende débito naquele prazo, o resultado diz que falta.
  Com `prazo: null` ("Tanto faz"), `prazoMaisBaratoDoPlano()` testa cada prazo
  de cartão que o plano oferece e fica com o que fecha a conta com menos falta
  e, entre esses, o mais barato (vendas + antecipação avulsa); empate pela
  `ordem` da dimensão. Todas as linhas de cartão usam esse prazo.
- **O Pix fica fora do prazo.** Ele cai sempre na hora: nunca é filtrado pelo
  prazo pedido (antes, pedir "Em 1 dia útil" derrubava o Pix e a marca inteira
  virava incompleta) e nunca conta em `prazos_usados`. Vale também para a
  faixa reportada (`faixaDaLinha`).
- **Pendência conhecida — InfinitePay "Sem antecipação"** (em rascunho): a
  modalidade é uma só, mas a carga a gravou com débito `d_1` e crédito `d_30`/
  `parcela_a_parcela`. Pela regra nova nenhum prazo único cobre as três
  operações, então essa modalidade não é escolhida no "Tanto faz" (os prazos
  "Na hora" e "1 dia útil" da marca seguem valendo). Resolver de verdade pede
  modelar "modalidade de recebimento" — decidir antes de aprovar a InfinitePay.

### Por que a fórmula existe duas vezes (decisão 2)

A regra 9 manda o comparador rodar no navegador sobre JSON estático. As entradas
do lojista formam espaço contínuo — faturamento em reais, mix, parcelas, prazo,
horizonte —, então o JSON não tem como carregar resultado pronto para toda
combinação sem congelar a granularidade das perguntas. A aritmética roda no
navegador. E o PHP precisa da mesma conta de qualquer forma: painel, testes de
valor conhecido e o gerador saber o que está exportando.

O PHP é a fonte da verdade. `tests/Feature/Motor/ParidadeDoMotorTest.php` gera o
resultado esperado de 13 casos com o motor em PHP — 9 sobre um catálogo sintético
de valores redondos, 4 sobre a carga real —, manda para o Node e confere campo a
campo, inclusive as strings já formatadas em pt-BR. **Mudou de um lado, muda do
outro; o teste avisa quando alguém esquecer.**

Junto vai uma tabela de arredondamento conferida direto no primitivo, e ela
existe por um motivo concreto: trocando `arredondar()` por `Math.round()` no lado
JavaScript, os 12 cenários continuavam passando. Cenário só encosta no desempate
de meio centavo por acaso. Os valores que separam os dois idiomas são `1,005` e
`0,145`, onde `Math.round` derruba o que o `round()` do PHP sobe. `2,675`, que
parece o caso clássico, **não** diverge — vezes 100 dá 267,5 exatos.

Por isso nenhum dos dois lados usa o arredondamento nativo: os dois reduzem o
valor escalado a 15 dígitos significativos e só então desempatam, meio centavo
para cima.

### Antecipação nunca é cobrada duas vezes (decisão 3)

A antecipação automática já está dentro do percentual quando o prazo declara
`antecipacao_embutida`. Nesses casos o motor **não soma nada** e registra o
aviso. A antecipação avulsa (`planos.taxa_antecipacao_mensal`) só incide sobre o
que ainda não foi antecipado, sobre o valor que sobra a receber (venda menos a
taxa), pelos meses de espera do recebível.

## Identidade visual (etapa 06)

> **Substituída na etapa 14** pela identidade do Máquina Certa — ver "Identidade
> visual Máquina Certa (etapa 14)", mais abaixo. Continua valendo daqui: tokens
> semânticos em vez de `dark:`, tema no atributo escrito antes da pintura, a
> separação `régua`/`contorno`, o anel de foco com folga e tudo em "O que os
> componentes já sabem do domínio". O que mudou: paleta, fontes, raios, a
> direção "Boletim" e o papel do verde.

Direção **"Boletim"**: a referência é a folha de boletim de consumo impressa, não o
painel de fintech. Papel, tinta, fio de 1px e espaço em branco — sem sombra, sem
gradiente, e dois raios de borda no arquivo inteiro (2px em botão e etiqueta, 3px em
bloco). A escolha não é de gosto: um site que parece boletim de consumo faz a regra 6
("nenhuma taxa sem fonte e data") parecer óbvia em vez de burocrática.

| Arquivo | Papel |
|---|---|
| `resources/css/app.css` | Toda a paleta, tipografia e escala. Fonte única da verdade |
| `scripts/verifica-contraste.mjs` | Lê o `app.css` e confere WCAG AA nos dois temas |
| `resources/views/components/layouts/site.blade.php` | Layout base, tema sem piscar, pular-para-conteúdo |
| `resources/views/components/*.blade.php` | Os componentes do portal |
| `resources/views/guia-visual.blade.php` | `/guia-visual` — folha de amostra, `noindex` |
| `tests/Feature/Interface/ComponentesDoPortalTest.php` | O que uma view não pode quebrar em silêncio |

**A cor não decora: cada acento nomeia um estado que o domínio já definiu.**

| Token | O que significa |
|---|---|
| `aferido` (verde) | Taxa divulgada pela marca, dentro dos 45 dias (regras 4 e 8) |
| `reportado` (ocre) | Faixa de relatos, promoção de entrada, taxa condicionada, dado a vencer |
| `vencido` (vermelho) | Cupom fora da validade, verificação degradada, erro de campo |
| `contorno` | Borda de campo e de botão — existe separado da `régua` por causa do contraste |

**Tipografia:** Newsreader nos títulos, IBM Plex Sans no texto, IBM Plex Mono em todo
número, via `@utility numero` com `tabular-nums`. O monoespaçado não é enfeite — é o
que faz a vírgula alinhar numa coluna de 21 parcelas (regra 11). As três famílias são
baixadas no build pelo `laravel-vite-plugin` e servidas do próprio domínio: nenhuma
requisição a terceiro na visita.

### Decisões que o CSS carrega

**Tokens semânticos, não utilitários `dark:`.** Trocar o tema troca o valor do token,
nunca a classe no markup — por isso não há um único `dark:` nos componentes. O
`@theme inline` do Tailwind 4 é o que permite isso: a utilitária compila para
`var(--cor-papel)` e o valor é redefinido pelo tema.

**O tema mora num atributo no `<html>`, escrito antes da primeira pintura** por script
inline no layout. Sem JavaScript não há atributo, e aí o bloco de
`prefers-color-scheme` assume — por isso a paleta escura aparece **duas vezes** no
`app.css`, e por isso `verifica-contraste.mjs` compara os dois blocos e reprova se um
andar sem o outro. `light-dark()` do CSS resolveria isso numa linha, mas falha duro
(token vazio, página quebrada) em navegador anterior a 2024, e o público chega por
celular de ciclo longo.

**`régua` e `contorno` são tokens diferentes de propósito.** O fio de tabela é
decorativo — a zebra, o cabeçalho e o espaço já separam as células —, então a WCAG
1.4.11 não o alcança e ele pode ser sutil, como pede a direção. Já a borda de um campo
*é* a única pista de que ali há um controle, e precisa de 3:1. Misturar os dois num
token só forçaria a escolha entre um fio feio e um campo inacessível.

**O anel de foco tem 2px de folga (`outline-offset`), sempre.** Assim ele encosta no
papel dos dois lados e nunca no preenchimento do botão — que é o que faz o contraste
do anel ser conferível contra uma cor só.

### O que os componentes já sabem do domínio

- `<x-selo-frescor>` recalcula o nível pela mesma constante de 45 dias quando não
  recebe `nivel` do model (regra 8). Aceita `:nivel` e `:dias` direto de `TemFrescor`.
- `<x-bloco-cupom>` **não renderiza nada** quando a validade passou (regra 5), e traz
  a frase "a taxa pelo nosso link é a mesma do site oficial" colada no bloco. O botão
  sai com `rel="sponsored nofollow"`.
- `<x-tabela-taxas classe="reportada">` imprime intervalo com mediana rotulada e
  número de relatos — nunca um número isolado —, e a etiqueta da outra classe não
  aparece na mesma tabela (regra 4).
- `<x-tabela-taxas>` cola a `condicao` embaixo do percentual, e taxa ausente vira
  "não publicada", nunca zero.
- `<x-cartao-marca>` mapeia `EstadoDoResultado` para tom: só `calculado` sai em
  `aferido`; `promocional` e `faixa_reportada` em `reportado`; `incompleto` e
  `sem_dado_publicado` em `apagado`.
- `<x-campo>` não tem caminho que produza campo sem `<label for>`. Dinheiro entra com
  prefixo `R$` e `inputmode="decimal"`, nunca `type="number"` (regra 11).

### Acessibilidade

Conferida, não declarada. `verifica-contraste.mjs` reprova o build lógico se um
hexadecimal do `app.css` cair abaixo de 4,5:1 em texto ou 3:1 em contorno de controle,
nos dois temas. `ComponentesDoPortalTest` varre o HTML renderizado do `/guia-visual` e
falha se qualquer `<input>`, `<select>` ou `<textarea>` estiver sem `<label for>`.
Somam-se: foco visível em tudo que recebe foco, link "pular para o conteúdo" como
primeiro focável com `<main tabindex="-1">`, tabela larga rolável e alcançável pelo
teclado (`role="region"` + `tabindex="0"`), alvo de toque de 44px, e
`prefers-reduced-motion` desligando transição.

### Pendente da etapa 06

- ~~**`/` ainda é o `welcome.blade.php` do scaffolding.**~~ Resolvido na etapa 07: a
  home é o comparador, e o `welcome.blade.php` saiu do repositório.
- **Navegação e links de rodapé são vazios por padrão** — as páginas das etapas 08 a 10
  entregam os seus. Link morto no cabeçalho é pior que cabeçalho sem link.
- ~~**Nenhum logo de marca no guia visual**~~: resolvido na etapa 15 — a busca de
  imagem por URL no painel, com aprovação humana antes de gravar.

## O comparador (etapa 07)

A home é o comparador. Não há página de entrada antes dele: quem chega pelo
vídeo quer a conta, e uma tela intermediária só custaria um clique.

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/ComparadorController` | Só `stat` no JSON, para a data do rodapé. Zero consulta ao banco |
| `resources/views/comparador.blade.php` | As quatro perguntas e os blocos de resultado |
| `resources/views/components/resultado-comparado.blade.php` | O cartão dos estados de número único |
| `resources/views/components/detalhe-do-resultado.blade.php` | A conta aberta, dentro de um `<details>` |
| `App\Motor\ResumoDoComparador` | Os quatro números da tela, derivados do motor |
| `resources/js/comparador/resumo.mjs` | O gêmeo em JavaScript |
| `resources/js/comparador.js` | O componente Alpine: cenário, cálculo e URL |
| `resources/js/comparador/segmentos.mjs` | Os presets de mix por segmento |
| `resources/js/comparador/estado.mjs` | O estado na barra de endereços |
| `scripts/verifica-resumo-js.mjs` | Compara as duas implementações do resumo |
| `tests/Feature/Comparador/ParidadeDoResumoTest.php` | Paridade + os quatro números conferidos à mão |
| `tests/Feature/Comparador/PaginaDoComparadorTest.php` | Ordem das perguntas, regra 9 e rótulo em todo campo |
| `tests/Support/CenariosDeBorda.php` | Os casos de borda da etapa 05, usados pelos dois testes de paridade |

**Alpine.js, e nada além.** Entrada própria no Vite (`resources/js/comparador.js`),
para que Alpine e os dois motores não pesem nas páginas das etapas 08 a 10. O
bundle da página fica em ~28 KB comprimidos, servidos do próprio domínio.

### A ordem das quatro perguntas é decisão de produto

1. **Faturamento mensal** — o único número que todo lojista sabe de cabeça.
2. **Mix de vendas** — o que de fato decide o resultado: a mesma marca ganha ou
   perde conforme se venda mais em débito ou mais em parcelado. Entra por botão
   de segmento (padaria, salão, loja de roupas, food truck, feira, delivery,
   oficina, outro), com os controles finos atrás de um `<details>`.
3. **Prazo de recebimento** — a quinta dimensão da chave da regra 1. O padrão é
   "tanto faz", que vira `prazo: null` e deixa o motor escolher o mais barato.
4. **Marcas**, ou **"Escolha por mim"**, que seleciona todas e rola até o
   resultado.

`PaginaDoComparadorTest` cobra essa ordem no HTML: trocar duas seções de lugar
quebra o teste, e é para quebrar.

### Os presets de segmento não são dado verificado, e a tela diz isso

Os mixes de `segmentos.mjs` não têm `fonte` nem `data_verificacao`, e não têm
porque não existe pesquisa pública de mix de meios de pagamento por segmento na
granularidade que a tela pede. São ponto de partida plausível, declarado na
própria tela como palpite editável.

A distinção importa porque as duas coisas se parecem e são opostas: **a regra 6
vale para taxa, que é afirmação nossa sobre a marca; o mix é o lojista
descrevendo o próprio negócio**, e o único remédio possível é deixar os
controles à mão. Taxa errada é risco de CDC; mix errado é o usuário se
descrevendo mal.

A soma dos quatro percentuais é menor que 100 de propósito — o resto é dinheiro
em espécie, que não passa na maquininha e não custa taxa nenhuma. O resultado
mostra os dois valores lado a lado, senão a "taxa efetiva" pareceria incidir
sobre o faturamento inteiro.

**O slider para no espaço que sobrou** em vez de deixar a soma passar de 100%.
As alternativas eram piores: passar de 100 apaga o resultado inteiro num
arrasto, e reequilibrar as outras faixas sozinho mexeria em números que a pessoa
não pediu para mexer. Para subir uma faixa é preciso baixar outra — que é a
verdade do problema. (Uma URL montada à mão ainda pode trazer soma inválida; aí
a tela avisa e não calcula.)

**Visa e Mastercard começam em 100%**, e não num número "mais realista". O grupo
de bandeiras é dimensão da chave da regra 1 e a SumUp não publica taxa fora
dessas duas. Chutar 15% em "demais bandeiras" jogaria marcas para o bloco "falta
dado" por causa de um número que o lojista não informou. Quem vende bastante em
Elo ou Amex ajusta no controle fino, com o aviso do lado explicando o efeito.

**O ticket médio existe para virar quantidade de transações**, que o motor
precisa quando a taxa cobra valor fixo por venda. Ticket zerado devolve
quantidade nula — o estado honesto de "não informei" —, e aí o motor declara a
falta em vez de estimar.

### Os quatro números da tela

`App\Motor\ResumoDoComparador` é camada separada, e não mais campos dentro de
`MotorDeCalculo`: o motor responde "quanto custa", que é pergunta de domínio;
o resumo responde "como esse custo se lê na tela". Separado, o motor da etapa 05
continua sendo a fonte da verdade e nenhuma mudança de layout mexe nele.

| Número | Conta |
|---|---|
| Custo mensal recorrente | `total mensal − adesão amortizada` — o que continua saindo do caixa depois que a adesão for paga |
| Taxa efetiva combinada | `custo mensal recorrente ÷ volume vendido × 100` |
| Custo inicial | a adesão, **sem cupom e com cupom** (regra 5) |
| Quanto sobra no mês | `faturamento − total mensal` |

**A taxa efetiva vem em duas.** A combinada tem mensalidade e aluguel dentro,
porque eles saem do caixa do mesmo jeito; ao lado dela sai
`taxa_efetiva_das_vendas`, só o percentual das taxas. Na Alfa do catálogo
sintético as duas coincidem; na Epsilon, que cobra R$ 49,90 de mensalidade e
tem o menor percentual do catálogo, a combinada é mais que o dobro da outra.
**Um comparador que mostrasse só a das vendas apontaria para a Epsilon.**

**Quanto sobra usa o total, e não o recorrente**, porque a adesão é dinheiro que
sai de verdade no primeiro ano. O horizonte da amortização anda junto no
resultado, então o divisor nunca fica escondido.

**O nome de cada chave muda com o estado**, exatamente como no motor:
`custo_mensal_recorrente_promocional`, `_parcial`, `_promocional_parcial`, e as
três pontas (`_minimo`/`_mediana`/`_maximo`, com o gênero certo em
`taxa_..._minima` e `sobra_..._minima`) na faixa reportada. Quem exibe é
obrigado a olhar para o estado antes de achar a chave. `campo()` e `campoTexto()`
no componente Alpine resolvem o sufixo — e **devolvem nulo em faixa reportada de
propósito**: lá o sufixo é `_faixa`, que não existe como chave, e não há caminho
por onde uma mediana de relatos escorregue para o lugar de um número publicado.
Um total parcial ainda ganha "— parcial" colado no rótulo na tela.

### Faixa reportada tem markup próprio, não uma variante do cartão

O bloco de `faixa_reportada` é escrito à parte, com borda tracejada, tom
`reportado`, as três pontas lado a lado, o número de relatos (intervalo quando
as linhas divergem — média de relatos seria mais um número inventado) e o aviso
de que o preço depende de negociação. Reaproveitar o cartão dos outros estados
com um `x-if` a mais seria abrir a porta para a mediana aparecer onde deveria
estar uma taxa publicada.

Melhor e pior saem só do bloco `calculado`, e o veredito do topo só aparece com
duas marcas ranqueáveis ou mais.

### O estado mora na URL

O lojista tem de poder mandar o resultado no WhatsApp. Chaves curtas e valores
legíveis (`?f=10000&s=padaria&mix=34-20-2-19`), número cru com ponto decimal (a
URL não é texto de tela — regra 11 vale para o que se digita e para o que se lê),
e só entra o que difere do padrão *daquele segmento*: `?s=oficina` sozinho já
significa a oficina inteira. A lista de marcas usa `*` para "todas", que é o que
"Escolha por mim" deixa — assim o link continua valendo quando uma marca nova
entrar no catálogo, em vez de congelar as nove de hoje.

### Regra 9, cobrada e não só prometida

`PaginaDoComparadorTest::test_a_pagina_nao_consulta_o_banco` liga o query log e
falha se qualquer consulta escapar. A carga chega em pico de vídeo: uma consulta
que se infiltre ali só apareceria no pior dia possível. O único toque no disco é
um `stat` no JSON, para a data do rodapé e para a página saber avisar quando o
arquivo não foi gerado (o estado normal de um clone novo — `/public/dados` está
fora do Git).

### Strings do motor que passaram a sair na tela

Mensagens que antes só apareciam em teste agora vão para o lojista, e três
carregavam identificador de banco:

- `VendaDoCenario::rotulo()` recebe a dimensão de grupos e imprime "Débito (Visa
  e Mastercard)" em vez de "Debito (visa_master)". Sem a dimensão em mãos, o
  código continua sendo o melhor que dá para dizer.
- O aviso de mistura de prazos e a falta de taxa citam o `nome_exibicao` do
  prazo, não o código: "no prazo Em 30 dias", não "no prazo d_30".
- `TipoOperacao::getLabel()` ganhou acento — vale também para o painel.
- `DimensoesSeeder` acentuou `nome_exibicao` e `descricao` das dimensões
  curadas. Comentário de código no projeto segue sem acento; **texto que vai
  para a tela, não.**

As três primeiras mudam a saída dos dois motores ao mesmo tempo, e os testes de
paridade cobram isso.

### Acessibilidade

`PaginaDoComparadorTest` varre o HTML e exige rótulo em todo controle —
inclusive nos que o Alpine cria depois: `<input>` com `id` fixo precisa de
`<label for>`; com `:id` ligado, precisa do `:for` equivalente. Somam-se: botão
de segmento com `aria-pressed`, `<output for>` em cada slider, alvo de toque de
44px, tabela de detalhe rolável e alcançável pelo teclado, e um `role="status"`
curto que anuncia só o vencedor — a tela inteira não pode ser `aria-live`, ou
cada arrasto de slider viraria ruído.

### Pendente da etapa 07

- **Só a InfinitePay fecha a conta hoje.** Com a carga da etapa 04, um cenário
  padrão devolve 1 marca em `calculado`, 1 em `promocional`, 3 em `incompleto` e
  7 em `sem dado publicado`. Não é bug do comparador: é a lista de campos
  pendentes ao fim da etapa 05 aparecendo na tela. Enquanto Ton e PagBank não
  tiverem `mensalidade` e a SumUp não tiver preço de aparelho, o bloco ranqueado
  fica com uma marca só — e sem duas marcas não há melhor nem pior.
- **Nada publicado (regra 10).** O JSON de hoje é gerado com `--rascunhos`, e a
  página carimba um aviso vermelho quando lê `contem_rascunhos`. A aprovação em
  lote no painel é o que destrava o lançamento.
- **Faixa reportada só foi vista com dado sintético**, porque não existe linha
  em `faixas_reportadas` (etapa 10). O bloco foi conferido injetando uma faixa
  no catálogo já carregado no navegador.
- **Sem logo de marca no resultado.** Entra quando o cartão do comparador
  ganhar espaço para isso — não é uma pendência de dado, é de layout.
  ~~O cupom que o motor aplicou não levava a lugar nenhum.~~ Resolvido na
  etapa 09: o código do cupom no cartão agora é link para `/cupom/{slug}`.
- **Navegação do cabeçalho continua vazia**, porque as outras páginas ainda não
  existem. Link morto é pior que cabeçalho sem link.
- **Produtos "celular como maquininha" continuam fora**, como a etapa 04 deixou.
  A pergunta de tratá-los como maquininha ficou para quando houver página de
  marca onde eles caibam.

### Revisão do comparador (etapa 19, 17/09/2026): valor na maquininha, mix sempre 100%

Pedido do Everton durante a etapa 19 (lançamento), depois de testar a tela com
dado real: a pergunta 1 media "faturamento", mas o motor só cobra taxa do que
passa na maquininha, e a tela deixava implícito que dinheiro/boleto/etc. eram
descontados de algum jeito. Mudanças, todas em `resources/js/comparador.js`,
`resources/js/comparador/{segmentos,estado}.mjs` e `comparador.blade.php` —
**nenhuma tocou `motor.mjs`/`resumo.mjs` nem o PHP gêmeo**, os dois testes de
paridade continuam passando sem alteração:

- **Passo 1 pergunta "Quanto você vende (ou venderá) por mês na maquininha?"**
  em vez de "fatura por mês". O campo passou a significar literalmente o que
  o motor usa: 100% dele é volume vendido na maquininha (cartão + Pix), sem
  supor parcela em dinheiro. As duas linhas "Passa na maquininha" / "Em
  dinheiro, sem taxa nenhuma" saíram — ficariam sempre redundantes com o
  próprio campo.
- **Passo 2: os quatro percentuais (débito, crédito à vista, crédito
  parcelado, Pix) somam sempre 100.** Não existe mais "o que sobra é
  dinheiro". `ajustarMix()` foi reescrito: mexer numa faixa redistribui as
  outras três **proporcionalmente entre si** no espaço que sobrou (a última
  absorve o resto do arredondamento, para a soma nunca escapar de 100). Os
  oito presets de segmento em `segmentos.mjs` foram renormalizados
  proporcionalmente para somar 100 (antes somavam entre 75 e 97, com o resto
  sendo "dinheiro").
- **A caixa "Ajustar o mix..." não fecha mais sozinha ao trocar de segmento.**
  Antes `x-bind:open` era amarrado a `! mixIgualAoSegmento`, que vira `true`
  assim que um preset é aplicado — abrir a mao e trocar de botão fechava a
  caixa sem pedir. Agora é um estado próprio (`detalhesMixAbertos`), só muda
  no clique do `<summary>` (`x-on:toggle`).
- **Passo 3: o seletor de prazo só lista prazos que alguma marca de fato
  oferece hoje.** `listaDePrazos` varre `catalogo.marcas[].planos[].taxas[]`
  coletando os `prazo` usados e filtra a lista fixa de dimensões da regra 1 por
  esse conjunto — hoje, em produção, é só `na_hora` e `d_1`; `d_30` e
  `parcela_a_parcela` existem como dimensão da chave mas nenhum plano
  publicado os usa, e uma opção que nunca muda nada só confundia.
- **Passo 4: nenhuma marca vem marcada de fábrica** (`marcas: []`, antes era
  `TODAS_AS_MARCAS`). Só "Escolha por mim" marca todas. As marcas parceiras
  aparecem primeiro, nesta ordem fixa: Ton, Mercado Pago, FacilityPay,
  SidePay, TrincaPay, Yelly, PagBank — depois as demais, na ordem que o
  catálogo já trouxer (`ORDEM_PARCEIROS` em `comparador.js`). Cada marca
  mostra o **logo** em vez do nome; o nome só aparece se não houver
  `logo_url` ou se a imagem falhar ao carregar (`x-on:error`) — o `<img alt>`
  mantém o nome para leitor de tela nos dois casos. O seletor "Considerar
  cupons de desconto" saiu do "Detalhes da conta" e mora agora no passo 4,
  junto da lista de marcas.
- **"Detalhes da conta" (saques, TEDs, Pix enviados, antecipação e horizonte
  de diluição) saiu da tela inteira.** Nenhuma marca cadastrada cobra
  antecipação extra hoje, e saques/TEDs/Pix enviados nunca foram um dado que
  o projeto registra. `Cenario::deArray` (PHP e JS) já assume os padrões
  certos (zero, zero, zero, `false`) quando esses campos não vêm no cenário —
  a tela só parou de perguntar, a capacidade continua no motor para quando
  fizer sentido de novo. O horizonte de diluição da adesão ficou **fixo em 12
  meses** (`HORIZONTE_PADRAO` nos dois motores, não mudou), e a adesão passou
  a mostrar sempre as duas pontas — **à vista** (`custo_inicial.com_cupom`) e
  **12x** (`item.formatado.adesao.por_mes`, que com o horizonte fixo em 12 já
  É o valor da parcela) — no card do resultado e no bloco de faixa reportada.
- **Corrigido: "Copiar link deste resultado" não fazia nada em contexto sem
  HTTPS.** `navigator.clipboard` só existe em contexto seguro (HTTPS, ou
  `localhost` exato — um `*.test` do Herd em HTTP não conta), e o catch
  silencioso deixava o botão clicável sem efeito visível nenhum, parecendo
  quebrado. `copiarLink()` agora tenta a Clipboard API e, se não existir ou
  falhar, cai num fallback com `<textarea>` oculto + `document.execCommand
  ('copy')` (descontinuado, mas ainda funciona nos navegadores atuais); se os
  dois falharem, a tela avisa ("Não deu para copiar sozinho...") em vez de
  ficar muda.
- **A URL de compartilhamento ganhou um segundo sinal para "nenhuma marca".**
  Com o padrão agora sendo `marcas: []`, `m=` vazio na URL não podia mais
  significar "parâmetro ausente, use o padrão" (que também é nenhuma marca
  hoje, mas nem sempre foi) — `estado.mjs` usa `m=0` para "nenhuma marca"
  explicitamente, para o link continuar reconstruindo a tela certa mesmo se o
  padrão de fábrica mudar de novo no futuro.

**Conferido:** `php artisan test tests/Feature/Comparador tests/Feature/Motor`
passa (26/27 — o único que falha, `MotorSobreACargaRealTest::
test_o_que_falta_na_carga_aparece_como_falta_e_nao_como_zero`, já falhava
igual antes desta revisão, em `main` sem nenhuma das mudanças acima; não
investigado, fora do escopo). `npm run build` sem erro. Testado no navegador
local (`http://comparador-maquininhas.test`, Herd) com dado real: mix
redistribuindo proporcionalmente, caixa do mix permanecendo aberta ao trocar
de segmento, "0 de 13 marcas selecionadas" de largada, ordem de parceiros
correta, prazo filtrado (confirmado contra o JSON de produção: só `na_hora` e
`d_1` em uso — o `d_30`/`parcela_a_parcela` só apareciam no JSON local, que
tem rascunho divergente de produção), "Copiar link" funcionando via fallback
em HTTP, e custo inicial mostrando à vista e 12x.

### Plano promocional nunca mais é o cartão principal (etapa 19, 17/09/2026)

Pedido do Everton depois de ver o Ton na tela: o plano "Período Promocional"
aparecia com destaque próprio (card "Tabela de entrada, por tempo limitado")
mostrando **R$ 45,54/mês**, enquanto o plano permanente da mesma marca para o
mesmo faturamento (**R$ 235,82/mês** — mais de 5x mais caro) ficava escondido
ou nem aparecia. O motivo dele: um comparador que existe para ajudar a
decisão não pode deixar a marca mais cara parecer a mais barata só porque a
tabela de entrada é chamativa — contraria o propósito da ferramenta.

**A regra nova: o cartão de uma marca é sempre o plano permanente dela.** O
plano promocional nunca mais aparece como cartão próprio ao lado dos outros —
vira um **selo** ("Tem tabela de entrada por tempo limitado") no cartão do
plano permanente da mesma marca, que abre um **modal** com: o motivo e os
dois limites da promoção (dias ou valor processado, o que vier primeiro — já
computado por `motivoDaPromocao()`, sem mudança nenhuma no motor), o plano
para o qual a conta migra depois, a tabela de taxas da promoção
(`item.vendas`) e o custo inicial. O aviso de que a taxa promocional **não
permanece** fica na primeira frase do modal, sempre.

**Exceção, para a regra 4 nunca quebrar:** se a marca não tiver *nenhum*
plano permanente elegível para o cenário (só o promocional foi avaliado), ele
não pode virar um selo sem cartão embaixo — nesse caso, e só nesse caso, ele
continua aparecendo como cartão próprio, na seção "Tabela de entrada... — sem
plano permanente cadastrado".

**Implementação, só na camada de apresentação — nada mudou no motor:**

- `motor.mjs`/`MotorDeCalculo.php` continuam calculando exatamente os mesmos
  itens de sempre (um por plano elegível, promocional incluso) — os dois
  testes de paridade não tiveram nenhuma linha alterada.
- `resultado-comparado.blade.php` ganhou um prop `expressao` (Alpine, opcional):
  antes o componente sempre lia `itensNoEstado($estado)`; agora um chamador
  pode passar uma expressão Alpine própria (`expressao="promocionaisOrfas"`)
  para o bloco de promocionais órfãs, sem duplicar o Blade inteiro.
- `comparador.js` ganhou `promocaoDaMarca(slug)` (o item promocional daquela
  marca, se houver), `promocionaisOrfas` (as que não têm plano permanente
  algum) e o estado do modal (`promocaoAberta`, `abrirModalPromocao()`,
  `fecharModalPromocao()`, `itemDaPromocaoAberta`).
- O selo em si só aparece quando `item.estado !== 'promocional'` — sem essa
  guarda o próprio cartão órfão (que já É a promoção) ganhava um selo
  apontando pra si mesmo; achado e corrigido durante o teste manual.

**De quebra, decluttering pedido junto:** o "— parcial" que repetia em três
`<dt>` do mesmo cartão virou um único selo "Parcial" ao lado do título; e os
quatro parágrafos empilhados (motivo, sucessor, falta dado, avisos), cada um
com sua própria borda inferior, viraram um bloco só com espaçamento interno.
Não mexi nos tokens de tipografia (`--text-miudo` = 13px, `--text-etiqueta` =
11px) — são do design system da etapa 06/14 e valem no site inteiro; se ainda
parecer pequeno depois dessa limpeza, é uma decisão de escala de fonte
maior, não um ajuste pontual desta tela.

### Por que "falta dado" aparece tanto para SidePay e FacilityPay (17/09/2026)

O Everton estranhou ver "falta dado" nessas duas marcas, que o `CLAUDE.md` já
registra como totalmente curadas (117/117 e 78/78 taxas publicadas) — achou
que podia ser um problema só do ambiente local. Conferido direto no
`comparador.json` de produção: **não é.**

- **`mensalidade` é `null` em 100% dos planos das duas marcas, em produção.**
  `custoDaConta()` (`MotorDeCalculo.php:566`) trata mensalidade nula como
  "falta dado: mensalidade do plano" sempre — mesmo que a marca não cobre
  nada, o motor nunca assume zero por conta própria (regra 4: campo em
  branco é honesto, número chutado é risco). Isso quer dizer que **nenhuma
  das duas teve a mensalidade confirmada como R$ 0,00** ainda, mesmo com as
  taxas de venda 100% publicadas — são curadorias diferentes. Seguindo pelo
  painel: `TaxaDivulgadas → [marca] → editar plano`, confirmar que é
  R$ 0,00 (não deixar em branco) resolve.
  `tarifa_pix_recebimento` está na mesma situação nas duas.
- **`Pix` só tem taxa publicada em `na_hora`, em toda marca do catálogo hoje
  — nunca em `d_1` ("Em 1 dia útil").** Faz sentido: Pix liquida na hora por
  natureza, nenhuma adquirente publica "Pix em 1 dia útil". Se o passo 3
  tiver `Em 1 dia útil` selecionado, **toda** marca vai mostrar "falta dado:
  taxa de Pix" — não é specífico da SidePay/FacilityPay, é estrutural
  (registrado aqui como achado, não mexido: mudar o prazo para não afetar
  Pix é decisão de produto que o Everton ainda não pediu).
- **"Equipamento vinculado a este plano" é a exceção que PODE ser só local**:
  em produção as duas marcas têm 3 equipamentos por plano; se isso apareceu
  na tela do Everton, o mais provável é o banco local não ter os mesmos
  vínculos de equipamento que produção (mesma divergência já registrada em
  "Curadoria e validação das taxas", nunca sincronizada de propósito).
- **O aviso "O aparelho T1 está cadastrado sem aluguel mensal: a comparação
  considerou o aparelho como compra." não é novo nem é bug** — é o aviso que
  `custoDoAparelho()` sempre mostrou quando `aluguel_mensal` é nulo; só
  ficou mais visível porque os cartões promocionais saíram do caminho.

Nada disso foi mexido nesta sessão — são achados de curadoria (etapa 17),
registrados aqui para não se perderem na conversa.

## Páginas de marca e listagem (etapa 08)

`/maquininhas` (grade) e `/maquininha/{slug}` (página individual), servidas do
banco — ao contrário do comparador, aqui não vale a regra 9: não há pico de
visita de vídeo nesta rota, e o conteúdo precisa estar no HTML da primeira
resposta para valer alguma coisa em SEO.

| Arquivo | Papel |
|---|---|
| `App\Http\Controllers\MarcaController` | `index()` e `show()`. Monta o `schema` (JSON-LD) e a meta descrição de cada página |
| `App\Http\Controllers\SitemapController` | `/sitemap.xml`, via `DOMDocument` — nunca uma view Blade (ver abaixo) |
| `App\Support\Marcas\ResumoDeMarca` | Os quatro dados do cartão da listagem: mensalidade, prazo mais rápido, faixa de taxa |
| `App\Support\Marcas\TabelaDeTaxasDaMarca` | Um bloco de `<x-tabela-taxas>` por plano, com o rótulo de `VendaDoCenario` (etapa 07) reaproveitado |
| `App\Support\Marcas\VantagensDaMarca` | As frases da seção 6 — só o que um campo do catálogo sustenta |
| `App\Support\Marcas\EconomiaDoCupom` | A economia em reais do CTA |
| `App\Support\Navegacao` | O menu do cabeçalho, agora com "Marcas" — reaproveitado pelo comparador |
| `resources/views/maquininhas.blade.php`, `maquininha.blade.php` | As duas páginas |

### A ordem das oito seções da página individual é decisão de produto

Primeiro o que decide (nota do Reclame Aqui, cupom), depois o que explica
(sobre, taxas, aparelhos, bandeiras, vantagens), por fim o vídeo e o convite
para contratar: cabeçalho → sobre a marca → tabela de taxas → equipamentos →
bandeiras → vantagens → vídeo → CTA. `PaginasDeMarcaTest` cobra essa ordem no
HTML.

### Regra 4 e regra 8 chegaram inteiras nas duas páginas

O cartão da listagem etiqueta a faixa de taxa como "faixa reportada" quando a
marca não publica tabela — nunca o mesmo número de quem publica. E como a
página individual mistura taxas verificadas em datas diferentes numa mesma
tabela ("completa, por plano e prazo"), o selo de frescor do rodapé de
`<x-tabela-taxas>` (que fala de uma data só) não bastava — ele agora é por
linha, um `<x-selo-frescor compacto>` colado em cada número, mantendo o selo
agregado como estava para quem passa `dataVerificacao` na tabela inteira (o
guia visual, com dado de amostra). O componente decide sozinho qual dos dois
mostrar no rodapé: se alguma linha carrega a chave `data_verificacao`, o selo
agregado desaparece de lá (repetir os dois seria a mesma data duas vezes) e só
sobra o link de fonte, quando houver.

### Nota do Reclame Aqui: campo manual, sem raspagem (regra 8)

`marcas.reclame_aqui_nota` já existia desde a etapa 02. A página só lê — não
existe caminho novo de coleta automática, e a seção inteira some quando o
campo está vazio (não vira "0/10").

### A economia do cupom nunca é um percentual solto

Regra 5: o cupom desconta a adesão ou o aparelho, nunca a taxa. Um desconto em
valor já é reais por si só. Um desconto percentual só vira reais quando colado
a um preço de adesão real e verificado (o mais barato entre os equipamentos
que a marca vende hoje — a amarra do cupom a um equipamento específico, se
houver, restringe a busca a ele). Sem um preço para aplicar, `EconomiaDoCupom`
devolve `null` e a tela cai para "use o cupom X na adesão", sem número — o
mesmo instinto da regra 6 aplicado fora da taxa.

### Vídeo do canal: campo novo, vazio por padrão

`marcas.youtube_video_id` (migration de 09/09/2026) guarda só o ID do vídeo,
não a URL inteira — é o que o player embutido (`youtube-nocookie.com/embed/`)
pede. Sem preenchimento no painel, a seção 7 simplesmente não aparece; não há
"espaço reservado" vazio na tela.

### SEO

- **Title e meta description próprios**: o layout já montava `<title>` e
  `<meta name="description">` desde a etapa 06 — a página individual só
  passa `titulo="{$marca->nome}: taxas, cupom e maquininhas"` (o layout cola
  o sufixo do site) e uma `metaDescricao()` que usa a descrição cadastrada
  (truncada a 155 caracteres) ou cai para uma frase genérica com o nome da
  marca, nunca vazia.
- **Canonical**: prop nova em `x-layouts.site` (`:canonical`). Só as páginas
  com conteúdo único e indexável passam algo — o guia visual, por exemplo,
  continua sem.
- **Dados estruturados**: prop `:schema` no mesmo layout, serializada como
  `<script type="application/ld+json">`. A listagem declara `ItemList`; a
  página individual declara `Product`, e só acrescenta `Review` quando a nota
  do Reclame Aqui existe ("onde couber" — regra 6 vale também para uma
  afirmação estruturada: sem dado verificado, sem `Review`).
- **`sitemap.xml`**: `SitemapController` monta o XML com `DOMDocument`, de
  propósito sem passar por uma view Blade — um `.blade.php` começando com
  `<?xml ...?>` corre o risco de o compilador do Blade (que gera PHP) ler
  aquilo como uma tag curta do próprio PHP. Lista a home, `/maquininhas` e
  cada marca **ativa** (`Marca::ativas()`) — uma marca pausada ou
  descontinuada não tem página pública (`MarcaController::show()` também
  filtra por `ativas()`, então a URL dá 404) nem entra no mapa.

### A caixa de "comparar" na listagem é vanilla, não Alpine

O comparador (etapa 07) tem entrada própria no Vite exatamente para o Alpine
e os dois motores não pesarem nas outras páginas. A listagem só precisa
marcar caixas e montar um link — por isso isso mora em `resources/js/app.js`
(carregado em toda página), no mesmo padrão `data-*` de `data-alternar-tema`
e `data-copiar`: `data-selecao-marcas` na raiz, `data-marca-checkbox` em cada
caixa, `data-ir-comparar` no botão. O link montado é `/?m=slug1,slug2` — o
mesmo parâmetro `m` que `resources/js/comparador/estado.mjs` já lê na home
(regra: lista de marcas por slug, `*` para "todas").

## Cupons e rastreamento de cliques (etapa 09)

`/cupons` (listagem consolidada) e `/cupom/{slug}` (uma página por marca,
otimizada para a busca "cupom NOME_DA_MARCA"), mais o log de clique em "usar
cupom" e de cópia de código. Como as páginas de marca (etapa 08), renderiza
do banco — sem pico de vídeo nesta rota.

| Arquivo | Papel |
|---|---|
| `App\Http\Controllers\CupomController` | `index()` e `show()`. Só marca com cupom vigente entra |
| `App\Http\Controllers\EventoCupomController` | `POST /eventos/cupons`, chamado pelo fetch do app.js |
| `App\Models\EventoCupom` | Log de eventos — não é entidade de domínio, não tem CRUD |
| `resources/views/cupons.blade.php`, `cupom.blade.php` | As duas páginas |
| `resources/views/components/bloco-cupom.blade.php` | O bloco reaproveitável (etapa 08), agora com rastreamento |
| `resources/js/app.js` | `rastrearEventoCupom()`, ligada aos cliques de copiar e de usar |
| `App\Filament\Resources\EventosCupom\EventoCupomResource` | Só listagem, para reconciliar com o relatório do parceiro |

### Ordenar por maior desconto é ordenar por reais, nunca por percentual solto

Um cupom de R$ 50 e um cupom de 10% não são comparáveis no número bruto. A
única unidade comum é o quanto cada um economiza em reais — a mesma conta que
`EconomiaDoCupom` já fazia para o CTA da página de marca (etapa 08), agora
reaproveitada em `CupomController::index()`. Um cupom percentual cuja marca
não tem preço de adesão verificado em lugar nenhum não tem como converter:
fica por último, nunca no topo por acaso de ordenação de array — o mesmo
instinto da regra 6 aplicado à ordenação, não só ao número exibido.

### O bloco de cupom aprendeu a rastrear sem deixar de ser opcional

`x-bloco-cupom` ganhou dois props novos, `marcaSlug` e `origem`
(`App\Enums\PaginaOrigemCupom`: `marca` | `cupons` | `cupom_marca`). Com os
dois presentes, o botão de copiar e o de "usar cupom" ganham
`data-marca`/`data-cupom`/`data-origem`, que `resources/js/app.js` lê para
disparar o evento. Sem eles — o guia visual, que só mostra o bloco como
amostra — nada é rastreado, porque não existe cupom de amostra para
reconciliar com relatório nenhum.

**Blade não aceita `@if` dentro da tag de abertura de um componente.** A
primeira tentativa (`@if ($rastreia) data-marca="..." @endif` dentro de
`<x-botao ...>`) quebrou o parser de atributos do compilador de tags —
`ParseError` genérico, longe da linha real. O jeito certo é um atributo
dinâmico que resolve para `null`: `:data-marca="$rastreia ? $marcaSlug :
null"`. `ComponentAttributeBag::__toString()` omite sozinho qualquer atributo
`null`/`false`, e `true` puro vira `atributo="atributo"` — o que já bastava
para o seletor `[data-usar-cupom]` do app.js.

A página de marca (etapa 08) tinha dois botões de "usar cupom" fora do
bloco reaproveitável — um dentro do `x-bloco-cupom` do cabeçalho, outro
montado à mão no CTA final. Os dois precisaram dos mesmos três atributos:
rastreamento é por clique, não por componente.

### O resultado do comparador ganhou link para a página do cupom

Pendência da etapa 07: o cartão do comparador já mostrava "cupom X", mas sem
lugar para ir. Reescrever esse bloco inteiro em Alpine para espelhar
`x-bloco-cupom` pediria um gêmeo em JavaScript a mais (no padrão de
`resumo.mjs`) só para um link — desproporcional ao ganho. A saída mais barata
e honesta: o código do cupom em `resultado-comparado.blade.php` virou uma
âncora para `/cupom/` + `item.marca.slug`, usando o slug que o JSON já
carrega. Sem esse link ser um clique de "usar cupom" (ele leva à nossa
própria página, não ao site da marca com o cupom aplicado), ele não dispara
evento — só o clique de fato na página de destino dispara.

### `eventos_cupom`: log, não entidade de domínio

Uma linha por clique em "usar cupom" ou por cópia de código: `marca_id`,
`cupom_id` (nulo-ao-apagar), `codigo` (uma foto do código no momento do
clique), `tipo_evento`, `pagina_origem` e `created_at`. `codigo` existe
separado de `cupom_id` de propósito — o cupom pode vencer ou ser editado
depois, e a reconciliação do fim do mês precisa do código que o lojista viu
na hora, não do estado atual da linha em `cupons`. Sem CRUD no painel: editar
um evento à mão invalidaria a reconciliação. `EventoCupomResource` só lista,
com filtro por marca, tipo e período.

### O POST de rastreamento não pode travar o lojista

`EventoCupomController` não tem sessão nem autenticação — é telemetria
pública, chamada por `fetch(..., { keepalive: true })` para sobreviver a uma
navegação que já começou (o link de afiliado abre em nova aba, mas o
princípio vale). Marca ou código que não batem mais viram `204 No Content`
sem gravar nada, nunca um erro 500 que apareceria no console do lojista por
um cupom que o admin editou entre o carregamento da página e o clique.
`<meta name="csrf-token">`, novo no layout, é o que permite esse fetch passar
pelo `VerifyCsrfToken` do grupo `web` sem endpoint `api.php` separado.

### O aviso das taxas não podia ficar implícito em lugar nenhum

Pedido explícito, texto exato, em `/cupons` e em cada `/cupom/{slug}`: "As
taxas exibidas aqui são exatamente as mesmas do site oficial de cada marca.
Nosso link não altera sua taxa — só acrescenta desconto na adesão." Isso é
além do texto mais curto que já vive dentro de `x-bloco-cupom` — a frase
pedida é mais explícita e fica em destaque próprio no topo da página, não só
dentro do cartão do cupom.

### SEO da página de cupom

`schema` do tipo `Offer` (não `Product` — o objeto vendido é o desconto, não
a maquininha), com `seller`, `validFrom`/`validThrough` e `price`/
`priceCurrency` só quando `EconomiaDoCupom` consegue calcular um valor em
reais (regra 6 vale para dado estruturado também: sem preço verificado, sem
afirmação de preço). `sitemap.xml` ganhou `/cupons` e `/cupom/{slug}` — este
último só para marca com `whereHas('cupons', fn ($q) => $q->vigentes())`, a
mesma regra que já tira a marca da listagem.

## Metodologia, LGPD e captação de relatos (etapa 10)

`/metodologia`, `/privacidade`, `/termos`, `/enviar-proposta` e o botão
"reportar taxa errada" reaproveitável — as quatro peças de confiança que
faltavam antes do lançamento (etapas 11-13), na mesma lógica das regras 6
("nada sem fonte"), 10 ("nada publicado sem revisão humana") e "campo vazio é
honesto", aplicada agora à própria operação do site.

| Arquivo | Papel |
|---|---|
| `resources/views/metodologia.blade.php` | Coleta, frequência, selo de frescor, por que Cielo/Rede/GetNet/Stone saem como faixa, comissão |
| `resources/views/privacidade.blade.php`, `termos.blade.php` | LGPD: base legal por formulário, dados coletados, direitos do titular |
| `App\Http\Controllers\PropostaController` | `/enviar-proposta`: grava `PropostaRecebida` pendente de revisão |
| `App\Http\Controllers\RelatoTaxaIncorretoController` | O botão "reportar taxa errada", em toda `<x-tabela-taxas>` |
| `App\Support\Antispam\FormularioProtegido` | Honeypot + tempo mínimo — sem CAPTCHA de terceiro |
| `App\Support\Uploads\AnexoDeProposta` | Anexo (foto ou PDF) da proposta, disco privado |
| `App\Filament\Resources\PropostasRecebidas`, `RelatosTaxaIncorreta` | As duas filas de revisão no painel ("Relatos de lojistas") |

### Duas tabelas de staging, nunca publicação automática (regra 10, forma mais forte)

`propostas_recebidas` e `relatos_taxa_incorreta` guardam o que o lojista
relatou, com `status: pendente | revisado`. Nenhuma das duas escreve em
`faixas_reportadas` sozinha — um humano lê no painel, decide, e cadastra a
faixa a mão, se for o caso. É staging operacional, no espírito de
`eventos_cupom` (etapa 09): não é entidade de domínio, não tem `url_fonte`
nem `data_verificacao`, porque não é afirmação nossa sobre a marca.

**`marcas.aceita_relatos`** decide quem entra no select de `/enviar-proposta`
— coluna editável no painel, não lista travada no código, no mesmo espírito
de `grupos_bandeiras`/`prazos_recebimento` (dimensão é linha). Semeada `true`
só para Cielo, Rede, GetNet e Stone na migration, mas uma marca nova que
comece a receber relato de lojista é um toggle no `MarcaForm`, não uma
migration.

### Sem CAPTCHA de terceiro (pedido explícito)

`FormularioProtegido::pareceAutomatizado()` combina duas checagens, e as
duas resultam no mesmo sucesso silencioso sem gravar nada — o bot não
aprende qual regra o pegou:

- **Honeypot**: um campo (`confirmar_contato`) fora da tela via `sr-only` +
  `tabindex="-1"` + `autocomplete="off"`, nunca `display:none` — um bot que
  ignora CSS ainda o vê e preenche.
- **Tempo mínimo**: um `carregado_em` (timestamp Unix) gravado pelo Blade no
  render; menos de 3 segundos até o submit é rápido demais para um humano
  preencher o formulário.

Some-se a isso `RateLimiter::for('propostas', ...)` (5/hora por IP) e
`for('relatos-taxa', ...)` (10/hora), registrados em
`AppServiceProvider::boot()`.

### `ImagemSeguraWebp::salvar()` passou a receber caminho, não o upload do Livewire

A assinatura mudou de `TemporaryUploadedFile $file` para `string
$caminhoAbsoluto` — tudo que o método usava era `$file->getRealPath()`. Isso
deixou o método reaproveitável fora do Filament: `App\Support\Uploads\
AnexoDeProposta`, que lida com upload HTTP comum (`Illuminate\Http\
UploadedFile`, não `TemporaryUploadedFile`), chama o mesmo método em vez de
duplicar a conversão para WebP. `AnexoDeProposta` aceita também
`application/pdf`, guardado como está, e salva no disco **`local`**
(privado) — a proposta que o lojista recebeu é documento de negócio dele,
sem motivo para ganhar URL pública adivinhável.

### Banner de cookies: o mecanismo está pronto, o provedor ainda não

`<x-banner-cookies>` grava a escolha em `localStorage`
(`consentimento_cookies: aceito | recusado`) e só chama
`carregarAnalytics()` depois do "Aceitar" — ou de cara, numa visita nova,
quando o aceite já tinha sido dado antes (isso não fere "só depois do
aceite": o consentimento já existe). `carregarAnalytics()` lê a meta
`ga4-id`, que só existe no HTML quando `config('services.ga4.id')` (env
`GA4_MEASUREMENT_ID`) está preenchida — hoje não está, então nada carrega. O
link "Gerenciar cookies" no rodapé (`Navegacao::rodape()`) limpa a escolha e
reabre o banner.

### `Navegacao::rodape()` virou o padrão de `linksRodape`

Pendência da etapa 06 ("navegação e links de rodapé são vazios por
padrão"): `site.blade.php` agora resolve `$linksRodape ??=
\App\Support\Navegacao::rodape()` quando a página não passa o próprio — as
oito páginas de antes (comparador, marcas, cupons, guia visual) ganharam os
links institucionais sem precisar editar cada uma.

### Bug de vazamento de PHP cru em atributo de componente Blade

`:valor="old(\"taxas.$i.parcelas\", ...)"` — a aspa **escapada** dentro do
atributo `:valor="..."` quebrou o parser de atributos do compilador de tags
do Blade, e tudo depois da primeira `\"` vazou como texto literal na página
(`parcelaMinima())" min="2" ...`). Encontrado só na verificação visual no
navegador — nenhum teste `assertSee` pega vazamento de código, só
`assertDontSee` explícito. Corrigido trocando por concatenação
(`old('taxas.'.$i.'.parcelas', ...)`), sem aspa escapada nenhuma. Mesma
família do bug documentado na etapa 09 sobre `@if` dentro de tag de
abertura de componente: o compilador de tags do Blade não é um parser de
PHP completo, e span de aspas dentro de um atributo é outro ponto cego dele.

### Pendente da etapa 10

- ~~**Nome final da ferramenta, domínio de produção, identificação do
  controlador e e-mail de contato de `/privacidade`**~~ — **decididos na
  etapa 11.** A ferramenta é o **Máquina Certa**, em
  `https://maquinacerta.com.br`. `config('services.legal.*')` deixa de ser
  vazio: `LEGAL_RAZAO_SOCIAL="Monetizando - Marketing e Consultoria Ltda"`,
  `LEGAL_CNPJ=40.986.808/0001-04`, `LEGAL_EMAIL_CONTATO=contato@maquinacerta.com.br`.
  Os três vivem só no `.env` de produção — o `.env.example` continua com os
  campos comentados, e em local as páginas seguem mostrando o aviso honesto
  de "a definir".
- ~~**Logo e identidade visual próprios do Máquina Certa ainda não existem.**~~
  **Resolvido na etapa 14** — ver a seção própria. Histórico: decisão do Everton na etapa 11: a identidade específica da marca será
  criada depois, e o front-end (paleta, tipografia, componentes da etapa 06)
  será ajustado a ela **no fim do projeto**, não agora. Até lá o portal vai
  ao ar com o design system genérico da etapa 06, que é funcional e passa no
  contraste WCAG AA (`node scripts/verifica-contraste.mjs`). Quando a
  identidade chegar, o ponto de entrada é a paleta de tokens em
  `resources/css/app.css`: os componentes só citam classes semânticas
  (`text-tinta`, `bg-papel`, `border-contorno`), nunca um hexadecimal, então
  trocar a marca é mexer no valor do token — não na view. O que a troca
  provavelmente pede além disso: tipografia (as fontes entram no bundle, ver
  etapa 06) e a direção "Boletim" descrita no cabeçalho do próprio app.css,
  que é escolha de design, não de código.
- **`GA4_MEASUREMENT_ID` vazio** até a etapa de lançamento — o gate do
  banner de cookies já funciona, só falta o ID.
- **O resultado do comparador (Alpine sobre o JSON estático, regra 9) ainda
  não tem o botão "reportar taxa errada"** — só as páginas de marca, por
  ora. Dar o mesmo botão lá pede um fetch dentro do componente Alpine; fica
  para quando essa tela ganhar espaço (mesmo texto de pendência já usado
  para "logo de marca no resultado" na etapa 07).



## Deploy, backup e producao (etapa 11)

O portal foi ao ar em `https://maquinacerta.com.br` — Hostinger Cloud Startup,
conta `u835756808`, atalho SSH `comparador` no `~/.ssh/config` do Mac.

```
~/domains/maquinacerta.com.br/
├── DO_NOT_UPLOAD_HERE
├── comparador/                    a aplicacao — IRMA de public_html, nao filha
│   ├── .env                       600, nunca versionado
│   ├── deploy.sh
│   ├── public/                    a unica pasta servida pela web
│   └── scripts/backup-comparador.sh
├── public_html  ->  comparador/public
└── public_html.wordpress-2024     o site antigo, renomeado e nao apagado

~/.comparador-backup.cnf           credenciais do MySQL, 600
~/backups/comparador/              os backups, diretorio 700
```

**A aplicacao fica dentro da pasta do dominio, e isso e convencao da conta, nao
exigencia tecnica.** A conta tem 16 dominios; `monetizando.com.br/tubemaster`
ja usava esse arranjo — o app como irmao de `public_html` dentro da pasta do
dominio. Manter o comparador em `~/comparador` teria a mesma propriedade de
seguranca (fora da raiz web nos dois casos), mas sairia do padrao e custaria
caro na hora de achar o projeto um ano depois. Por isso mudou de lugar na
etapa 11, depois de ja estar no ar.

Nem tudo mudou junto, e o motivo esta escrito em `scripts/backup-comparador.sh`:
`~/.comparador-backup.cnf` fica no home porque e credencial da conta, nao do
codigo; e `~/backups/` fica **fora** de `~/domains` porque, se o dominio for
removido ou reatribuido no hPanel, a arvore `domains/<dominio>` vai junto — e o
backup precisa sobreviver exatamente ao dia em que isso acontece.

Os dois scripts **deduzem a raiz da propria posicao** (`dirname
"$BASH_SOURCE"`), nunca de um caminho fixo. Foi o que permitiu mover o projeto
sem editar uma linha deles — e e o que vai permitir mover de novo.

### Use `/opt/alt/php84/usr/bin/php`, nunca o `php` do PATH

O `/usr/bin/php` da Hostinger e 8.3.33 e tem **`proc_open`, `exec`, `shell_exec`,
`symlink` e `popen` desabilitados**. As consequencias sao concretas, nao
teoricas: `composer install` morre no `post-autoload-dump` (que chama
`artisan package:discover` e `filament:upgrade`) por falta de `proc_open`, e
`artisan storage:link` morre por falta de `symlink` — e sem esse link os logos
de marca e as fotos de equipamento somem do site, porque os models os servem
por `Storage::disk('public')->url()`.

`/opt/alt/php84/usr/bin/php` e 8.4.19 (praticamente o 8.4.23 do Herd local) e
nao tem funcao desabilitada nenhuma. O `deploy.sh` o fixa numa variavel no topo
e nunca chama `php` solto. A versao **web** e outra configuracao, feita por
dominio no hPanel (Avancado -> Versao do PHP), e tambem esta em 8.4.

Extensoes conferidas e presentes: `bcmath ctype curl dom fileinfo filter gd
hash iconv intl json mbstring openssl pcre pdo pdo_mysql session simplexml
tokenizer xml xmlwriter zip zlib exif`. **GD com suporte a WebP**, que e o que
`ImagemSeguraWebp` exige. Nao ha `sodium` nem `opcache` no CLI, e nenhum dos
dois e usado.

### So `public/` tem URL

`public_html` e um **symlink** para `comparador/public`. Nem `.env`, nem
`vendor/`, nem `app/`, nem `storage/` estao debaixo de diretorio servido, entao
nao existe URL que os alcance. Conferido de fora depois de cada mudanca de
caminho: `/.env` da 403; `/vendor/autoload.php`, `/composer.json`,
`/app/Models/Marca.php`, `/deploy.sh` e `/scripts/backup-comparador.sh` dao
404.

Vale como contraste dentro da propria conta: o cron do gestorprisma aponta para
`public_html/cron/backup_db.php` — um script de backup **dentro** da raiz web,
disparavel por qualquer um pelo navegador. Nao e deste projeto, mas e o erro
que este arranjo evita.

O `public_html` anterior (um backup de WordPress de 2024) foi **renomeado**
para `public_html.wordpress-2024`, nao apagado. O `.well-known` que estava
dentro dele foi copiado para `public/` antes da troca — e por onde o Let's
Encrypt valida o certificado.

### O site publico esteve fechado (`SITE_EM_BREVE`) — reaberto em 17/09/2026

O portal ficou em producao e **fechado ao publico** de 11/09/2026 a
17/09/2026: toda rota de `routes/web.php` respondia **503** com
`Retry-After` e uma pagina "em breve" marcada `noindex`. O `/admin` continuou
de pe o tempo todo — e por ele que as taxas foram aprovadas enquanto o site
esperava a identidade visual (etapa 14) e a curadoria (etapa 17).

`App\Http\Middleware\SiteEmBreve`, ligado por `SITE_EM_BREVE` no `.env`
(`config/site.php`). **Nao e `artisan down`**, que derrubaria o painel junto.

503 e nao 200 de proposito: um "em breve" servido com 200 e pagina real aos
olhos do buscador — ele indexa, e o dominio aparece com esse texto por semanas.
O 503 se desfaz sozinho quando a flag sair; nao ha `robots.txt` para lembrar de
reverter.

**Reabrir o site e trocar `SITE_EM_BREVE` para `false` e rodar o `deploy.sh`.**
Feito em 17/09/2026, a pedido do Everton, logo depois da trava de aprovacao
de marca entrar no ar (ver "A trava da marca (etapa 19...)") — a decisao
consciente foi abrir com **zero marcas aprovadas ainda** (`comparador.json`
com 0 marcas, 0 taxas) e aprovar direto em producao pelo `/admin/marcas`
dali em diante, em vez de esperar aprovar tudo antes de abrir. Confirmado no
ar: `/`, `/maquininhas`, `/cupons`, `/metodologia` e `/sitemap.xml` todos
`200` (nao mais `503`), tela do comparador renderizando normal (so sem
marca nenhuma pra comparar ate a primeira aprovacao).

### Producao e MariaDB, nao MySQL

O `mysqldump` se identifica como **MariaDB 11.8.9**, e o dump comeca com
`/*M!999999\- enable the sandbox mode */`, marcador que so o MariaDB escreve.
O local e MySQL 8.0.40 (DBngin). As `CHECK constraint` da regra 1 funcionam
nos dois, mas a divergencia esta escrita aqui para ninguem assumir paridade:
tipo JSON, `CHECK` e comportamento de `ENUM` diferem entre os dois motores.

### `deploy.sh`: o que ele faz e o que ele recusa fazer

`ssh comparador '~/domains/maquinacerta.com.br/comparador/deploy.sh'`. Idempotente: rodar duas vezes
seguidas sem nada novo termina em sucesso e nao muda nada.

Quatro decisoes que o ciclo minimo (pull, install, migrate, cache) nao cobre:

- **Todas as guardas rodam antes de o site sair do ar** — `.env` presente,
  arvore sem alteracao local, ramo `main`, PHP e Composer no lugar. Abortar
  com o site no ar e melhor que abortar com ele fora.
- **O modo de manutencao entra antes do `composer install`.** O
  `public/index.php` checa `storage/framework/maintenance.php` **antes** de
  carregar o autoload, entao o aviso continua de pe enquanto o `vendor/` e
  reescrito. Um `trap` em EXIT/INT/TERM garante que o site volta mesmo se o
  script morrer no meio.
- **Confere o manifesto do Vite** e aborta se o bundle chegou ausente ou pela
  metade. Bundle *desatualizado* nenhum script pega — isso e disciplina de
  commitar o `npm run build` junto.
- **`git merge --ff-only`**, nao `git pull`: historia divergida aborta em vez
  de criar um merge no servidor.

**Ele nao roda `db:seed`.** Os seeders da etapa 04 usam `updateOrCreate`, o que
os torna seguros de reexecutar no sentido de nao duplicar — mas "seguro" ali
significa **sobrescrever**. Uma taxa corrigida no painel voltaria ao valor do
arquivo, em silencio. Carga inicial e coisa de uma vez so, feita a mao.

**Ele nao roda `npm run build`**, porque o servidor nao tem Node e nao precisa
ter — ver a nota sobre `public/build` versionado na secao Comandos.

### Armadilha: os assets do Filament sao versionados

`public/js/filament` (28 arquivos), `public/css/filament` (1) e
`public/fonts/filament` (8) estao **rastreados pelo git**. O `composer install`
roda `filament:upgrade`, que os reescreve. Hoje sai byte a byte identico e a
arvore fica limpa — mas **no dia em que o Filament for atualizado**, o servidor
vai reescrever esses arquivos com conteudo novo, a arvore fica suja e a
primeira guarda do `deploy.sh` **aborta o deploy seguinte**.

A saida nao e afrouxar a guarda: e commitar os assets regenerados junto do
`composer.lock`, o que acontece naturalmente se o `composer update` for rodado
em local (onde o `filament:upgrade` tambem roda) e o resultado for commitado.

### Backup: tres artefatos, e o terceiro nao e zelo excessivo

`scripts/backup-comparador.sh`, diario as 03:00 de Sao Paulo, rotacao de 14
dias, verificando o que acabou de gravar.

| Arquivo | Conteudo |
|---|---|
| `banco-*.sql.gz` | `mysqldump` comprimido |
| `arquivos-*.tar.gz` | `storage/app` — os dois discos: `public` (logos e fotos em WebP) e `local` (anexos de proposta, privados) |
| `env-*` | O `.env`, com a `APP_KEY` |

**Por que o `.env` entra no backup.** `users.app_authentication_secret` e os
codigos de recuperacao tem cast `encrypted`, e quem os decifra e a `APP_KEY`.
O 2FA e obrigatorio no painel (`isRequired: true`) e **o app nao envia e-mail**
— nao ha Mailable, nao ha rota de recuperacao de senha. Restaurar o banco sem
a `APP_KEY` original devolve um painel com segundo fator obrigatorio e nenhum
segundo fator legivel, sem "esqueci minha senha" para contornar. Os codigos de
recuperacao mostrados no primeiro login sao a outra metade dessa rede.

**As credenciais nao moram no script** — ficam em `~/.comparador-backup.cnf`,
e o script **recusa rodar** se a permissao nao for exatamente `600`.

**Cada execucao verifica o que gravou**, porque backup que falha em silencio e
o modo de falha classico: `gzip -t` nos dois arquivos, e o dump precisa
terminar com `Dump completed` e ter ao menos um `CREATE TABLE`. Um `.sql.gz`
truncado abre sem reclamar e restaura pela metade.

**Dois bugs encontrados so rodando em producao:**

- **Substituicao de processo nao funciona na Hostinger.** A rotacao usava
  `while read ... < <(find ...)` e falhava com `/dev/fd/63: No such file or
  directory` — depois de gravar o dump e antes de rotacionar. No cron o efeito
  seria pior que na mao: backup gravado todo dia, erro todo dia, e nenhum
  arquivo velho removido nunca. Trocado por pipe simples com `find -delete`.
- **O servidor roda em UTC.** Um cron `0 3 * * *` dispara a meia-noite
  brasileira. O cron correto e **`0 6 * * *`**, e o script exporta
  `TZ=America/Sao_Paulo` para que nome de arquivo e log carimbem a hora que a
  regra 11 manda. Isso nao afeta o dump: o `mysqldump` usa `--tz-utc` por
  padrao.

### Nao ha `crontab` na linha de comando

O binario nao existe neste servidor. O cron e criado no hPanel: **Avancado ->
Cron Jobs**, tipo **Comando**, expressao `0 6 * * *`, linha:

```
/bin/bash /home/u835756808/domains/maquinacerta.com.br/comparador/scripts/backup-comparador.sh
```

`/bin/bash` e nao `/usr/bin/php` porque o script e bash, nao PHP — e neste
servidor `/bin` e symlink para `usr/bin`, entao `/bin/bash` e `/usr/bin/bash`
sao o mesmo binario.

### Restaurar

```bash
# banco (sobrescreve tabela a tabela: o dump traz DROP TABLE IF EXISTS)
gunzip -c ~/backups/comparador/banco-<carimbo>.sql.gz \
  | mysql --defaults-extra-file=~/.comparador-backup.cnf u835756808_comparador

# arquivos enviados (caminhos relativos: cai no lugar a partir da raiz do projeto)
tar -xzf ~/backups/comparador/arquivos-<carimbo>.tar.gz -C ~/domains/maquinacerta.com.br/comparador

# .env, so se a APP_KEY se perdeu
cp ~/backups/comparador/env-<carimbo> ~/domains/maquinacerta.com.br/comparador/.env
chmod 600 ~/domains/maquinacerta.com.br/comparador/.env

# depois de qualquer restauracao, os caches apontam para o estado velho
~/domains/maquinacerta.com.br/comparador/deploy.sh
```

Conferir se o backup rodou:

```bash
ssh comparador 'tail -8 ~/backups/comparador/backup.log; ls -lht ~/backups/comparador/ | head -7'
```

O log termina em `--- fim, ok ---` quando deu certo e em `FALHOU: <motivo>`
quando nao. **Dump que encolhe de repente e sinal de problema mesmo quando o
script diz ok** — por isso o `ls -lht`, que poe os tamanhos lado a lado.

### O 403 que so existe fora do `local` (e mordeu no primeiro login)

`Filament\Http\Middleware\Authenticate` recusa com **403** todo usuario cujo
model nao implemente `FilamentUser` — mas **so quando `APP_ENV` nao e `local`**:

```php
abort_if(
    $user instanceof FilamentUser
        ? (! $user->canAccessPanel($panel))
        : (config('app.env') !== 'local'),
    403,
);
```

As etapas 03 a 10 rodaram inteiras em `local`, entao o painel sempre funcionou
e o defeito ficou invisivel por oito etapas. Ele apareceu no **primeiro login
em producao**, com o sintoma mais confuso possivel: a senha esta certa, a
autenticacao passa, e a resposta e 403.

`User` agora implementa `FilamentUser`. A porta e "existir na tabela `users`",
que e o que ja valia de fato — nao ha rota publica de cadastro, usuario so
nasce por `php artisan make:filament-user`, e o 2FA obrigatorio e a segunda
tranca. **Se a etapa 15 (programa de parceiros) trouxer cadastro publico, todo
cadastrado passa a entrar no `/admin`**, e `canAccessPanel()` vai precisar de
um criterio de verdade — coluna, papel ou lista.

**Por que 130 testes nao pegaram, e o que mudou.** Os testes de admin usam
`Livewire::test($pagina)`, que instancia o componente direto e **nao passa pela
pilha de middlewares HTTP** — o `Authenticate` nunca rodava neles.
`tests/Feature/Admin/AcessoAoPainelTest.php` cobre o caminho que eles pulam:
requisicao HTTP de verdade, autenticada, contra `/admin`. Verificado removendo
a correcao — 3 dos 5 falham com "Expected response status code [302] but
received 403", o mesmo sintoma da producao. O primeiro teste do arquivo guarda
o proprio arquivo: falha se alguem puser `APP_ENV=local` no `phpunit.xml`, o
que transformaria os outros quatro em decoracao.

A licao que vale alem deste bug: **um teste que nunca falhou nao provou nada**.
Ao cobrir um caminho que so quebra em producao, apague a correcao e confira que
o teste fica vermelho antes de commitar.

### Dois defeitos do proprio `deploy.sh`, achados usando-o

- **Ele baixa uma versao nova de si mesmo enquanto roda.** O bash le script por
  deslocamento de byte, entao trocar o arquivo em disco durante a execucao pode
  fazer ele retomar a leitura no meio de uma linha e executar lixo — com o site
  fora do ar e o `trap` possivelmente nunca alcancado. O sintoma leve apareceu
  primeiro: um `export TZ` recem-adicionado nao valeu na execucao que o trouxe.
  O corpo inteiro passou para dentro de um bloco `{ }`, que obriga o bash a ler
  ate o `}` final antes de executar. Conferido que o `trap` continua disparando
  de dentro do bloco.
- **Ele carimbava em UTC.** O backup ja exportava `TZ=America/Sao_Paulo` e o
  deploy nao, entao o log dizia 11:27 para um deploy das 08:27 — e isso levou a
  investigar um erro na hora errada. Os dois scripts agora carimbam no fuso da
  regra 11.

### SMTP, e as traducoes que faltavam atras dele

O app manda **um** e-mail: a recuperacao de senha do painel
(`AdminPanelProvider::passwordReset()`). Em local isso fica em `MAIL_MAILER=log`
e basta. Em producao passou a ser SMTP da Hostinger — o dominio ja tinha MX,
SPF e DKIM publicados, entao faltava so a caixa e a senha.

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps          # Laravel 13 usa MAIL_SCHEME, nao MAIL_ENCRYPTION
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465              # a 25 e bloqueada de saida; 465 e 587 abertas
MAIL_USERNAME=contato@maquinacerta.com.br
MAIL_FROM_ADDRESS=contato@maquinacerta.com.br
```

`MAIL_USERNAME` e `MAIL_FROM_ADDRESS` sao o mesmo endereco porque **a Hostinger
recusa remetente diferente da caixa autenticada**. E a senha entra no `.env`
entre **aspas simples**, pelo mesmo motivo da senha do banco: `$` em valor de
dotenv com aspas duplas vira interpolacao de variavel.

**Trocar o `.env` nao basta: o `config:cache` precisa ser refeito.** Enquanto o
cache nao for reconstruido, `config('mail.default')` continua devolvendo `log` e
o e-mail vai silenciosamente para `storage/logs`, sem erro nenhum.

**O teste real achou o que a configuracao escondia.** O SMTP aceitou a mensagem
na primeira tentativa, mas percorrer o fluxo no navegador mostrou a notificacao
com **titulo em ingles sobre corpo em portugues** — "We have emailed your
password reset link." O Filament embarca `pt_BR`; o Laravel nao. E o corpo do
e-mail de redefinicao saia inteiro em ingles, num site brasileiro.

As traducoes que faltavam, agora versionadas:

| Arquivo | O que cobre |
|---|---|
| `lang/pt_BR/passwords.php` | Os cinco status do password broker (`sent`, `reset`, `throttled`, `token`, `user`) |
| `lang/pt_BR.json` | As frases do e-mail e do layout — o Laravel as pede por chave-frase (`Lang::get('Reset your password')`), nao por chave-de-arquivo |

`tests/Feature/Idioma/TraducoesDoLaravelTest.php` cobre as duas, e um dos testes
guarda o proprio arquivo de traducao: uma chave copiada sem traduzir passaria
despercebida, porque `__()` devolve a chave e nada quebra. Verificado removendo
o `lang/`: 10 dos 12 falham.

Detalhe do layout que vale lembrar: sem `lang/pt_BR.json`, um e-mail em
portugues termina com **"Regards,"** — o tipo de coisa que ninguem revisa depois
que o assunto ja esta certo.

### A armadilha do `ShouldQueue` do Filament

Depois de o SMTP estar funcionando e as traducoes no lugar, o e-mail de
redefinicao **continuava nao chegando**. O que denunciou foi a diferenca entre
dois testes que pareciam equivalentes:

- O teste por `Mail::raw()` para `contato@maquinacerta.com.br` chegou. Mas esse
  endereco e do proprio dominio: a mensagem **nunca sai do servidor**. Ele
  provou a autenticacao SMTP, nao a entrega.
- O fluxo real, para um Gmail, nao chegou — e nao havia erro em lugar nenhum.

A causa: `Filament\Auth\Notifications\ResetPassword` estende a do Laravel e
acrescenta **`implements ShouldQueue`**. Com `QUEUE_CONNECTION=database` e
nenhum worker rodando, a notificacao entrava na tabela `jobs` e ficava la. Sem
excecao, sem log, e com a tela dizendo "Enviamos o link de redefinicao".

Producao ficou em **`QUEUE_CONNECTION=sync`**: o `ShouldQueue` vira letra morta
e a notificacao sai dentro da requisicao. O app nao despacha job proprio
(nenhum `dispatch(` nem `ShouldQueue` em `app/`), entao manter uma fila de
verdade seria pagar manutencao por uma capacidade que ninguem usa — e o worker
que para em silencio e exatamente o modo de falha que acabou de custar uma
hora. Custo aceito: ~1s a mais numa redefinicao de senha, acao rara e de
administrador.

**Revisitado na etapa 13** (monitor de mudanças): o monitor não dispatcha job
nenhum no Laravel — ele é um projeto Node à parte, chamando só dois endpoints
síncronos (`POST /api/monitor/deteccoes`, `GET /api/monitor/resumo-semanal`).
Continua sem trabalho pesado de verdade no `app/`; `QUEUE_CONNECTION=sync` segue
válido.

Duas licoes que valem alem deste bug:

- **E-mail para o proprio dominio nao testa entrega.** Para provar que o
  correio sai, o destino precisa ser externo.
- **Fila sem worker nao falha — ela espera.** Nao ha erro para procurar. A
  unica pergunta que responde e `SELECT COUNT(*) FROM jobs`:

```bash
ssh comparador 'cd ~/domains/maquinacerta.com.br/comparador && \
  /opt/alt/php84/usr/bin/php artisan tinker \
  --execute="echo DB::table(\"jobs\")->count(), PHP_EOL;"'
```

Se isso devolver numero diferente de zero com `QUEUE_CONNECTION=sync`, alguem
mudou a configuracao. Vale virar alerta do Painel Inicial numa etapa futura,
ao lado dos tres que ele ja soma.

### O ensaio da restauracao (feito, nao prometido)

Backup que nunca foi restaurado nao e backup — e um arquivo com nome de
backup. O ensaio foi feito em 11/09/2026, num banco descartavel
(`u835756808_ensaio`, criado e apagado no hPanel), sem tocar em producao. O
usuario de producao nao serve para isso: so tem privilegio no proprio banco e
responde `ERROR 1044` a qualquer `CREATE DATABASE`.

O que foi conferido, do mais fraco ao mais forte:

| Verificacao | Resultado |
|---|---|
| Restauracao completa | 24 tabelas, em menos de 1s, sem erro |
| Contagem de linhas, 24 tabelas | 23 identicas; so `sessions` diverge |
| **`CHECKSUM TABLE` do conteudo** | 23 identicas, incluindo as 964 taxas |
| Indices (nome, coluna, unicidade) | 98 entradas identicas |
| Chaves estrangeiras | 26 identicas |
| `CHECK constraints` | 6 identicas |
| Colunas (tipo, nulidade, padrao) | 245 identicas |
| Chave unica da regra 1 | `taxas_divulgadas_chave_unica` intacta |
| Acentuacao | "Em 1 dia útil", "Antecipação parcial" — `utf8mb4_unicode_ci` |
| Decimais (regra 11) | `1.9900`, `2.9800`, `19.9900` — 4 casas, sem perda |
| Arquivos: binario de 5 KB + texto acentuado | MD5 identico nos dois discos |

**A divergencia de `sessions` e o comportamento correto**, e vale registrar
porque vai reaparecer em todo ensaio futuro: `SESSION_DRIVER=database`, o dump
saiu as 11:31:16, e a sessao a mais em producao tem `last_activity` as
11:35:49 — criada pelas requisicoes de verificacao, **depois** da fotografia.
Um backup e um instante; nao conter o que veio depois dele e o que se espera.

O ensaio dos arquivos comecou fraco e foi refeito: `storage/app` so tem os
`.gitignore` enquanto nenhum logo foi enviado, entao a primeira passada provou
o mecanismo, nao o mecanismo com conteudo. A segunda criou um binario de 5000
bytes de `/dev/urandom` no disco `public` e um texto com acento, cedilha e
`R$ 1.234,56` no disco `private`, rodou o backup, restaurou num temporario e
comparou por MD5. Os arquivos de ensaio foram removidos depois.

### Pendente da etapa 11

- **Os backups moram no mesmo servidor que protegem.** Resolvem "apaguei sem
  querer" e "a migration comeu a tabela"; nao resolvem servidor perdido, conta
  suspensa ou disco morto. Uma copia fora e trabalho de uma etapa propria; o
  minimo viavel hoje e
  `rsync -avz --delete comparador:backups/comparador/ ~/Backups/maquinacerta/`,
  rodado a mao de vez em quando.
- **As 964 taxas estao em rascunho, entao o comparador esta no ar vazio.**
  `comparador:gerar-json` produz um JSON com as 9 marcas em estado
  "sem dado publicado" e nenhum numero. E a regra 10 funcionando, nao um
  defeito — a aprovacao em lote no painel e o que destrava.
- **Aprovar taxa no painel nao regenera o JSON.** O arquivo so e reescrito por
  `comparador:gerar-json`, que hoje roda no deploy. Depois de aprovar em lote,
  e preciso rodar `ssh comparador '~/domains/maquinacerta.com.br/comparador/deploy.sh'` (ou so o comando
  artisan) para o site refletir. Automatizar isso — um botao no painel ou um
  cron — e trabalho da etapa 12 ou 13.
- **A senha do banco de producao foi digitada em texto puro num chat durante a
  etapa 11.** Trocar no hPanel e rodar um deploy resolve, e o custo e um campo
  e um script.
- **O 2FA do painel ainda nao foi configurado.** O usuario existe
  (`make:filament-user`), mas `users.app_authentication_secret` e
  `app_authentication_recovery_codes` estao NULL — ninguem entrou em `/admin`
  ainda. No primeiro login o Filament obriga a configurar, e os codigos de
  recuperacao aparecem **uma vez**. Guardar fora do computador: nao ha
  "esqueci minha senha" (o app nao envia e-mail) e o backup so ajuda se a
  APP_KEY do `.env` for a mesma.

## Cloudflare, medição, SEO, segurança e performance (etapa 12)

Nove painéis, um de cada vez, com o site em produção mas ainda fechado
(`SITE_EM_BREVE=true`). O que segue é o checklist de onde cada coisa mora —
para não depender de memória de sessão daqui a seis meses.

### 1. Cloudflare

Domínio migrado para os nameservers do Cloudflare (plano Free). No painel:

- **DNS**: `A` da raiz e `www` com proxy (nuvem laranja). `ftp`, `autoconfig`,
  `autodiscover` e os três `hostingermail-*` ficaram **Somente DNS** — são
  registros de e-mail/FTP, não do site, e proxied quebrariam (o Cloudflare
  responderia com o IP dele para qualquer protocolo que não seja HTTP/HTTPS).
- **SSL/TLS**: modo **Completo (Estrito)** — o certificado Let's Encrypt da
  origem cobre `maquinacerta.com.br` e `www`, conferido direto no IP antes de
  trocar.
- **Sempre usar HTTPS**: ligado. **HSTS**: ligado, `max-age` de 6 meses, sem
  `includeSubDomains` (os subdomínios de e-mail não têm garantia de HTTPS) e
  sem pré-carregamento (praticamente irreversível).
- **Brotli**: automático nessa conta, sem toggle no painel — confirmado por
  fora (`content-encoding: br` numa resposta 200).
- `bootstrap/app.php` confia nos IPs do Cloudflare como proxy
  (`$middleware->trustProxies(...)`) — sem isso, `Request::ip()` veria o IP
  do Cloudflare para todo mundo, e o rate limit do login do Filament
  (`vendor/filament/filament/src/Auth/Pages/Login.php`, 5 tentativas) ficaria
  quebrado.
- **Regra de taxa** (Segurança → Regras de segurança → Rate Limiting):
  `/admin/login`, 5 requisições / 10 segundos (o plano Free só oferece essa
  janela) → Bloquear por 10 segundos. Segunda camada, de propósito: o
  Filament já bloqueia sozinho no app.
- **Regras de transformação de cabeçalho de resposta** (Regras → Criar
  regra), duas:
  1. `starts_with(http.request.uri.path, "/build/")` → define
     `Cache-Control: public, max-age=31536000, immutable`.
  2. `not starts_with(http.request.uri.path, "/admin")` → define
     `Content-Security-Policy` com o valor completo (ver seção 6) — **a
     Hostinger sobrescreve qualquer CSP que o PHP mande** numa camada depois
     do app (achado testando em produção, não suposto), então a política que
     de fato chega ao visitante vive aqui, não só no middleware.
- **Cache de HTML: decisão consciente de não fazer.** Toda página hoje sai
  com sessão + CSRF token no corpo (o layout embute o token para o
  rastreamento de cupom da etapa 09 funcionar em qualquer página). Cachear
  esse HTML na borda serviria a mesma sessão para visitantes diferentes —
  não é só formulário quebrando, é vazamento de sessão entre pessoas, e
  justamente no pico de tráfego pós-vídeo que o site foi desenhado para
  aguentar. Só ativos estáticos são cacheados (extensão comum + regra acima
  para `/build/`). Revisitar se algum dia o CSRF sair do layout global.

### 2. Google Search Console

Propriedade tipo **Domínio**, verificada por registro **TXT** no DNS
(`google-site-verification=...`, confirmado via `dig`). **Sitemap NÃO
enviado** — toda URL nele daria 503 com o site fechado. Lembrete registrado
no `PLANO.md`, etapa 19: enviar o `sitemap.xml` só no lançamento.

### 3. Bing Webmaster Tools

Importado direto do Google Search Console (mesma verificação, sem TXT
próprio). Sitemap também não enviado, mesmo motivo.

### 4. Google Analytics 4

`GA4_MEASUREMENT_ID` preenchido no `.env` de produção
(`config('services.ga4.id')`), confirmado via `tinker` depois do
`config:cache`. O gate do banner de cookies (etapa 10) já existia; só faltava
o ID.

`window.gtag` virou **global** em `resources/js/app.js` (era função local
dentro de `carregarAnalytics()`) porque o comparador é um bundle Vite
separado (etapa 07) e precisa chamar a mesma função. Só existe depois do
consentimento — todo disparo usa `window.gtag?.(...)`, nunca fila.

Seis eventos:

| Evento | Onde dispara | Arquivo |
|---|---|---|
| `segmento_selecionado` | clique no botão de segmento | `resources/js/comparador.js` |
| `uso_comparador` | 1200ms depois da última mudança no cenário (debounce próprio, separado do cálculo) | `resources/js/comparador.js` |
| `faixa_faturamento` | junto do anterior, faturamento em faixas fixas (`ate_2_mil` … `acima_de_50_mil`) | `resources/js/comparador.js` |
| `clique_cupom` | link do cupom dentro do resultado do comparador (`/cupom/{slug}`) | `resources/views/components/resultado-comparado.blade.php` |
| `clique_afiliado` | botão "usar cupom" (o clique de saída de verdade) | `resources/js/app.js` (`rastrearEventoCupom`) |
| `copia_codigo` | botão de copiar código | `resources/js/app.js` (`rastrearEventoCupom`) |

Os dois últimos reaproveitam o mesmo ponto que já alimenta `eventos_cupom`
desde a etapa 09 — mesmo clique, dois destinos.

**Pendente:** confirmação com tráfego real só depois do lançamento — testado
localmente com ID de teste, nunca em produção (o site fechado impede).

### 5. Microsoft Clarity

`CLARITY_PROJECT_ID` preenchido no `.env` de produção
(`config('services.clarity.id')`), mesmo gate de consentimento do GA4, mesmo
padrão de meta tag (`clarity-id`) em `site.blade.php`. Carrega
independente do GA4 — um site pode ter só um dos dois configurado.

`/enviar-proposta` (`resources/views/propostas/criar.blade.php`) ganhou
`data-clarity-mask="True"` no `<form>` inteiro. O relato já é anônimo por
desenho (sem nome, sem e-mail — achado ao mapear o formulário antes de
mexer), mas faturamento e mensalidade continuam sendo dado de negócio que
não precisa aparecer numa gravação de tela.

**Pendente:** mesma ressalva do GA4 — mapa de calor e gravação só se provam
com visita real, depois do lançamento.

### 6. Cabeçalhos de segurança

`App\Http\Middleware\CabecalhosDeSeguranca`, registrado globalmente em
`bootstrap/app.php`. `X-Content-Type-Options`, `Referrer-Policy` e
`Permissions-Policy` em toda resposta, inclusive `/admin`. A
`Content-Security-Policy` só entra fora do `/admin` — o Filament embute o
próprio Alpine e mexe com estilo inline em vários componentes, e testar
política estrita ali sem a cobertura que `ComponentesDoPortalTest` dá ao
site público arriscava quebrar o painel usado todo dia.

**A política que o visitante recebe de fato vive em dois lugares por um
motivo concreto**: a Hostinger sobrescreve o `Content-Security-Policy` que o
PHP manda (numa camada depois do app, fora de qualquer `.htaccess` do
projeto — achado testando em produção). O middleware continua sendo a fonte
da verdade para o `/admin` (onde não sobrescreve nada, e a CSP simplesmente
não entra) e para o caso hipotético de a resposta do PHP chegar ao navegador
sem passar pela Hostinger; a regra de transformação do Cloudflare (seção 1)
é o que efetivamente chega ao visitante no site público hoje. **As duas têm
que ser mantidas iguais manualmente** se a política mudar — não há
sincronização automática entre `CabecalhosDeSeguranca::politica()` e a regra
do Cloudflare.

Duas exceções na política, achadas testando no navegador (não adivinhando):

- **`'unsafe-eval'` em `script-src`** — o Alpine.js (comparador, etapa 07)
  resolve `x-data`/`x-on`/`x-text` com `new Function()` na build padrão. Só
  a build dedicada `@alpinejs/csp` evita isso, e trocar pediria reescrever
  várias expressões JS direto no Blade sem garantia de paridade. Dívida
  registrada, não decisão final.
- **`'unsafe-inline'` em `style-src`** — `x-show`/`x-transition` do Alpine
  escrevem direto em `element.style` via JS, que CSP também trata como
  estilo inline. Risco bem menor que a mesma permissão em script.

Os dois scripts inline que sobram (flash de tema, em três arquivos; leitor
de hex do `/guia-visual`) são liberados por **nonce por requisição** (
`View::share('cspNonce', ...)`) **e** por **hash SHA-256 fixo** — o hash é o
que sobrevive à sobrescrita da Hostinger/regra do Cloudflare, já que uma
regra estática na borda não tem como carregar um nonce que muda a cada
requisição. Os dois hashes vivem como constantes em
`CabecalhosDeSeguranca` (`HASH_SCRIPT_TEMA`, `HASH_SCRIPT_GUIA_VISUAL`) — se
o conteúdo desses dois scripts mudar um dia, os hashes têm que ser
recalculados **nos dois lugares** (middleware e regra do Cloudflare).

**Testado em [securityheaders.com](https://securityheaders.com): nota A+.**

### 7. Performance

- `php artisan optimize` já rodava no `deploy.sh` (com `optimize:clear`
  antes e `filament:optimize` depois) — nada a mudar.
- **OPcache confirmado ativo no SAPI web** (`opcache.enable=1`, ~1050
  scripts em cache) — testado com uma rota temporária (`/__diag-opcache`),
  removida logo depois de confirmar. O CLI nunca mostra isso, mesmo ligado.
- Imagens: `loading="lazy"` e `decoding="async"` já estavam nas 4 tags
  `<img>` que existem hoje. Dimensão explícita (`width`/`height`) não foi
  adicionada de propósito — o espaço já é reservado por contêineres de
  tamanho fixo (`size-14`, `size-16`, `aspect-4/3`), mesmo efeito prático
  contra CLS, e não há nenhuma imagem real no catálogo ainda (etapa 15).
- `public/.htaccess` + `public/build/.htaccess` (gerado por
  `scripts/gera-htaccess-do-build.mjs`, novo passo do `npm run build` — ver
  por quê na próxima nota) dão cache de longo prazo aos ativos estáticos.
  **`/build/` é reescrito inteiro a cada `vite build`** (o `emptyOutDir` do
  Vite apaga tudo que não veio do build atual): um `.htaccess` colocado lá à
  mão desapareceria no próximo `npm run build` sem aviso. O script recria o
  arquivo depois do build, todo build.
- Medido localmente (Lighthouse, mobile simulado — o domínio real dá 503):
  comparador 96/100 (LCP 2,6s, CLS 0), listagem de marcas 93/100 (LCP 3,0s,
  CLS 0). LCP um pouco acima do ideal nos dois, causa já conhecida: fontes
  são a maior fatia do peso (~410KB de ~550KB, medido na etapa 11).
- **Pendente:** medição real (PageSpeed Insights, CrUX) só depois do
  lançamento. Não vale otimizar fonte agora — a etapa 14 troca as famílias
  tipográficas, e qualquer ajuste de hoje seria refeito.

### 8. UptimeRobot

Dois monitores, e-mail como contato de alerta:

- **Home** (`https://maquinacerta.com.br`): tipo **Keyword**, procura
  `Máquina Certa`, alerta se **não existir**. Escolha deliberada em vez de
  monitor HTTP comum: a palavra aparece tanto na página "em breve" (hoje)
  quanto na home de verdade (depois do lançamento), então **o mesmo monitor
  funciona antes e depois de abrir o site, sem precisar trocar nada** — e
  ainda pega o caso de a página responder 200 em branco, que um monitor de
  status não pegaria.

  **Essa premissa não se sustentou na prática — achado em 16/09/2026.** O
  monitor está "Down" desde a própria criação (14/09/2026), com "Root
  Cause: 503 Service Unavailable" — mesmo a palavra `Máquina Certa`
  aparecendo várias vezes no corpo da página "em breve" (conferido com
  `curl` na hora: status 503, `<title>Máquina Certa — em breve</title>`,
  a palavra 3+ vezes no HTML). O monitor **Keyword** da UptimeRobot
  reprova no status HTTP não-2xx antes de sequer chegar a checar a
  palavra — não é só "existe a palavra ou não", como o texto acima supôs
  sem testar. Enquanto `SITE_EM_BREVE=true` (503 de propósito, ver seção
  "O site público está fechado"), **este monitor vai ficar "Down" o tempo
  todo, e isso é esperado, não uma queda de verdade** — o e-mail de alerta
  pode ser ignorado até a etapa 19. Vale mutar/pausar o monitor até lá, ou
  conferir nas configurações da UptimeRobot se existe opção de aceitar
  status não-2xx num monitor Keyword; se não existir, o desenho certo teria
  sido monitor de Keyword **sem** exigir 2xx, ou dois monitores separados
  (um antes, um depois do lançamento). Reconferir isso na etapa 19, depois
  de `SITE_EM_BREVE=false`: o monitor precisa voltar a "Up" sozinho quando
  a home responder 200 de verdade.
- **Admin** (`https://maquinacerta.com.br/admin/login`): tipo **HTTP(s)**
  comum — essa rota sempre responde 200, site aberto ou fechado.

### 9. rclone — cópia externa do backup

Fecha a pendência registrada na etapa 11: backup no mesmo servidor que ele
protege resolve "apaguei sem querer", não resolve servidor perdido, conta
suspensa ou disco morto.

- **rclone instalado em `~/bin/rclone`** no servidor (binário estático da
  [downloads.rclone.org](https://downloads.rclone.org), sem precisar de
  root). `~/bin` não entra no PATH de sessão não interativa — todo comando
  usa o caminho completo, mesma convenção do PHP (`/opt/alt/php84/...`).
- **Projeto Google Cloud** "Comparador de Maquininhas" (o mesmo já usado
  para outras APIs), com a **Google Drive API** ativada.
- **Cliente OAuth próprio** (tipo "Aplicativo para computador"), não o
  client_id compartilhado do rclone — achado direto na primeira conexão:
  `rclone` avisou que o client_id compartilhado **está sendo desativado
  durante 2026** (o ano corrente). Tela de consentimento OAuth publicada
  (fora do modo de teste), para o token não expirar sozinho em 7 dias.
- **Remoto `gdrive`**, em `~/.config/rclone/rclone.conf` (permissão 600,
  mesma lógica do `~/.comparador-backup.cnf`): `scope = drive.file` — o
  rclone só enxerga a pasta que ele mesmo criou (`backups-maquina-certa`),
  nunca o resto do Drive da conta. `root_folder_id` fixado nessa pasta, para
  todo comando sem caminho (`gdrive:`) cair direto nela.
- `scripts/backup-comparador.sh` ganhou uma seção **offsite**, logo depois
  de gravar o `.env` do dia: copia os três arquivos (`banco-*`, `arquivos-*`,
  `env-*`) para `gdrive:` e aplica a mesma rotação de 14 dias por lá.
  **Falha na cópia externa falha o backup do dia inteiro** (`exit 1`) — o
  dump, os arquivos e o `.env` de hoje já estão intactos no disco local
  quando isso roda, então nada se perde numa falha de rede pontual, só fica
  sem cópia externa naquela rodada, e isso precisa aparecer no log.
- **Testado de ponta a ponta**: rodado o script inteiro uma vez em produção,
  os três arquivos conferidos no Drive via `rclone ls gdrive:`, tamanho
  batendo com o backup local.
- **Restaurar do Drive**: os comandos de restauração da etapa 11 continuam
  valendo para o backup local; se o disco local também tiver sumido, baixe
  primeiro com `rclone copy gdrive:banco-<carimbo>.sql.gz .` (e os outros
  dois arquivos do mesmo carimbo) antes de seguir os passos de restauração
  já documentados.

## Monitor de mudanças (etapa 13)

Pasta `monitor/`, **neste mesmo repositório** — projeto Node.js separado de
propósito (`package.json` próprio, não mistura dependência com o PHP), mas
sem repositório do GitHub à parte: um repositório a mais custaria mais um
lugar para lembrar de checar, mais uma tela de secrets, sem ganho real para
um projeto de uma pessoa só. Os workflows do GitHub Actions ficam em
`.github/workflows/monitor-*.yml` (o GitHub só reconhece esse diretório na
raiz), todos com `working-directory: monitor`. Não alimenta o site nem
escreve em tabela de domínio nenhuma — só detecta quando uma fonte externa
muda e avisa no Telegram. Nada vai ao ar sem aprovação humana no admin
(regra 10, forma mais forte). Ver `monitor/README.md` para detalhe de
execução.

| Arquivo (neste repositório) | Papel |
|---|---|
| `database/migrations/..._create_deteccoes_de_mudanca_table.php` | Staging: uma linha por mudança detectada ou falha de coleta |
| `App\Models\DeteccaoDeMudanca` | `tipo`: mudança \| falha. `status`: pendente \| revisado (reaproveita `StatusRevisao` da etapa 10) |
| `App\Enums\CategoriaFonteMonitorada`, `App\Enums\TipoDeteccaoDeMudanca` | — |
| `App\Http\Middleware\AutenticaMonitor` | Token fixo (`MONITOR_API_TOKEN`) por `hash_equals`, comparado em `routes/api.php` |
| `App\Http\Controllers\Api\MonitorController` | `POST /api/monitor/deteccoes`, `GET /api/monitor/resumo-semanal` |
| `App\Support\Monitor\ResumoSemanal` | Os dois números do resumo semanal — extraído de `PainelInicial`, que agora só chama esta classe |
| `App\Filament\Resources\DeteccoesDeMudanca\*` | Fila de revisão no painel, grupo de navegação "Monitor de mudanças". Sem create — só chega por POST |

### Por que não existe uma tabela "fontes monitoradas" neste banco

A lista de URLs monitoradas e o estado de hash entre execuções (o que
substitui, para um runner efêmero do GitHub Actions, a memória que um
serviço teria) moram **versionados no repositório do monitor** —
`fontes.json` e `estado/*.json`, commitados pela própria Action a cada
execução. A API deste app expõe só dois endpoints, os dois de
escrita/consulta pontual, nunca uma lista de configuração para o monitor
ler. Isso mantém a superfície pública pequena e auditável (dois endpoints,
os dois logados normalmente pelo Laravel) e dá de graça um efeito colateral
bom: `git log -- estado/` no repositório do monitor é o histórico de quando
cada página realmente mudou.

### Por que o monitor decide sozinho o que é "mudança", e o Laravel só registra

`POST /api/monitor/deteccoes` não recebe hash antigo para comparar com hash
novo — recebe o resultado já decidido. A comparação (hash do texto
normalizado contra o hash commitado em `estado/`) acontece inteira no
repositório do monitor, antes de qualquer chamada a este app. O Laravel não
precisa saber o que é uma "fonte", só precisa de `marca_slug` (resolvido
para `marca_id` por `where('slug', ...)`, nunca um ID interno recebido de
fora) para montar o `link_admin` de volta na resposta — o link direto para
`MarcaResource::getUrl('edit', ...)` que vai no alerta do Telegram.

### `ResumoSemanal` existia espalhado, virou uma classe

`App\Filament\Widgets\PainelInicial` já calculava "taxas sem verificação há
+30 dias" e "cupons vencendo em 7 dias" para os dois primeiros cartões do
painel inicial (etapa 03). O resumo semanal do monitor precisa exatamente
dos mesmos dois números — extrair para `App\Support\Monitor\ResumoSemanal`
evitou a mesma conta (e o mesmo "30 dias", que é alerta antecipado do
painel, diferente do selo de frescor de 45 dias da regra 8) escrita duas
vezes em dois arquivos que podiam divergir sem ninguém notar.

### As quatro marcas atrás de Cloudflare/Akamai

Ton, PagBank, Stone e InfinitePay provavelmente bloqueiam o IP de
datacenter do GitHub Actions. O monitor detecta sinais comuns de bloqueio
(HTTP 403/429/503, texto de desafio) e registra isso como **falha
explícita** — nunca como "sem mudança", que esconderia o problema. Cada
fonte tem uma flag `roda_no_mac` em `fontes.json`: onde o bloqueio se
confirmar na prática, a fonte migra para rodar do Mac do Everton (IP
residencial) via cron, com `mac/rodar-fontes-bloqueadas.sh` já pronto no
repositório do monitor — só falta confirmar quais fontes precisam disso.

### O que ficou pendente, sem inventar URL nenhuma (regra 6 vale aqui também)

- **Cielo, Rede, GetNet e Stone, categoria `contrato_credenciamento`:
  resolvido em 14/09/2026.** As quatro URLs foram pesquisadas e testadas
  com o próprio coletor do monitor (não só uma busca — o PDF foi de fato
  baixado e teve texto extraído). Ativas em `fontes.json`, com a fonte e o
  grau de confiança anotados no campo `observacao` de cada uma. A da Cielo
  é a de confiança mais baixa das quatro — o PDF encontrado tem só 3
  páginas, contra 28 (Rede) e 70 (GetNet); vale o Everton abrir uma vez e
  confirmar que é o corpo do contrato, não um trecho. A Stone não usa mais
  o termo "contrato de credenciamento" — a fonte cadastrada é o
  equivalente atual, "Termos Gerais de Contratação".
- **Ton, categoria `tabela_taxas`: pesquisado em 14/09/2026, sem solução
  encontrada.** Não existe página pública com a tabela completa — o
  próprio Ton diz que a taxa é consultada dentro do app, em "Minhas taxas e
  prazos", dependente do faturamento do mês e atrás de login. Continua
  inativa em `fontes.json`. Resolver isso exigiria login automatizado na
  conta do Everton dentro do app, fora do escopo desta etapa.
- **Categoria `equipamento_cupom`: resolvido em 14/09/2026 para 4 marcas.**
  O Everton passou os próprios links de afiliado (com cupom já aplicado) de
  Ton, PagBank, Mercado Pago e InfinitePay — cada um testado com o coletor
  antes de entrar em `fontes.json`, e todos trazem preço/desconto real via
  fetch simples, sem precisar de navegador.
  **Achado no processo, sem relação com o monitor:** os links também
  incluíam Yelly, SidePay, TrincaPay e FacilityPay — quatro parceiras de
  afiliado que **ainda não existem na tabela `marcas`** (só eram citadas
  como exemplo da regra 7). As URLs ficaram guardadas em `fontes.json`,
  inativas, para não se perderem — mas cadastrar essas quatro marcas de
  verdade (adquirente, taxa, etc.) é trabalho de catálogo, não desta etapa.
  Vale decidir em que etapa isso entra.
- **Todos os cinco secrets já foram gerados e conferidos em 14/09/2026:**
  `MONITOR_API_TOKEN` (no `.env` de produção e no secret do GitHub — os
  dois lados testados batendo, `POST /api/monitor/deteccoes` respondendo
  200 em produção), `MONITOR_API_URL`, `TELEGRAM_BOT_TOKEN`,
  `TELEGRAM_CHAT_ID` e `GEMINI_API_KEY` (testada de verdade contra a API do
  Gemini — ver nota abaixo sobre o modelo).
  **`TELEGRAM_CHAT_ID` errado na primeira tentativa**: o Everton colou o
  `update_id` do JSON de `getUpdates` (`218905998`), não o `chat.id`
  (`970486531`) — os dois aparecem perto um do outro na resposta da API do
  Telegram, fácil de confundir. Corrigido no secret do GitHub, e testado
  com `sendMessage` direto (curl, fora do workflow) contra o bot
  `@maquinacerta_bot`: mensagem confirmada recebida pelo Everton.
- **`gemini-2.0-flash`, usado como padrão no código, foi descontinuado.**
  Descoberto testando a chave real do Everton: a API responde 404 pedindo
  a troca para `gemini-3.6-flash`. Trocado o padrão em `src/config.mjs`, e
  `maxOutputTokens` subiu de 300 para 1024 — o modelo novo gasta parte do
  orçamento "pensando" antes de responder (campo `thoughtsTokenCount` no
  retorno da API), e 300 cortava o resumo pela metade. `src/gemini.mjs`
  também ganhou uma segunda tentativa em HTTP 503/429, que apareceu de
  verdade no teste (sobrecarga passageira do modelo gratuito do Google).

### Duas falhas reais em produção, achadas em 16/09/2026 — nenhuma delas era o link da Yelly

O `monitor-diario.yml` (categoria `equipamento_cupom`) falhou nas suas duas
primeiras execuções agendadas (15/09 e 16/09). O diagnóstico inicial, feito
só pelos "Annotations" da tela de resumo da Action (sem login no GitHub,
que não mostra o log completo pra quem não está autenticado), apontou o
link de cupom da Yelly como fora do ar (`HTTP 404`) — **errado**: o Everton
confirmou abrindo a URL no próprio navegador que ela funciona normalmente,
e insistir nisso sem o log de verdade quase virou correção do problema
errado. **Lição que vale além deste bug: antes de diagnosticar uma falha de
CI só pelas annotations, pedir pra quem tem acesso ao repositório abrir o
log completo do passo que falhou** — o resumo trunca exatamente o tipo de
informação (a mensagem de erro real, o passo específico) que muda o
diagnóstico.

**Causa real, achada só depois que o Everton colou o log expandido:** os
quatro workflows (`monitor-diario`, `monitor-bissemanal`, `monitor-semanal`,
`monitor-resumo-semanal`) liam `secrets.MONITOR_API_URL`/
`secrets.MONITOR_API_TOKEN`, mas os secrets no repositório do GitHub existem
com outro nome — `PORTAL_API_URL` e `COLETA_TOKEN` (visto direto na tela
Settings → Secrets and variables → Actions). **Isso contradiz o que este
mesmo arquivo registrava desde 14/09/2026** (bloco logo acima, "Todos os
cinco secrets já foram gerados e conferidos") — o "conferido" da etapa 13
validou que o *valor* do token batia dos dois lados (o `POST` respondeu
200), mas aparentemente nunca conferiu o *nome literal* do secret no
GitHub contra o nome que `src/config.mjs` exige. Resultado: desde que esses
workflows foram criados, `carregarConfig()` falhava direto em
`obrigatoria('MONITOR_API_URL')`, **antes de checar qualquer fonte** — a
"falha" da Yelly nunca chegou a acontecer de verdade nessas duas execuções,
porque o script morria antes de chegar nela. Corrigido trocando só o lado
direito do `env:` nos quatro `.yml` (`secrets.PORTAL_API_URL`,
`secrets.COLETA_TOKEN`), sem tocar no nome da variável que o script recebe
— então `src/config.mjs` não mudou. **Não renomeie os secrets no GitHub sem
atualizar os quatro workflows junto**, e vice-versa.

**A causa real da Yelly, uma vez que o script passou a rodar de verdade:**
o checkout dela virou um app React renderizado no cliente, e o CloudFront/S3
que serve o site devolve HTTP 404 pra essa URL mesmo entregando o HTML/JS
reais, que funcionam certinho no navegador (confirmado batendo o coletor
via Playwright contra a mesma URL — texto renderizado idêntico ao que o
Everton viu, status 404 nos dois). É config malfeita do lado da Yelly
(erro customizado do CloudFront sem sobrescrever o status pra 200, como o
padrão de SPA pede), fora do nosso controle. `yelly-equipamento-cupom` em
`fontes.json` ganhou `requer_navegador: true` (um fetch simples só pegava a
casca vazia da SPA) e a flag nova `ignora_status_http: true`, que faz
`src/coletor.mjs` (nas duas vias, fetch e navegador) não tratar esse status
como falha automática — o sinal de bloqueio por conteúdo (`pareceBloqueado`)
continua valendo como rede de segurança. Documentado com mais detalhe em
`monitor/README.md`.

**Verificado de ponta a ponta, não só na teoria:** depois das duas
correções, uma execução manual (`workflow_dispatch`) do `monitor-diario`
terminou em `Success` (1m14s) e gerou o **primeiro commit `estado(monitor):
...` que esse mecanismo já produziu** (`3fceebc`, 8 arquivos novos em
`monitor/estado/`) — a persistência de estado entre execuções, desenhada na
etapa 13, nunca tinha rodado de verdade em produção até este dia, porque
todas as execuções anteriores morriam antes de escrever qualquer arquivo.

## Identidade visual Máquina Certa (etapa 14)

A fonte é o manual de marca, versionado em `id_visual/`:
`brandbook-maquina-certa.md` (texto), `manual-de-marca.pdf` (o mesmo conteúdo,
diagramado — a página 8 tem o desenho de botão, selo e cartão) e `assets/`
(PNGs do logo e favicons). **Não há SVG do logo**, só PNG em 1x e 2x.

| Arquivo | Papel |
|---|---|
| `resources/css/app.css` | Paleta, fontes, raios e escala. Continua sendo a fonte única da verdade |
| `scripts/verifica-contraste.mjs` | Ganhou os pares do canal de ação e do navy do rodapé |
| `vite.config.js` | Saira (400/600/700) e Figtree (400/500/600), baixadas no build |
| `public/marca/` | Os PNGs que o site usa: logo com assinatura (normal e negativo, 1x/2x) e favicons |
| `public/favicon.ico` | ICO com carga PNG (32 e 64px), montado a partir de `favicon-32/64.png` |

### A decisão que a etapa carregava: dois canais de cor

O manual pede verde para "botão principal, melhor taxa, selos de economia". O
site já usava verde para `aferido` (taxa publicada) — e, descoberto ao medir,
**o conflito já existia no código da etapa 07**: "Melhor custo" saía em
`aferido` e "Mais caro" em `vencido`. Decidido com o Everton, em 14/09/2026:

- **Canal de ação** (tokens `acao`, `acao-fundo`, `acao-vivo`, `sobre-acao`):
  botão principal, cartão de menor custo, selo de economia de cupom. O menor
  custo só existe dentro do bloco `calculado`, então **um verde é sempre, por
  construção, um número publicado** — a regra 4 não depende da cor.
- **Estado do dado** sai do verde e passa a ser dito por **forma e rótulo**:
  `aferido` virou navy com contorno cheio, `reportado` é **tracejado** em ocre
  (cartão promocional, faixa reportada, etiqueta), `vencido` é o vermelho de
  alerta. Os **nomes** dos tokens não mudaram — só os valores.
- **"Mais caro" não tem cor.** Vermelho é de vencido.
- O rótulo do cartão destacado é **"Menor custo no seu cenário"**, não o
  "Melhor para seu perfil" do manual: o site ranqueia custo, não recomenda
  perfil, e o próprio manual diz que "a escolha é do usuário".

### Onde o manual foi corrigido pelo contraste

- **Branco sobre `#009C82` dá 3,46:1** (o manual diz 3,2:1 e libera "peso 600 a
  partir de 14px", mas pela WCAG texto grande em negrito começa em ~18,7px). O
  botão principal usa **`#00705E`**, o verde escuro do próprio manual (6,04:1).
  O `#009C82` ficou como `acao-vivo`, decorativo.
- **O cinza `#C8C8C8` dá 1,67:1** — serve de fio (`regua-forte`), não de borda
  de campo. `contorno` continua derivado (`#7A8397`, ≥ 3:1).
- **O manual não tem cor para `reportado`.** Ficou o ocre (`#7A4D05`), longe do
  laranja `#D26432`, que é reservado ao Monetizando Negócios.
- **O manual não tem tema escuro.** Decidido manter, derivado do navy
  (`#0C1324` de fundo). Remover mexeria no script inline de tema, cujo hash está
  fixado à mão na CSP (middleware **e** regra do Cloudflare).

### Tipografia: `numero` e `numero-destaque`

Saira nos títulos, botões e números de destaque; Figtree no texto. **Os dois
servidos pelo Bunny mantêm algarismos tabulares** — conferido no navegador
medindo `1111111` e `0000000` com `tabular-nums`: 174px e 174px nas duas
famílias (proporcional: 111 × 186 na Saira). Sem isso a vírgula não alinharia
na coluna de 21 parcelas.

`numero` virou Figtree tabular, para número no meio de frase. O número herói de
cartão usa `numero-destaque` (Saira 700 tabular, −2%) com `text-numero` (22px,
o do manual). Saira 700 no meio de uma frase de 13px ficava pesada demais.

Escala: `manchete` = H1 do manual (40–56px), `titulo` = H2 (28–32px), e um
degrau novo, `cartao` (20px), para nome de marca e cabeçalho de bloco — o H2 de
28px empurrava o número para a linha de baixo num cartão de 375px. Raios: `selo`
4px, `botao` 6px (novo, também em campo), `bloco` 10px.

### Botão: verde, uma ação por tela

`<x-botao>` tem quatro variantes: `principal` (verde), `marca` (navy sólido —
o "Ver oferta" do cartão que não é o destaque), `secundaria` (contorno) e
`discreta`. Altura mínima de **48px** (manual), Saira 600. Desativado é fundo
cinza com texto apagado, nunca o verde lavado.

Para cumprir "verde só para a ação principal da tela — um por vez",
`<x-bloco-cupom>` ganhou `variante-botao`: `/cupons` (grade) e o topo de
`/maquininha/{slug}` (que já tem o CTA verde no fim) passam `marca`. O banner
de cookies não usa verde em nenhum dos dois botões — pela LGPD, aceitar e recusar
têm o mesmo peso.

**Selo "Desconto parceiro"** (`<x-etiqueta tom="parceiro">`, navy) sai em todo
bloco de cupom e no CTA da página de marca: o manual o torna obrigatório
sempre que há comissão, e todo cupom daqui é link de afiliado.

### Logo: PNG, então a versão escura é outra imagem

Cabeçalho, rodapé (navy, logo negativo) e em-breve usam
`logo-horizontal-assinatura`, com `srcset` 2x e mínimo de 140px. Nas telas que
trocam de tema há **duas `<img>`**, com as classes `so-tema-claro` e
`so-tema-escuro` do `app.css`. **Não `dark:`**: a variante `dark:` só enxerga o
atributo `data-tema`, e sem JavaScript o logo navy sumiria no fundo escuro. As
classes seguem as mesmas duas vias da paleta (atributo e `prefers-color-scheme`).

**O script inline de tema de `em-breve.blade.php` e do layout não foi tocado**:
o hash dele está fixado na CSP em dois lugares. Um comentário no topo da
em-breve avisa disso.

### Mobile-first: o que mudou de layout

- Fundo da página em `superficie` (`#F6F7F9`) e cartão em `papel` (branco): os
  nomes não inverteram de sentido, só o `body` trocou de token.
- A direção "Boletim" montava grades de fio de 1px (`gap-px bg-regua`). Saíram
  todas: os quatro números do resultado são **uma coluna até 30rem**, duas até
  `md`, quatro no desktop — antes o rótulo "CUSTO MENSAL RECORRENTE" quebrava em
  três linhas numa grade de 2 colunas a 375px.
- `<x-tabela-taxas>` só ganha largura mínima (e rolagem) quando tem coluna
  demais para 375px: rótulo + taxa cabe sem rolar; 3 colunas pedem 24rem; 4
  pedem 32rem.
- A barra "N de 9 marcas selecionadas" de `/maquininhas` fica **fixa no topo**
  ao rolar a grade; botões de ação principal ocupam a largura toda no celular.
- A barra de rolagem da navegação do cabeçalho está escondida (a rolagem por
  toque continua): na emulação de celular ela desenhava uma faixa cinza.
- Em `/enviar-proposta`, o nome da linha de venda fica em cima dos campos no
  celular.

### Conferido no navegador

Todas as 9 páginas públicas em 375px e em desktop, mais `/guia-visual` e a
em-breve (renderizada num HTML temporário em `public/`, apagado depois, porque
localmente `SITE_EM_BREVE` fica desligado), e o tema escuro na em-breve e nas
tabelas. Nenhuma página rola na horizontal em 375px (`scrollWidth` = 375).

**Para ver `/cupons` e `/cupom/{slug}` com conteúdo foi preciso um cupom** — o
banco local não tem nenhum. Criado um temporário (`TESTE-ETAPA14`, InfinitePay)
e **apagado ao fim da verificação**.

### O que ficou de fora, e por quê

- **A grade débito/crédito/12x do cartão do manual não entrou na listagem de
  marcas.** `ResumoDeMarca` entrega mensalidade, prazo mais rápido e faixa de
  taxa; montar débito, crédito e 12x por marca exigiria escolher sozinho plano,
  prazo e grupo de bandeira — três das cinco dimensões da chave da regra 1. É
  decisão de dado, não de estilo. A listagem ganhou a *forma* da grade do manual
  com os três dados que já existem.
- **O painel Filament não foi tocado** — ele tem tema próprio e não lê este
  `app.css`.
- **Logo de marca no cartão continua sendo a inicial** (etapa 15). O quadrado
  agora fica à direita, como no desenho do manual.
- Localmente o `APP_NAME` é "Comparador de Maquininhas", e é o que aparece em
  `<title>` e no `alt` do logo; em produção o `.env` já diz "Máquina Certa".

### Corrigido de passagem

`/metodologia` dizia que o selo degradado aparece como etiqueta `vencido`, mas
`<x-selo-frescor>` sempre usou `reportado` para "desatualizada". O texto passou
a usar `reportado`, igual ao componente.

## Imagens (etapa 15)

O catálogo não tinha nenhuma imagem até aqui. O schema já previa
`marcas.logo_path`, `equipamentos.imagem_path` e `bandeiras.logo_path`
(etapa 02), e `App\Support\Uploads\ImagemSeguraWebp` (etapa 10) já validava
upload manual pelo conteúdo real do arquivo e convertia para WebP. Esta etapa
não trocou esse caminho manual — ele continua existindo no formulário de cada
recurso — e somou um segundo caminho: uma ação no painel que busca a imagem a
partir de uma URL.

| Arquivo | Papel |
|---|---|
| `App\Support\ImagensExternas\UrlExternaSegura` | SSRF: esquema, IP privado/reservado/CGNAT, resolução de DNS pinável |
| `App\Support\ImagensExternas\BuscaDeImagemExterna` | Busca, segue redirecionamento (revalidando cada salto), extrai `og:image`/ícone, converte |
| `App\Support\ImagensExternas\ResultadoDaBusca` | O que a busca devolve: sucesso, erro, WebP em base64, de onde veio |
| `App\Filament\Actions\BuscarImagemPorUrlAction` | A ação de linha em Marca, Equipamento e Bandeira |
| `App\Support\Uploads\ImagemSeguraWebp` | Ganhou `converterParaWebp()` e `gravar()`, operando sobre bytes — sem tocar em disco antes da aprovação |
| `resources/views/filament/imagem-externa/preview.blade.php` | A pré-visualização dentro do modal |

### O fluxo: buscar, ver, só então aprovar

O admin cola uma URL (kit de mídia, página de imprensa, ou a própria página do
produto) numa ação por linha nas três listagens. Um botão "Buscar", dentro do
mesmo modal, dispara a busca no servidor: acha o candidato — `og:image`,
`apple-touch-icon` (o maior, pelo atributo `sizes`), `<link rel="icon">`, ou a
própria URL quando o `Content-Type` já é `image/*` —, passa pelo mesmo
conversor para WebP do upload manual, e mostra a pré-visualização.

**O candidato fica em base64, num campo oculto do próprio formulário — nunca
em disco.** Essa é a leitura mais literal possível da regra 10 aqui: não existe
nem um caminho público provisório antes da aprovação. Só o clique em "Aprovar e
salvar" (`BuscarImagemPorUrlAction::aprovarEGravar()`) decodifica, reconfere o
tipo pelos bytes (defesa contra um campo adulterado entre a busca e a
aprovação) e grava no disco `public`, no mesmo diretório que o upload manual já
usava. Buscar sem aprovar não muda nada no registro — é o que
`BuscaDeImagemPorUrlTest` cobra.

### A busca em si é risco de SSRF, e é tratada como tal

`UrlExternaSegura` só libera `http`/`https`, resolve o host para um IP e
recusa se ele for privado, loopback, link-local (inclusive o range do
metadado de nuvem, `169.254.169.254`), CGNAT (`100.64.0.0/10`) ou multicast —
usando `FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE` do próprio PHP como
base, mais os CIDRs que esses dois flags não cobrem. `BuscaDeImagemExterna`
pina a conexão real no IP resolvido (`CURLOPT_RESOLVE`), para que a checagem e
a conexão usem o mesmo endereço — sem isso, o DNS poderia responder diferente
entre a validação e a busca (rebinding). Redirecionamento desliga o
`allow_redirects` do Guzzle e segue a mão, até 3 saltos, **revalidando cada
salto pela mesma checagem** — um `og:image` ou um `Location` apontando para
`169.254.169.254` é bloqueado igual à URL de entrada. Timeout de 3s para
conectar e 6s no total, limite de 5 MB, e só `image/*` ou `text/html` são
aceitos como resposta.

`UrlExternaSeguraTest` cobre a lista de IP bloqueado/permitido e a resolução
via DNS injetado (nunca o real — os testes não dependem de rede). 
`BuscaDeImagemExternaTest` cobre fim a fim com `Http::fake()`: esquema
recusado, IP literal privado, DNS apontando para rede privada, redirecionamento
para IP privado, tipo inválido, arquivo grande demais, e a extração de
`og:image` (absoluta e relativa) e `apple-touch-icon`.

### O logo entrou no comparador também

Decidido com o Everton: o resultado do comparador (regra 9, JSON estático,
motor duplicado em PHP e JavaScript) ganhou `marca.logo_url`. Foram quatro
arquivos, não um: `CatalogoDoComparador::marcas()`, `MotorDeCalculo::esqueleto()`,
`motor.mjs::esqueleto()` e o fixture sintético de teste
(`tests/Support/CatalogoDeTeste.php`) — os três primeiros porque o campo
atravessa a mesma duplicação que a regra 1 já exige para qualquer número do
motor, o quarto porque o teste de paridade roda sobre catálogo sintético, não
só sobre a carga real. `ParidadeDoMotorTest` continuou passando sem alteração
própria: é um campo repassado, não calculado, e por isso os dois motores já
concordavam nele.

O quadrado de logo (ou a inicial, como no cartão de marca) entrou no card do
bloco `calculado` e no do bloco `promocional` — os dois usam o mesmo
`resultado-comparado.blade.php`. O bloco `faixa_reportada` tem markup próprio
(motivo na etapa 07: nunca reaproveitar o cartão de número único) e ganhou o
mesmo quadrado, escrito à parte em `comparador.blade.php`. A lista minúscula de
"sem dado publicado" ficou sem logo de propósito — é texto corrido, de
propósito apagado, e um quadrado ali seria peso visual sem função.

### Placeholder continua honesto

Nada mudou aqui — a etapa 14 já deixava isso pronto e esta etapa só confirmou:
marca sem logo mostra a inicial no quadrado (nunca um ícone de banco de
imagem), equipamento sem foto mostra "Sem foto", bandeira sem logo mostra a
inicial. `<x-cartao-marca>`, a página de marca e o resultado do comparador
usam o mesmo critério: `if (logo_url) <img> else <inicial>`.

### `loading="lazy"`, `decoding="async"`, WebP, próprio domínio

Já valiam antes desta etapa (regra 9: CSP só aceita imagem do próprio domínio,
e nada aqui contraria isso — toda imagem sai de `Storage::disk('public')`).
Isso a etapa somou: `width`/`height` explícitos em todo `<img>` que já
existia (o quadrado de logo e a foto de equipamento), calculados a partir da
caixa CSS que já delimitava o tamanho (`max-h-10 max-w-10` → `40×40`, e assim
por diante) — o layout já não deslocava ao carregar, porque a caixa em si já
tinha tamanho fixo, mas o atributo é a declaração explícita que a regra pede.

### O que ficou de fora, e por quê

- **Nenhuma imagem real foi cadastrada em produção nesta etapa.** A
  verificação usou uma marca, um equipamento e uma bandeira com uma imagem
  gerada localmente (não baixada de lugar nenhum — regra 8 do domínio, "nunca
  raspar", vale pelo mesmo espírito aqui: nada entra sem o admin colar a URL de
  propósito), e foi revertida ao fim da conferência.
- **Não existe rota nem asset pública para o candidato não aprovado.** Foi
  decisão, não esquecimento: qualquer caminho público, mesmo temporário,
  seria "no ar" antes da aprovação.

- [x] **01** — Ambiente local, Filament, Git e CLAUDE.md
- [x] **02** — Schema do banco
- [x] **03** — Painel admin no Filament
- [x] **04** — Carga dos dados reais
- [x] **05** — Motor de cálculo
- [x] **06** — Identidade visual e design system
- [x] **07** — O comparador
- [x] **08** — Páginas de marca e listagem
- [x] **09** — Página de cupons
- [x] **10** — Metodologia, LGPD e captação de relatos
- [x] **11** — Deploy, SSH, backup e commits
- [x] **12** — Cloudflare, medição, SEO, segurança e performance
- [x] **13** — Monitor de mudanças
- [x] **14** — Identidade visual e reforma da interface
- [x] **15** — Imagens: logos de marca, equipamentos e bandeiras
- [x] **16** — Painel de saúde e observabilidade do administrador
- [ ] 17 — Curadoria e validação das taxas (avançada, não fechada — ver seção própria)
- [x] **18** — Manual do administrador
- [ ] 19 — Lançamento
- [ ] 20 — Decisão sobre programa de parceiros
- [ ] 21 — Cadastro de taxas por imagem (IA de visão)

**A ordem da 12 em diante foi refeita em 11/09/2026** (o motivo está em
`PLANO.md`). Nada até a 11 mudou. O resumo: a identidade visual própria ainda
não existia e levava dias para ficar pronta, então as etapas que não encostam
em estética passaram na frente, a reforma visual entrou antes do lançamento —
nunca depois —, e a curadoria das taxas foi para depois dela, por decisão do
Everton: avaliar taxa e avaliar tela ao mesmo tempo confunde as duas coisas.

## Painel de saúde do administrador (etapa 16)

Um lugar só, no `/admin`, para responder "o site está bem?" sem abrir SSH nem
hPanel — expandindo `App\Filament\Widgets\PainelInicial` (que já somava três
alertas) com mais sete widgets, todos descobertos automaticamente por
`AdminPanelProvider::discoverWidgets()`. Ordem no dashboard por
`sort`: PainelInicial (1), OperacaoWidget (2), CliquesCupomChart (3),
CliquesCupomPorMarcaTable/CliquesCupomPorCupomTable (4-5), FrescorWidget (6),
BancoWidget (7), TrafegoWidget (8).

| Prioridade | Widget / arquivo | O que mostra |
|---|---|---|
| 1 | `links:verificar` + cartão em `PainelInicial` | Link de afiliado quebrado |
| 2 | `CliquesCupomChart`, `CliquesCupomPorMarcaTable`, `CliquesCupomPorCupomTable` | `eventos_cupom`, 30 dias |
| 3 | `OperacaoWidget` (`App\Support\Saude\StatusDeOperacao`) | Backup, fila, SSL, `APP_DEBUG`, `.env`, 2FA |
| 4 | `BancoWidget` | Tamanho por tabela, contagem por entidade |
| 5 | `FrescorWidget` (`App\Support\Saude\FaixasDeFrescor`) | Faixa de idade da verificação |
| 6 | `TrafegoWidget` (`App\Support\Saude\ClienteAnalyticsCloudflare`) | Acessos diários, se configurado |

**Regra do painel inteiro, aplicada com mais rigor que em qualquer outra
tela:** cartão sem dado mostra "Sem dado" e o motivo — nunca zero, nunca
"ok" adivinhado. É a regra 6 do domínio ("nada sem fonte") virada do avesso
contra o próprio painel.

### 1. Verificador de link de afiliado

`App\Console\Commands\VerificarLinksAfiliados` (`php artisan
links:verificar`) faz HEAD em `marcas.site_url` e `cupons.link_afiliado`,
com fallback para GET quando o servidor devolve 405 (HEAD não implementado —
achado testando contra sites reais de afiliado, não suposto). Grava em
quatro colunas novas nas duas tabelas (migration
`2026_09_15_090000_add_verificacao_de_link_to_marcas_and_cupons`):
`link_ultimo_status`, `link_ultima_falha` (mensagem quando nem status HTTP
saiu — timeout, DNS, TLS), `link_quebrado` e `link_verificado_em`. Link nunca
verificado (`link_verificado_em` nulo) não conta como quebrado — ele ainda
não teve chance de falhar.

`PainelInicial` ganhou o cartão "Links de afiliado quebrados" (soma marcas +
cupons). `MarcasTable` e `CuponsTable` ganharam coluna badge (Não
verificado/No ar/Quebrado) e filtro, para quem já está editando a marca ou o
cupom ver o estado sem precisar abrir o dashboard.

Rodado manualmente uma vez em produção ao fim desta etapa: 9 marcas e 0
cupons verificados (ainda não há cupom cadastrado — ver `PLANO.md`), 0
links quebrados.

**Resolvido — confirmado em produção em 17/09/2026.** O Everton criou o Cron
Job no hPanel. `routes/console.php` registra
`Schedule::command('links:verificar')->dailyAt('07:00')` para quem rodar
`schedule:work` em local, mas em produção quem executa de fato é o cron do
hPanel (Avançado → Cron Jobs), chamando o mesmo binário PHP que o
`deploy.sh` usa (`/usr/bin/php` desta Hostinger tem `proc_open` e symlink
desabilitados, não serve):

```
/opt/alt/php84/usr/bin/php /home/u835756808/domains/maquinacerta.com.br/comparador/artisan links:verificar
```

Confirmado rodando sozinho de madrugada (`marcas.link_verificado_em` e
`cupons.link_verificado_em` em 17/09/2026 04:05, não um horário de execução
manual) — 0 links quebrados no momento da checagem.

### 2. Cliques em cupom

`eventos_cupom` existe desde a etapa 09 e nunca tinha sido visualizada.
`CliquesCupomChart` é um gráfico de barras (Filament `ChartWidget`) dos
últimos 30 dias, uma série por `TipoEventoCupom` (usar cupom / copiar
código). As duas tabelas de ranking agrupam por Eloquent `Builder` puro —
`CliquesCupomPorMarcaTable` por `marca_id`, `CliquesCupomPorCupomTable` por
`marca_id` + **`codigo`**, não por `cupom_id`: `codigo` é a foto do código
no momento do clique (ver etapa 09), então um cupom editado ou apagado não
faz o clique de ontem desaparecer do ranking de hoje.

**Achado implementando:** uma query agrupada (`groupBy`) não tem `id` real,
e o Filament Table usa `$record->getKey()` para o `wire:key` de cada linha.
As duas tabelas selecionam explicitamente uma coluna `id` derivada
(`marca_id as id`, ou `CONCAT(marca_id, '_', codigo) as id`) — sem isso as
linhas do grupo colidiriam.

### 3. Operação

`App\Support\Saude\StatusDeOperacao` concentra as seis contas; o widget só
escolhe cor e ícone. `config/saude.php` é onde tudo isso é opcional:

- **Backup diário**: lê `BACKUP_LOG_PATH` (vazio por padrão — nunca supõe
  `$HOME`) e faz o parse do último bloco `--- inicio --- ... --- fim, ok
  ---`/`FALHOU:` do log que `scripts/backup-comparador.sh` escreve.
  Verificado contra o log real de produção via `ssh comparador` durante esta
  etapa, e `BACKUP_LOG_PATH=/home/u835756808/backups/comparador/backup.log`
  já entrou no `.env` de produção (com `config:cache` refeito) no fim desta
  mesma etapa. Sem essa variável, em qualquer outro ambiente, o cartão
  mostra "Sem dado" honestamente — nunca finge ter lido o log.
- **Fila**: `jobs` e `failed_jobs` — `QUEUE_CONNECTION=database` em produção
  (não `sync`), então essas tabelas existem e o cartão é real, não decorativo.
- **Certificado SSL**: `stream_socket_client` direto (sem `exec`, que está
  desabilitado no PHP web da Hostinger — ver "Fica de fora" no `PLANO.md`),
  contra o host de `SAUDE_DOMINIO_SSL` ou o host de `APP_URL`. Cacheado 6h
  (`Cache::remember`) para não abrir uma conexão TLS a cada carregamento do
  dashboard.
- **`APP_DEBUG`**, **permissão do `.env`** (`fileperms()`, seguro só em
  `600` — mesma regra do `backup-comparador.sh`) e **usuários sem 2FA**
  (`users.app_authentication_secret` nulo) são leitura local direta, sem
  rede.

**Não tentamos ler disco nem CPU da hospedagem** (pedido explícito do
Everton) — `exec`/`shell_exec` desabilitados no PHP web e
`disk_free_space()` numa conta compartilhada reporta o volume inteiro, não a
cota. Isso continua no hPanel.

### 4. Banco

`BancoWidget` lê `information_schema.tables` para tamanho por tabela — MySQL
e MariaDB só, os dois motores reais do projeto (local e produção). **Achado
pelos testes**: a suíte roda em SQLite (`phpunit.xml`), que não tem
`information_schema`; o widget checa `DB::connection()->getDriverName()` e
devolve coleção vazia fora de `mysql`/`mariadb` — a view mostra "Sem dado"
em vez de estourar. `table_rows` do InnoDB é estimativa, não contagem exata;
a coluna diz isso ("Linhas (aprox.)"). Contagem por entidade é `count()`
Eloquent direto nos 12 models de domínio + operacionais.

### 5. Frescor

`App\Support\Saude\FaixasDeFrescor` expande o cartão único do
`PainelInicial` ("+30 dias") em faixas de idade — soma `taxas_divulgadas` e
`faixas_reportadas`, as duas classes de taxa da regra 4. Os cortes de 30 e
45 dias são os que o domínio já usa (`ResumoSemanal::DIAS_ALERTA_VERIFICACAO`
e a constante de `TemFrescor`, regra 8); só o corte de 90 ("muito
desatualizada") é novo, o dobro do prazo de degradação.

**PHP não deixa acessar constante de trait pelo nome do trait**
(`TemFrescor::DIAS_ATE_DEGRADAR` não compila) — só por uma classe que o usa;
`FaixasDeFrescor` referencia `TaxaDivulgada::DIAS_ATE_DEGRADAR`.

### 6. Tráfego

`App\Support\Saude\ClienteAnalyticsCloudflare` consulta a GraphQL Analytics
API da Cloudflare (`httpRequests1dGroups`, server-side — conta o que o
servidor viu, não depende de consentimento de cookie, diferente do GA4 da
etapa 12). A etapa 12 deixou DNS, SSL e regras da Cloudflare prontos, **mas
não gerou o token da Analytics API** — conferido direto no `.env` de
produção via SSH nesta etapa (`CLOUDFLARE_API_TOKEN`/`CLOUDFLARE_ZONE_ID`
ausentes). Sem os dois, `TrafegoWidget` mostra o cartão vazio com o motivo à
vista, nunca um número de visita inventado — exatamente o que o Everton
pediu. Resposta cacheada 1h quando configurado.

### Testando um widget lazy

Todo `Filament\Widgets\Widget` é lazy por padrão (`CanBeLazy::$isLazy =
true`): o dashboard renderiza um placeholder de carregamento no primeiro
HTML, e o conteúdo real vem de um segundo request que o navegador dispara
sozinho. `Livewire::test(Dashboard::class)` não dispara esse segundo
request — só prova que o dashboard monta sem exceção de descoberta/registro.
Para testar o **conteúdo** de um widget (`assertSee(...)`), o jeito que
funciona é testar o widget diretamente como componente raiz —
`Livewire::test(OperacaoWidget::class)` —, o que pula o wrapper lazy do
schema da página. `tests/Feature/Admin/PainelDeSaudeTest.php` faz as duas
coisas: um smoke test no dashboard inteiro, e um teste de conteúdo por
widget.

## Curadoria e validação das taxas (etapa 17, em andamento)

**Estado real em produção, conferido em 17/09/2026 (não por seeder — pelo
que o Everton aprovou/subiu direto no painel):**

| Marca | Taxas publicadas | Logo | Equipamentos com foto |
|---|---|---|---|
| Ton | 534/534 | sim | sim |
| FacilityPay | 117/117 | sim | sim |
| SidePay | 78/78 | sim | sim |
| Yelly | 116/116 | sim | sim |
| TrincaPay | 45/45 | sim | sim |
| PagBank | 135/135 | sim | sim |
| InfinitePay | 0/284 | sim | sim |
| SumUp | 0/81 | sim | sim |
| Mercado Pago | 0/3 | sim | — |
| Cielo/Rede/GetNet/Stone | 0 (não publicam tabela, regra 4) | sim | — |

**Resolvido em 17/09/2026:** as 74 taxas que estavam presas ao plano
"Taxas iniciais" (`plano_id = 1`, soft-deleted em 16/09) eram lixo de banco
sem efeito no site (já não apareciam no JSON) — removidas em produção com
`TaxaDivulgada::where('plano_id', 1)->delete()`, a pedido do Everton. O
`plano` em si (registro `planos` id 1) continua soft-deleted, não
force-deleted — histórico, sem risco de colidir com slug novo porque o
`PagBankSeeder` não tenta mais recriá-lo.

**Divergência achada entre local e produção, ao limpar isso:** localmente o
mesmo plano "Taxas iniciais" **não está soft-deleted** (a exclusão foi uma
ação manual do Everton só em produção, nunca replicada local) e as mesmas
74 taxas ainda existem lá, vinculadas a um plano que localmente parece
ativo. Não mexido — risco baixo (base local não serve ao público, só a
testes e ao próprio Claude), mas vale lembrar antes de rodar
`PagBankSeeder` local achando que reflete produção.

**Decisão do Everton em 17/09/2026: lançamento não vai esperar a curadoria
de todas as 13 marcas.** InfinitePay, SumUp e o restante de Mercado Pago
(marcas mais caras e/ou sem parceria de afiliado com o Monetizando) ficam
para depois, em ritmo próprio, sem travar o avanço para a etapa 18/19. Isso
não é regressão da regra 10 (nada publicado sem aprovação) — é a mesma
regra, marca a marca, terminando em datas diferentes.

**24/24 equipamentos com foto, 13/13 marcas com logo, 0 links quebrados, 0
itens pendentes nas filas de revisão** (propostas, relatos de taxa incorreta,
detecções do monitor) — conferido em produção na mesma data.

**Resolvido em 17/09/2026: as 13 bandeiras ganharam logo, e duas bandeiras
novas entraram no catálogo.** O Everton baixou os logos e mandou pelo chat —
a maioria já tinha sido salva automaticamente na pasta dele do Drive
(`Logos Bandeiras/`), no mesmo padrão de sempre. Cadastrados via
`ImagemSeguraWebp::salvar()` direto (não pela ação de busca por URL da
etapa 15, que é para candidato buscado na web — aqui o arquivo já estava
local e confiável, veio do próprio Everton).

**Dois formatos que o pipeline de upload não aceita direto** (só
JPEG/PNG/GIF/WebP, ver `ImagemSeguraWebp::CRIADORES`): SVG
(American Express, Pluxee) e AVIF (Green Card, ver abaixo). Convertidos
para PNG com `qlmanage -t -s 512 -o` (miniatura do Quick Look do macOS —
não há `rsvg-convert`/ImageMagick instalado nesta máquina) e, para as duas
SVG, recortados por script PHP com GD (bounding box do conteúdo não-branco/
não-transparente) porque a miniatura do Quick Look mantém a tela cheia
512×512 com o logo pequeno num canto.

**Duas bandeiras novas, confirmadas por fonte oficial (regra 6), não só
porque o Everton mandou o logo:** conferido em `pagbank.com.br/
para-seu-negocio/voucher`, que lista explicitamente sete bandeiras de
vale-refeição/alimentação aceitas — Alelo, Pluxee, Ticket, VR, **Ben Visa
Vale**, Up Brasil, **Green Card**. As duas primeiras (Ben, Green Card) não
existiam no catálogo; criadas com `ordem` 13 e 14.

**Duas logos que o Everton mandou e NÃO entraram, por enquanto:** Senff e
Personal Card. Buscadas em `pagbank.com.br/.../voucher` (não aparecem),
`yelly.com.br` (nem no texto da página nem na tira de logos de "formas de
pagamento") e `infinitepay.io`/`sumup.com` (sem menção). Só apareceram em
agregadores de terceiros (tipo "calculadoradetaxas.com.br"), que este
projeto trata como fonte não confiável desde a regra 8 (nunca raspar
Reclame Aqui) — o mesmo princípio vale aqui. Ficam pendentes até aparecerem
numa página oficial de alguma marca já cadastrada.

**Apple Pay, Google Pay e Samsung Pay não são bandeira neste domínio, e por
isso não entraram no catálogo.** São carteiras digitais — rodam por cima de
Visa/Mastercard/Elo, que já são a bandeira real cobrada. Cadastrá-las como
`Bandeira` própria contaria a mesma transação duas vezes, e nenhuma marca
publica taxa diferenciada para "pagamento por Apple Pay" (a taxa é da
bandeira do cartão por trás). Ficaram fora do `bandeiras_staging` de
propósito.

**A vinculação `bandeira_marca` (quais marcas aceitam quais bandeiras)
não foi expandida nesta sessão**, além do que já existia. A página do
PagBank confirma que ele aceita todo o grupo de voucher (Alelo, Pluxee,
Ticket, VR, Ben, Up Brasil, Green Card) mas o `bandeira_marca` dele hoje só
tem as bandeiras de cartão (Visa, Master, Elo, Amex, Hipercard, Hiper,
Diners, Cabal) — nenhum voucher. Registrado como pendência de curadoria de
dado (etapa 17), não de catálogo: decidir marca a marca quais vouchers
vincular é trabalho à parte de só ter o logo pronto.

Sessão de 15/09/2026. Trabalho de dado real feito com o Everton, não código
novo de feature — mas exigiu três mudanças de schema porque duas premissas da
etapa 02 não se sustentaram na prática (ver regras 5 e 7 revistas acima).

**Aprovação em lote:** o painel só tinha `EditAction` por registro — aprovar
964 taxas uma a uma era inviável. `TaxaDivulgadasTable` e
`FaixaReportadasTable` ganharam `BulkAction` "Aprovar e publicar" e "Voltar
para rascunho" (undo). A aprovação de verdade em produção é o Everton quem
faz — ele já tem acesso.

**Buracos fechados** (confirmados pelo Everton, dono do domínio):

- Ton e PagBank não cobram mensalidade → `0`, não mais ausente.
- Adesão parcela em 12x sem juros em toda marca → `equipamento_plano.parcelas_adesao`.
- Preço real dos 3 aparelhos da SumUp (Smart R$ 190,80, Solo R$ 58,80, Top
  R$ 46,80) — a etapa 04 tinha deixado em branco por ambiguidade na página.
- Pix de Ton (0,49%) e SumUp (0,9%): regra do Everton para esse par — **o
  número que aparece na tela é o real** (o que se paga por padrão), a
  condição para a taxa cair a 0% vai colada como `condicao`, nunca como
  número.
- Mercado Pago ganhou catálogo: bandeiras (grupo novo `geral` — "mesma taxa
  para qualquer bandeira", criado porque nenhum grupo existente servia),
  `publica_tabela = true`, e um plano promocional real (débito e crédito à
  vista 0,74%, 12x 8,99%, 30 dias ou R$ 5 mil processados) — só 1x e 12x
  tinham número na página, o resto ficou de fora de propósito (regra 6).

**Grupo de bandeira novo `elo`**, para marca que publica Elo com percentual
próprio, separado de "demais bandeiras" (Yelly, SidePay, TrincaPay — a
maioria das marcas antigas trata as duas juntas, sem precisar dele).

**Quatro marcas novas cadastradas** (Yelly, SidePay, FacilityPay, TrincaPay —
eram só citadas como exemplo da regra 7 desde a etapa 13). Cada uma com
seeder próprio (`YellySeeder`, `SidePaySeeder`, `FacilityPaySeeder`,
`TrincaPaySeeder`), leitura de 15/09/2026:

| Marca | Cobertura | O que falta |
|---|---|---|
| Yelly | Completa: 2 planos (Flash D+0, Premium D+1) × 3 grupos (Visa/Master, Elo, demais) × 1x-18x + Pix | — |
| SidePay | Completa: 2 planos (Receba em 1 dia D+1, Receba na hora D+0) × 2 grupos × 1x-18x + Pix | — (corrigido: ver nota abaixo) |
| FacilityPay | Completa: 3 planos (Express D+0, Profit D+1, Light D+1) × 2 grupos + Pix | — (Profit e Light completados por screenshot do Everton) |
| TrincaPay | Parcial: 1 faixa (a "oferta Canal Monetizando") × 2 grupos, sem Pix | A página mistura dois números de faturamento diferentes para a mesma tabela ("acima de R$ 5.000" no texto, "> R$ 45K" no cabeçalho) — não resolvido, plano ficou sem faixa de faturamento declarada até alguém confirmar |

**Correção real sobre a SidePay, achada pelo Everton batendo o olho no painel
admin (não por mim):** a primeira leitura tinha só 2 dos 4 blocos de taxa (faltava
o plano D+0 inteiro) e gravou a segunda coluna no grupo `elo` — errado, porque a
página da SidePay rotula essa coluna como **"Elo + Outros"**, diferente da Yelly,
que tem Elo isolada. Gravar em `elo` mostraria ao lojista "essa taxa vale só pra
Elo" quando na verdade vale pra qualquer bandeira fora Visa/Master. Corrigido pra
usar o grupo `demais` (o mesmo que Ton/PagBank/InfinitePay/SumUp já usam pro
mesmo padrão), e o plano "Receba na hora" foi completado com screenshots que o
Everton mandou. `SidePaySeeder` apaga qualquer taxa gravada sob o grupo errado
antes de recarregar, pra não deixar lixo órfão.

**Adquirente das quatro:** Yelly, SidePay e FacilityPay são PagSeguro/PagBank
(dito pelo Everton). TrincaPay ficou sem adquirente — ele não sabe, a conta
dele lá nem abriu ainda, e o campo virou opcional nesta etapa exatamente por
isso.

**O mesmo vale para o aparelho, não só o adquirente** (confirmado pelo
Everton em 16/09/2026): FacilityPay, SidePay e Yelly vendem maquininha
fornecida pela PagBank por trás — fisicamente o mesmo hardware, com marca
própria de cada revendedora na carcaça e na tela. Isso não significa que o
catálogo de equipamentos daqui deva copiar o nome/ficha técnica do PagBank:
cada marca publica o próprio nome comercial (ex.: "Facility Pro" da
FacilityPay é, na prática, o mesmo aparelho de uma das Moderninhas do
catálogo do PagBank) e a própria ficha técnica, que é o que vai no
catálogo — mas **quando faltar detalhe técnico que a página da revendedora
não deixa claro, a página de equipamentos do PagBank é uma fonte legítima
para tirar a dúvida**, exatamente por ser o mesmo hardware.

**Cupons reais cadastrados**, todos incidindo sobre a adesão de qualquer
maquininha da marca (nunca um equipamento específico — dito pelo Everton
"assim como de todas as demais"), sem `equipamento_id`:

| Marca | Código | Desconto |
|---|---|---|
| Ton | `EVERTONLOURENCOBF20` | 20% |
| Yelly | `AFILIADOS10` | 10% |
| SidePay | `MONETIZANDO` | 10% |
| FacilityPay | `EVERTON10` | 10% |
| TrincaPay | `CANALMONETIZANDO`¹ | 16% |
| PagBank | `vzArXydV` | Real, mas variável — sem percentual fixo (ver regra 5) |
| Mercado Pago | `ZQXZWS3OLW` | Real, mas variável — sem percentual fixo |

¹ O Everton escreveu "CANAL-MONETIZANDO" (com hífen); a própria página, ao
abrir o link, mostra e aplica "CANALMONETIZANDO" (sem hífen) — usado o que
foi verificado na página real. Vale confirmar.

**Ton, 17/09/2026: 20% cadastrado é intencionalmente o valor conservador.**
O desconto real no link do Everton estava em 25% nessa data (promoção da
própria Ton por tempo limitado, até o fim do mês, sobre o mesmo cupom).
Decisão dele: anunciar 20% (o valor que se mantém) em vez de 25% (o que é
passageiro) — surpresa boa pro cliente quando o desconto real for maior, e
evita ficar ajustando a página toda vez que a Ton muda a promoção. Não é
erro de cadastro se uma sessão futura conferir o link e achar um percentual
real diferente de 20%: é o comportamento esperado.

**Preço de adesão dos três equipamentos da FacilityPay, corrigido em
16/09/2026 (sessão fora da numeração do plano).** Primeira leitura só
capturou o preço da aba Express (a ativa por padrão em
`facilitypay.com.br/maquininhas`) e, sem perceber que cada aba de plano
muda o preço do aparelho, chegou a usar por engano o preço de um link de
indicação (`app.facilitypay.com.br/indicacao/EVERTON10`) aplicado por
igual aos três planos. O Everton conferiu as três abas manualmente e deu
os nove valores reais — `Facility Pro`, por exemplo: Express R$ 198,90,
Profit R$ 299,90 (mais caro — é o plano de taxa mais baixa), Light
R$ 119,90 (mais barato — é o plano de taxa mais alta). `preco_adesao`
(o "cheio" riscado no site) só foi verificado no contexto da aba Express;
sem dado de um cheio diferente por aba, o mesmo valor ficou nos três.

**Decisão com o Everton: manter os três planos com preço de adesão
próprio, não simplificar para "só o plano da parceria".** Adesão mais
cara compensando com taxa melhor no longo prazo (Profit) contra adesão
barata com taxa pior (Light) é exatamente o tipo de trade-off que o motor
de cálculo já resolve (custo inicial vs. custo mensal recorrente, etapa
05) — esconder isso tornaria a comparação menos honesta, não mais simples.

**Ainda pendente, sem mudar a lógica agora:** o preço do link de indicação
do Everton é um **terceiro** valor, mais baixo que qualquer uma das três
colunas confirmadas (ex.: Facility Pro indicação R$ 119,90 == a coluna
Light, mas Facility Mini indicação R$ 55,50 é mais barato que a própria
coluna Light, R$ 64,90 — não há um mapeamento limpo pra nenhum plano do
catálogo). Isso não é "preço cheio menos os 10% do cupom `EVERTON10`" —
é bem mais barato do que 10% explicariam. `EconomiaDoCupom` (etapa 08)
aplica o percentual do cupom sobre `preco_adesao`/`preco_adesao_promocional`
pra calcular a economia mostrada ao lojista, e por isso **subestima** a
economia real de quem usa o link do Everton. Revisitar `EconomiaDoCupom`
(ou o schema de `cupons`, se precisar de um jeito de o cupom apontar
direto pra um preço fixo em vez de um percentual) fica pra decisão de
produto futura.

**`taxa_antecipacao_mensal`: resolvido.** O Everton confirmou que nenhuma marca
cobra antecipação avulsa à parte — a taxa publicada já é a final, inclusive nos
planos que usam prazo sem antecipação embutida. `0`, não mais ausente, nos
únicos 4 planos onde o campo chega a ser consultado (os que têm alguma taxa em
prazo `d_30`/`parcela_a_parcela`): PagBank "Taxas iniciais", InfinitePay
"Acima de 20/40/80 mil".

**Pendente, sem inventar nada (regra 6):**

- Voucher: fechado como não resolvível — depende da negociação do lojista com
  a empresa do vale-refeição, não é dado que a maquininha publique.
- As lacunas de Yelly/SidePay/FacilityPay/TrincaPay da tabela acima.
- Faixas reportadas de Cielo/Rede/GetNet/Stone: zero relatos captados até
  agora (0 propostas, 0 relatos pendentes no painel).

### Catálogo de equipamentos da SidePay, e um bug real de reseed achado em 16/09/2026

Sessão fora da numeração do plano, a pedido do Everton: catálogo de
equipamentos (Mini, Pro, Smart) lido em `sidepay.com.br/maquininhas`, mesmo
padrão do `FacilityPaySeeder` — fotos PNG originais baixadas do próprio site
(`Mini-Plus-SidePay-Transparente.png`, `Pro-SidePay-Transp.png`,
`Smart-SidePay-Transp.png`, todas em `wp-content/uploads/2025/12/`), não os
prints que o Everton anexou. Confirmado que as fotos baixadas batem
pixel a pixel com os anexos dele.

O `De:`/`por` de cada aparelho é igual nos dois toggles de taxa
("Receba em 1 dia" / "Receba na hora"): `preco_adesao` (o cheio riscado)
não muda, só `preco_adesao_promocional` — mais barato em "Receba na hora"
(Mini R$ 97, Pro R$ 197, Smart R$ 297) do que em "Receba em 1 dia" (Mini
R$ 147, Pro R$ 247, Smart R$ 347), o mesmo trade-off adesão × taxa mensal
já visto na FacilityPay. `parcelas_adesao = 12` (parcela sem juros, como
toda marca já cadastrada).

**Achado real em produção, ao rodar o `SidePaySeeder` de novo para trazer
esse catálogo:** a correção da etapa 17 (grupo de bandeira `elo` → `demais`)
tinha deixado um `TaxaDivulgada::query()->...->delete()` **incondicional**
no início do `run()` — certo para aquele dia, mas ele nunca foi removido, e
qualquer reseed seguinte da SidePay apaga as taxas já aprovadas no painel e
recria tudo do zero como `rascunho` (regra 10). Isso derrubou a marca do
JSON público em produção sem aviso nenhum — só notado porque
`comparador:gerar-json` passou a listar "SidePay" em "sem taxa nem faixa" e
o total caiu de 1.025 para 947. Confirmado que não sobrava nenhuma taxa da
SidePay no grupo `elo` (a correção original já estava plenamente
persistida), então a limpeza não tinha mais função nenhuma — só risco.
Removido o bloco, as 78 taxas restauradas para `publicado` manualmente
(local e produção, mesmos valores de antes, sem dado novo aprovado às
pressas) e o JSON gerado de novo confirmando 1.025 taxas.

**Why:** todo `SeederDeMarca::taxa()`/`plano()` já preserva status numa chave
que já existe — a armadilha era o `DELETE` prévio apagar a chave antes desse
`updateOrCreate` rodar, então a proteção normal nunca chegava a entrar em
ação. Um `DELETE` de limpeza pontual dentro de um seeder que roda de novo a
cada carga futura é, por definição, permanente — se algum dia precisar
limpar de novo por outro motivo, fazer uma vez só, fora do `run()`.

**How to apply:** antes de rodar qualquer seeder de marca já existente em
produção (para trazer equipamento novo, corrigir taxa etc.), ler o `run()`
inteiro em busca de `delete()`/`query()->delete()` sem guarda — se existir,
confirmar que ainda tem função (taxa órfã real) antes de rodar, e considerar
remover se a correção que ele fazia já está persistida há tempo.

### Catálogo de equipamentos da Yelly, 17/09/2026

Mesmo padrão do catálogo da SidePay (sessão anterior): fotos oficiais
baixadas de `yelly.com.br/maquininhas` (`mini_sem_fundo.png`, `PRO-2-2.png`,
`smart_sem_fundo.png`, hospedadas em blob storage da Vercel) e ficha técnica
lida na mesma página. Antes de rodar em produção, verificado pelo JSON
público (`comparador.json`) que as 116 taxas da Yelly já estavam publicadas
— o `YellySeeder` nunca teve `DELETE` incondicional (diferente do
`SidePaySeeder` antes da correção acima), então reseed não tinha o mesmo
risco.

`preco_adesao` (cheio) igual nos dois planos (Mini R$ 399, Pro R$ 699, Smart
R$ 799); só o promocional muda por plano — mais barato no Flash (D+0) que
no Premium (D+1): Mini R$ 99,90/R$ 199,90, Pro R$ 199,90/R$ 299,90, Smart
R$ 299,90/R$ 399,90. `parcelas_adesao = 12`.

**O cupom `AFILIADOS10` não precisou de tratamento especial.** O Everton
mandou screenshots do checkout com cupom aplicado
(`checkout.yelly.com.br/monetizando/?cupom=AFILIADOS10`) mostrando o preço
final por combinação de plano e aparelho. Conferido: cada "Total" no resumo
do pedido bate exatamente com 10% de desconto sobre o `preco_adesao_promocional`
já cadastrado (ex.: Smart Premium R$ 399,90 × 0,9 = R$ 359,91, o valor exato
do resumo) — o cupom genérico já cadastrado (regra: incide sobre adesão de
qualquer maquininha da marca, sem `equipamento_id`) resolve isso sozinho.

**"Yelly Plus" (plano e aparelho) ficou de fora, decisão do Everton.**
Aparece só no checkout (Débito 1,13%, Crédito 12x 12,68%, aparelho próprio)
mas não em `yelly.com.br/maquininhas` nem em `yelly.com.br/taxas` — sem
confirmação em lugar nenhum verificável de que ainda está à venda. Provável
plano/aparelho descontinuado, mantido fora do catálogo até aparecer em fonte
oficial.

### Fotos dos equipamentos da Ton, e um site oficial que mentiu duas vezes (17/09/2026)

Preço de adesão e ficha técnica do T1/T2/T3/T3 Smart já existiam desde a
etapa 04 (08/09/2026) — confirmados ainda corretos e batendo com
`ton.com.br` nesta sessão, sem mudar nada. Faltava só a foto.

**A foto oficial do T3 Smart em `ton.com.br` está errada** (ou desatualizada
— não dá pra saber qual), e de um jeito raro de pegar: o mesmo arquivo
aparece consistente em pelo menos 5 lugares do site (página individual do
T3 Smart, card da home, "compare as três maquininhas", hero do catálogo de
afiliado, banner promocional "0,57%") mostrando um aparelho com teclado em
**inglês** (CANCEL/CLEAR/ENTER, com setas ↑↓) — nada a ver com o teclado em
português (AJUDA/ATALHOS) do aparelho real que o Everton fotografou e
confirmou duas vezes ser o T3 Smart de verdade. Cinco confirmações do mesmo
site não bastaram porque todas vinham do mesmo asset reciclado — quantidade
de lugares que mostram uma imagem não é o mesmo que quantidade de fontes
independentes.

Na primeira rodada eu argumentei com o Everton usando essa "evidência",
pedindo pra ele confirmar antes de eu confiar na foto dele. Na segunda, ele
insistiu e deu os nomes de arquivo (`t3-1`, `t3-smart`) que usa há anos —
e o `find` no disco achou os originais numa pasta própria dele no Google
Drive (`Projetos IA/Máquina Certa/Equipamentos/Ton/`, datados de 14/11/2023,
replicados em mais dois Google Drive diferentes que ele usa). Eram
exatamente os quatro aparelhos, com o nome batendo com o que ele disse.

**Why vale lembrar:** o site oficial da marca é a fonte padrão neste
projeto, mas não é infalível — pode reciclar um asset errado/desatualizado
em vários lugares ao mesmo tempo, o que parece (e não é) confirmação
cruzada. Quando o dono do domínio insiste numa correção com detalhe
verificável (nome de arquivo, não só "confia em mim"), vale procurar esse
detalhe no disco antes de insistir de volta com mais scraping da mesma
fonte.

**Decisão pendente de confirmação do Everton:** não modelar o "Ton Black"
(plano exclusivo MEI/PJ, taxas piores, só existe atrás do link de afiliado
`ton.com.br/catalogo?coupon=...`) nem o preço de aparelho por plano desse
catálogo. Motivos: (1) o "Ton Mega+" desse catálogo é o mesmo "Período
Promocional" já cadastrado, só com nome de afiliado; (2) o "% OFF" que a
página do Ton Black anuncia não fecha matematicamente com o preço cheio
mostrado do próprio card (conferido, não bate); (3) o checkout do link do
Everton abre com "Ton Mega+" pré-selecionado — o mesmo plano que já
mostramos — então o risco de um cliente ver um preço diferente do
anunciado é baixo na prática, e o motor de comparação (que otimiza por
menor taxa) nunca recomendaria o Black de qualquer forma, já que a taxa
dele é pior.

### Equipamento da TrincaPay, e o cupom que já vem embutido no preço (17/09/2026)

TrincaPay vende um único modelo, sem nome comercial próprio — é hardware
Ingenico Move/2500 rebrandeado (a marca só troca o que aparece no visor),
e a própria página só chama de "a Trinca Pay"/"minha Trinca Pay". Cadastrado
como **"TrincaPay"** (mesmo espírito de FacilityPay/SidePay/Yelly com o
PagBank: cataloga-se o nome que a revendedora usa, não o do fabricante).

**Preço só existe na página de afiliado do Everton**
(`trincapay.com.br/canal-monetizando/`) — a página institucional não
divulga valor, "interessado deve entrar em contato". `preco_adesao` =
R$ 358,80 (cheio), `preco_adesao_promocional` = R$ 298,80 "à vista", com o
aviso da própria página "Cupom aplicado automaticamente: CANALMONETIZANDO".

**`parcelas_adesao` ficou `null` de propósito, não é lacuna.** A página
anuncia "12x de R$ 29,90" — mas 29,90 × 12 = R$ 358,80, o preço CHEIO, não
os R$ 298,80 à vista. Parcelar aqui abre mão do desconto — o oposto da
regra de toda outra marca cadastrada ("parcela em 12x sem juros sobre o
preço à vista"). `EquipamentoPlano::parcelaDaAdesao()` só sabe dividir o
`preco_adesao_vigente` (promocional quando existe) pelo número de
parcelas — gravar 12 aqui mostraria R$ 24,90, um valor que a TrincaPay
nunca cobra. Sem campo pra "parcela sobre o cheio, não sobre o vigente",
ficou sem declarar parcelamento a mostrar um número errado.

**Achado real, corrigido com uma migration:** como o R$ 298,80 já é o preço
COM o cupom `CANALMONETIZANDO` (16%) aplicado, `EconomiaDoCupom` calculava
16% de cima dele de novo — confirmado na prática, R$ 47,81 de "economia"
que não existe, um desconto contado duas vezes. Diferente do caso
PagBank/Mercado Pago (`cupom.valor` nulo — desconto real mas sem
percentual fixo, varia por equipamento/mês): aqui o percentual é real e
fixo, só não pode virar uma segunda economia em reais — zerar `valor`
teria apagado também o selo "16% off" do cupom (`x-bloco-cupom` lê
`cupom->valor` direto), que continua sendo informação real e válida.

Solução: campo novo `cupons.desconto_ja_no_preco` (boolean, migration
`2026_09_17_090000`), `true` só na TrincaPay. `EconomiaDoCupom::calcular()`
devolve `null` quando esse campo é `true` — mesmo efeito prático do `valor`
nulo (esconde a economia em reais, mantém o resto do cupom intacto), mas
como um conceito distinto: "eu sei o percentual, só não posso somar de
novo". Confirmado com o Everton antes de implementar (ele descreveu a
mecânica exata: "o desconto está somente sobre o preço à vista... a prazo
paga o valor cheio" — bateu com o que a página mostra).

### Fotos e preço cheio dos equipamentos da SumUp (17/09/2026)

Sem cupom de afiliado nesta marca (dito pelo Everton) — página e valores
100% oficiais, `sumup.com/pt-br/maquininhas/`. Equipamento e preço já
existiam desde 15/09/2026 (etapa 17), só faltava foto.

**O preço confirmado em 15/09 era o promocional, não o cheio — só descoberto
ao reler a página hoje.** Ela agora mostra riscado ao lado do promocional
para Solo (de R$ 118,80 por R$ 58,80) e Smart (de R$ 598,80 por R$ 190,80);
Top continua com um preço só (R$ 46,80), sem riscado em lugar nenhum.
Conferido antes de gravar: a parcela que `EquipamentoPlano::parcelaDaAdesao()`
calcula a partir do preço vigente reproduz exatamente os "12x R$X,XX" que a
própria página publica (Top 3,90, Solo 4,90, Smart 15,90) — os três batem.

**Mapeamento nome↔foto exigiu atenção às dimensões, não só à forma na
imagem.** As três fotos que o Everton mandou pareciam, à primeira vista,
combinar melhor na ordem "alta e fina = Solo, quadrada e pequena = Smart" -
errado. A ficha técnica da Solo (`sumup.com/pt-br/maquininhas/solo/`) diz
83×83×17mm, quase um cubo; é a foto pequena e quadrada
("Favor inserir ou aproximar o cartão") que bate com isso, não a alta e
fina (essa é a Smart, com tela de 6,5" — dimensão bem maior, corpo mais
alto). A pasta do Everton no Drive já tinha os arquivos nomeados
corretamente (`smart.webp`/`solo.jpeg`/`top.webp`), o que resolveu de vez
antes de eu errar a foto de novo (ver o episódio da Ton, sessão anterior).

**Continua incompleto, sem eu ter mexido nisso:** as 81 taxas da SumUp
seguem em `rascunho` (0 publicadas) — pendência de aprovação no painel já
existente antes desta sessão, sem relação com equipamento/foto.

### Foto, preço cheio e um `tem_chip_gratis` errado desde a etapa 04, na InfinitePay (17/09/2026)

Único aparelho da marca, já cadastrado como "Maquininha Smart" (o nome
comercial exato que a própria `infinitepay.io` usa — nada a renomear).
Sem parceria/cupom (dito pelo Everton): preço e specs são os oficiais.

**`tem_chip_gratis` estava `true` desde a etapa 04 — errado, corrigido para
`false`.** O FAQ da própria página (`infinitepay.io/maquininha`) responde
"Precisa de chip de dados?" assim: *"A maquininha funciona por Wi-Fi. Dados
móveis são opcionais para usar fora da rede — o chip é adquirido
separadamente."* Não é chip grátis incluso — é o oposto: comprado à parte,
e só necessário fora de uma rede Wi-Fi. Ninguém tinha percebido isso até o
Everton perguntar diretamente "vocês estão pegando os detalhes técnicos
(bobina, chip, wifi)?" nesta sessão — vale lembrar de sempre confirmar
essas três coisas contra o FAQ/specs da própria marca, não só contra as
fotos ou o texto de vendas do topo da página.

**Preço cheio, que não estava capturado (mesmo padrão do achado da
SumUp).** Só o vigente (R$ 199,00) tinha sido gravado na etapa 04. A home
mostra "De: 12x de R$ 79,90 por: 12x de R$ 16,58 ou apenas R$ 199" — cheio
= 79,90 × 12 = R$ 958,80, gravado agora. `199,00 / 12 = 16,58` bate exato
com a parcela publicada.

### Fotos e duas correções de ficha técnica no PagBank, e um plano excluído em produção travando o reseed (17/09/2026)

Os seis aparelhos já existiam desde a etapa 04, preço e nome corretos.
Faltava só foto. Preço conferido de novo contra
`pagbank.com.br/para-seu-negocio/maquininhas` — sem mudança nos seis.

**Duas correções reais, achadas na página individual de cada aparelho**
(que tem "Ficha técnica" e, no rodapé, um grid comparativo mais granular
que a listagem usada na etapa 04):

- **Moderninha Plus 2 não imprime comprovante** — `imprime_comprovante`
  estava `true`. O texto do produto diz "Envio de comprovante por SMS", e
  o grid comparativo confirma "Comprovante por SMS" nela, contra "Imprime
  comprovante" nas outras que realmente imprimem.
- **Minizinha NFC 2 não tem chip de dados próprio** — `tem_chip_gratis`
  era fixo `true` para as seis no código. Das seis, é a única cuja página
  nunca diz "(chip grátis)" — diz só "Conexão por Bluetooth (precisa de
  celular)". As outras cinco repetem literalmente "Não precisa de celular
  (chip grátis)". Coerente com `exige_celular`: quem depende do celular
  para conectar não carrega chip de dados próprio. `tem_chip_gratis`
  deixou de ser hardcoded fora do array de aparelhos e passou a vir por
  equipamento.

**Preço de ProFit e Minizinha NFC 2, esclarecido com o Everton via print
do carrinho.** Antes de mexer no código, encontrei três valores diferentes
para a ProFit em três páginas do próprio PagBank: R$ 83,88 (home,
já cadastrado), R$ 95,88 (`loja.pagbank.com.br`, tanto pelo link de
afiliado do Everton quanto pela página individual do produto) e R$ 75,49
(uma landing específica com "91% OFF"). O Everton testou o carrinho pelo
próprio link dele: subtotal R$ 95,88 − R$ 12,00 de desconto automático
(sem cupom digitado, só por vir do link `cm=vzArXydV`) = **R$ 83,88** — o
mesmo valor já cadastrado, vindo da home. Preço não mudou; ficou registrado
que "o link não tem vantagem" (dito por ele antes de testar) não era bem
exato — o link aplica R$ 12 automático que, coincidentemente ou não,
devolve o mesmo preço da home.

**Achado real, não relacionado a equipamento, que bloqueou o reseed em
produção:** rodar `db:seed --class=PagBankSeeder` falhava com
`SQLSTATE[23000]... Duplicate entry '1-taxas-iniciais'`. Investigado: o
plano "Taxas iniciais" (id 1, marca PagBank) está **soft-deleted em
produção desde 16/09/2026 16:50:45** — as 74 taxas dele já estavam todas
em rascunho antes disso, sinal de que foi substituído pelos "Planos
comerciais" (Essencial/Super Max) introduzidos na sessão de 16/09. Achado
um segundo caso irmão, apagado 21 segundos depois: o plano "Padrão" da
SidePay (id 20), sem taxa nenhuma vinculada — os dois exclusões parecem
ter vindo de uma limpeza deliberada no painel, na mesma sessão que corrigiu
a `TabelaDoPlano` e adicionou a coluna "Situação das taxas" (ver seção
"Duas falhas reais em produção" — mesma data).

`SeederDeMarca::plano()` não usa `withTrashed()`, então `Plano::where($chave)->exists()`
não vê a linha soft-deleted e tenta `INSERT`, que colide com o índice único
`planos_marca_id_slug_unique` (que não filtra `deleted_at`). Isso significa
que **desde 16/09/2026, `db:seed --class=PagBankSeeder` está permanentemente
quebrado em produção** — não é falha de rede nem concorrência, reproduz
100% das vezes.

**Contornado na hora sem decidir nada sobre o plano excluído:** como o
código de equipamentos mora dentro de `planosComerciais()` (não num método
próprio), e essa função não toca em "Taxas iniciais" (criado antes, direto
em `run()`), rodei só `planosComerciais()` em produção via Reflection
(`ReflectionMethod::setAccessible(true)` + `invoke()`), pulando a parte
que quebra. As 135 taxas publicadas do PagBank não foram tocadas.

**Resolvido em seguida, no mesmo dia: o Everton confirmou que a exclusão
foi definitiva** ("O sistema deve prever isso, planos podem deixar de
existir, já aconteceu com a Ton... de mudar de 5 para 3 planos, depois
passar para 4"). Duas mudanças:

1. Removida do `PagBankSeeder::run()` a chamada que recriava "Taxas
   iniciais" (o bloco de débito/crédito/parcelado que vinha antes de
   `planosComerciais($marca)`) — a marca não publica mais essa tabela, só
   os planos comerciais.
2. **`SeederDeMarca::plano()` agora verifica `Plano::onlyTrashed()` antes
   de criar/atualizar.** Se a chave (marca + slug) existir só entre os
   soft-deleted, lança `RuntimeException` nomeando o plano, a marca, a
   data da exclusão e o comando de `restore()` caso tenha sido engano — em
   vez do `Duplicate entry` de MySQL sem contexto nenhum. Isso vale para
   qualquer marca, não só o PagBank: o mesmo padrão (plano aposentado no
   painel, seeder desatualizado ainda tentando recriá-lo) pode se repetir
   em qualquer marca à medida que catálogos mudam com o tempo. Testado
   localmente simulando a mesma situação numa marca diferente (soft-delete
   de um plano da SidePay, rodar o seeder dela, confirmar a mensagem clara,
   restaurar): reproduziu e recuperou como esperado.

`db:seed --class=PagBankSeeder` roda direto de novo, sem precisar do
contorno por Reflection.

## A trava da marca (etapa 19, 17/09/2026)

**O erro que gerou isto.** A etapa 17 foi dada como "completa" para PagBank,
Ton, SidePay, FacilityPay, Yelly e TrincaPay olhando só a contagem de taxas
publicadas (534/534, 117/117...). SidePay e FacilityPay tinham `mensalidade`
nula em **todos** os planos, e `MotorDeCalculo::custoDaConta()` trata isso
como "falta dado" sempre — nunca assume zero (regra 4). O Everton achou isso
sozinho, testando o comparador de verdade, depois de eu já ter dito que
estava tudo pronto pra etapa 19. A resposta não podia ser "confiro com mais
cuidado da próxima vez" — é exatamente o tipo de coisa que escapa de uma
conferência manual. Tinha que ser o motor conferindo sozinho, e a decisão de
mostrar ou não tinha que sair da minha revisão e ir para um clique explícito
do Everton, condicionado a essa conferência.

**A regra, em uma frase:** nenhuma marca aparece em lugar nenhum do site
público — comparador, página própria, cupom — sem `aprovada_em` preenchido
**e** sem fechar conta nas quatro formas de pagamento agora mesmo. As duas
coisas, sempre, sem exceção.

**Revisão no mesmo dia: a exceção para "sem dado publicado" foi removida.** A
primeira versão desta trava deixava passar sem aprovação a marca que não
tinha nenhuma taxa nem faixa publicada (Cielo, Rede, GetNet, Stone hoje;
InfinitePay/SumUp/Mercado Pago até a curadoria terminar), apoiada na regra 4
("marca sem dado nunca some, aparece com o motivo"). O Everton pediu o mesmo
critério para todas, sem exceção — essas marcas agora também ficam
completamente invisíveis até terem dado publicado **e** aprovação. Na prática
isso significa: enquanto não houver nenhuma taxa nem faixa publicada para
essas sete marcas, elas nunca vão poder ser aprovadas (`CompletudeDaMarca`
sempre devolve `Nenhum plano cadastrado.` ou `nenhuma taxa nem faixa
publicada.` para uma marca vazia), e é exatamente esse o efeito pretendido —
regra 4 continua valendo *dentro* do admin (a marca não desaparece do
`/admin/marcas`, a pendência dela fica visível ali), só deixou de valer
como estado publicamente visível sem aprovação.

### O que fica pendente, em quatro perguntas

`App\Support\Saude\CompletudeDaMarca::avaliar($marca)` responde exatamente
isso, e devolve `['completa' => bool, 'pendencias' => list<string>]`:

- **Falta imagem de equipamento?** Todo equipamento vinculado a algum plano
  da marca precisa de `imagem_path` — isso o motor não cobra (preço e foto
  são coisas diferentes pra ele), então é conferido à parte.
- **Falta confirmar taxa?** Roda `MotorDeCalculo::avaliarPlanoParaCompletude()`
  (wrapper público do mesmo `avaliarPlano()` privado que o comparador usa —
  zero lógica nova) contra um **cenário de referência**: débito, crédito à
  vista, crédito parcelado em 3x e Pix, todos com valor e quantidade, prazo
  em branco ("tanto faz"). Todo `faltando` que ele devolver vira pendência,
  com o nome do plano na frente. Prazo em branco de propósito: um cenário com
  prazo fixo em "Em 1 dia útil" reprovaria toda marca por causa do Pix, que
  nenhuma marca publica nesse prazo por natureza (achado documentado mais
  abaixo, na revisão do comparador).
- **Falta confirmar custo de adesão?** Está dentro do "falta confirmar taxa"
  acima — `avaliarPlano()` já cobra mensalidade, tarifa de Pix recebido e
  preço do aparelho como parte da mesma conta.
- **Falta o quê mais?** Logo da marca (`logo_url`).

**O que a trava deliberadamente NÃO cobra:** tarifa de saque/TED/Pix enviado
e antecipação avulsa. A tela pública não pergunta mais isso ao lojista (saiu
na mesma revisão do comparador desta sessão) — exigir esses campos aqui
travaria toda marca por um dado que ninguém consegue mais acionar.

### Onde isso mora

- **Migration** `2026_09_17_153701_add_aprovada_em_to_marcas_table.php`:
  `aprovada_em` timestamp nullable em `marcas`. Timestamp e não boolean, no
  mesmo espírito de `data_verificacao` nas taxas — registra quando, não só
  se.
- **`App\Support\Saude\CompletudeDaMarca`** (novo): a lógica acima, com
  `avaliar(Marca $marca)` (busca o array da marca via
  `CatalogoDoComparador::paraMarca()`) e `avaliarArray(array $marca)` (a
  mesma conta a partir de um array já montado — usada pelo próprio catálogo
  ao decidir quem entra no JSON, sem reconsultar o banco por marca).
- **`App\Motor\MotorDeCalculo::avaliarPlanoParaCompletude()`** (novo, público):
  wrapper fino sobre o `avaliarPlano()` privado que `calcular()` já usa — a
  única diferença é ignorar o filtro de faturamento de `planosElegiveis()`,
  porque a pergunta aqui não é "o lojista de hoje pode usar este plano", é
  "este plano, quando alguém cair nele, tem tudo que precisa". Nenhuma linha
  de cálculo nova; os testes de paridade não mudaram.
- **`App\Motor\CatalogoDoComparador::apenasAprovadasECompletas()`** (novo,
  chamado dentro de `montar()` só quando `incluirRascunhos: false`): filtra o
  array de marcas já montado. O modo com rascunho (conferência local antes de
  aprovar) não passa por aqui de propósito — senão o Everton nunca
  conseguiria ver o que falta numa marca ainda não aprovada.
- **`Marca::visiveisNoSite()`** (novo scope): a mesma regra, para as páginas
  que consultam o banco direto em vez do JSON (`/maquininha/{slug}`,
  `/maquininhas`, `/cupom/{slug}`, `/cupons` — etapas 08 e 09, que não seguem
  a regra 9 porque precisam de SSR para SEO). Desde a revisão sem exceção, é
  literalmente `whereNotNull('aprovada_em')` — nada mais. De propósito
  **não** roda `CompletudeDaMarca` a cada visita — motor por marca a cada
  carregamento de página pública custaria caro demais para o que resolve. A
  completude de verdade só é conferida no clique de aprovar e na geração do
  JSON, que são ações raras, não páginas de visitante — se algo ficar
  incompleto depois de aprovado, o JSON já para de incluir a marca sozinho,
  mas a página de marca em si (SSR, sem regenerar nada) pode ficar
  desatualizada até a próxima geração. Aceito conscientemente: é a mesma
  latência que qualquer conteúdo servido do banco já tem.
- **`SitemapController`** ganhou o mesmo `->visiveisNoSite()` nas duas
  consultas (`/maquininha/{slug}` e `/cupom/{slug}`) — sem isso o sitemap
  ficaria anunciando ao Google URL que a trava faz dar 404, o oposto do que
  SEO pede.
- **Painel visual**: `MarcasTable.php` ganhou duas colunas (Completude, No
  site) e duas ações por linha — `VerPendenciasDaMarcaAction` (modal com a
  lista, reaproveitando `CompletudeDaMarca`) e `AprovarMarcaAction` (desabilitada
  enquanto incompleta; clicar de novo numa marca já aprovada revoga — para o
  caso raro de um dado completo virar incompleto depois, deixar a intenção
  registrada em vez de só esperar a próxima geração do JSON tirar a marca em
  silêncio). Mais um filtro "Não aprovadas" na listagem.

### O que isso muda no dia do lançamento

**Depois deste deploy, nenhuma marca com taxa aparece no comparador até
alguém clicar em "Aprovar marca" para cada uma, no `/admin/marcas`.** O
site está fechado (`SITE_EM_BREVE=true`), então não há visitante afetado —
mas o `comparador.json` gerado a partir de agora vai ter só as marcas "sem
dado publicado" até o Everton passar pelo painel novo e aprovar Ton, PagBank,
SidePay, FacilityPay, Yelly e TrincaPay (as que a curadoria já deu como
prontas) uma a uma. **Isso é intencional** — é o próprio propósito da trava:
a decisão de "está pronto" não sai mais de uma revisão em conversa, sai de um
clique condicionado à conferência do motor.

**Conferido:** migration roda limpa; `CompletudeDaMarca::avaliar()` testado
via tinker contra dado real (SidePay local: pegou exatamente "mensalidade do
plano" e "tarifa de Pix recebido" nos dois planos — o mesmo buraco que
motivou tudo isso). Suíte completa rodada por pasta (mesmo contorno de sempre
para o vazamento de memória pré-existente do `ImagemSeguraWebp`):
`tests/Feature/{Admin,Comparador,Marcas,Cupons,Relatos,Console,Api,Motor}` e
`tests/Unit` — tudo verde, exceto o mesmo `MotorSobreACargaRealTest` que já
falhava antes de qualquer mudança de hoje (confirmado com `git stash` no
início desta sessão). Três testes existentes precisaram de fixture mais
completa para continuar válidos depois da trava
(`PaginasDeMarcaTest::test_pagina_completa_com_cupom_nota_e_taxa_publicada`,
os dois de `StatusDoJsonTest` que editam/despublicam taxa) — ajustados para
criar marca genuinamente completa, não só marcada como aprovada. Sete testes
novos em `tests/Feature/Admin/AprovacaoDeMarcaTest.php` cobrem os quatro
cantos da matriz (sem dado / incompleta / completa-mas-não-aprovada /
completa-e-aprovada) e a interação real do botão via Livewire
(`assertTableActionDisabled`/`assertTableActionEnabled`/`callTableAction`).
Conferido visualmente também: `Livewire::test(ListMarcas::class)->html()`
renderizado contra marcas reais do banco local (algumas sintéticas,
removidas depois do teste) — colunas "Completude"/"No site", contagem de
pendências e os dois botões aparecem exatamente como esperado, incluindo os
"2 pendência(s)" corretos em Stone/Cielo/Rede.

**Revisão no mesmo dia (removendo a exceção do "sem dado publicado"):**
`apenasAprovadasECompletas()` e `visiveisNoSite()` voltaram a ser filtros
únicos, sem ramo especial. `SitemapController` ganhou o mesmo scope nas duas
consultas — sem isso o sitemap ficaria com `/maquininha/{slug}` de marca não
aprovada, e essa URL dá 404 agora. Seis testes existentes mais precisaram de
`aprovada_em` na fixture ou de reescrita de expectativa
(`PaginasDeMarcaTest`: a listagem sem marca aprovada agora espera o estado
vazio, não mais "PagBank ... sem dado publicado" — virou dois testes, um
para o vazio e um para marca aprovada com taxa ainda em rascunho; mais os
fixtures de cupom vencido e sitemap; `PaginaDeCuponsTest`: o helper
`criarMarcaAtiva()` ganhou `aprovada_em` para as duas marcas com cupom que a
suíte testa). `AprovacaoDeMarcaTest::test_marca_sem_taxa_nenhuma_...` também
teve a asserção invertida — confirma agora que marca vazia fica invisível,
não mais o oposto. Suíte inteira rodada de novo por pasta depois de todos os
ajustes: mesmo resultado de sempre, só o `MotorSobreACargaRealTest`
pré-existente falhando.

### Regra nova: Máquina Certa não compara custo de conta digital (18/09/2026)

O Everton reportou "tarifa de Pix recebido" pendente pra Ton nos seis planos
e achou, a princípio, que era o mesmo problema do campo de Pix duplicado por
prazo (ver abaixo) — mas são duas coisas diferentes, e a investigação
separou as duas.

**A confusão real, e corrigida**: em `TabelaDoPlano.php` (`gradeDePrazos()`),
o campo de Pix aparecia dentro de **cada** seção de prazo (Na hora, D+1,
D+14, D+30, conforme as parcelas) — até 5 campos de Pix na mesma tela, como
se a taxa dele variasse por prazo de recebimento. Ela não varia: Pix liquida
na hora por natureza, e nenhuma marca do catálogo jamais publicou Pix em
outro prazo (achado já registrado na revisão do comparador, mesma seção
acima). Um campo em branco em "Em 1 dia útil" parecia dado faltando quando
nunca houve o que preencher ali. **Corrigido**: o campo de Pix agora só
existe uma vez, dentro da seção "Na hora" — `$ehNaHora` checa
`$prazo->codigo === PrazoRecebimento::NA_HORA` antes de renderizar o
`TextInput`. Teste de regressão:
`TabelaDoPlanoTest::test_pix_so_tem_campo_na_secao_na_hora`.

**A decisão maior, que veio da pergunta certa**: "tarifa de Pix recebido"
(`Plano.tarifa_pix_recebimento`) é um campo genuinamente separado da taxa
percentual do Pix — editado no formulário do Plano, não na Tabela do Plano —
e representa uma tarifa fixa por receber Pix, cobrada pela **conta digital**
da adquirente. O Everton decidiu que isso está fora do escopo do produto:
**o lojista não é obrigado a usar a conta digital da adquirente** — pode
receber o que a maquininha processa na conta do próprio banco. Nesse caso
nenhuma tarifa de conta digital se aplica, e exigir esse dado (ou comparar
por ele) seria comparar algo que a maioria dos lojistas nunca paga.

**O Máquina Certa compara agora só três coisas**: taxa de venda (débito,
crédito à vista, parcelado, Pix), custo de adesão/aluguel do aparelho, e
mensalidade do plano (quando existe). Nada de conta digital — nem tarifa de
saque, TED, Pix recebido, Pix enviado. (Antecipação avulsa,
`taxa_antecipacao_mensal`, não entra nessa categoria — não é custo de conta
digital, é opcional do lojista sobre a própria operação — e continua fora
do escopo pelo motivo já registrado antes: saiu da tela pública na mesma
revisão do comparador.)

**Onde isso foi tocado — e onde não foi, de propósito:**

- `MotorDeCalculo::custoDaConta()` (PHP) e o gêmeo `custoDaConta()` em
  `motor.mjs`: pararam de ler `tarifa_saque`, `tarifa_ted`,
  `tarifa_pix_recebimento`, `tarifa_pix_envio` e a função auxiliar
  `recebimentosPix()` inteira foi removida — o método agora só soma
  `mensalidade`. Assinatura mudou de `custoDaConta($plano, $cenario)` para
  `custoDaConta($plano)` (o parâmetro `$cenario` não sobrou uso nenhum).
- `App\Support\Saude\CompletudeDaMarca` **não precisou de nenhuma mudança**
  — ela reaproveita o mesmo `avaliarPlano()`/`custoDaConta()` do motor
  público, então a pendência "tarifa de Pix recebido" parou de aparecer de
  graça. Essa é exatamente a razão de ter desenhado a trava reaproveitando o
  motor em vez de duplicar a lista de campos obrigatórios — a correção
  cascateou sem precisar tocar em dois lugares.
- Formulário do Plano (`PlanoForm.php`): a seção "Custos da conta" (3
  colunas: mensalidade, tarifa de saque, TED, Pix recebimento, Pix envio,
  antecipação) virou "Mensalidade e antecipação" (2 colunas) — os quatro
  campos de conta digital saíram do formulário. Manter um campo editável que
  o motor não lê mais seria a mesma armadilha de confusão, só que ao
  contrário.
- **De propósito NÃO removido** (vestigial, sem custo de manter, evita
  mexer em superfície grande sem necessidade real): as colunas
  `planos.tarifa_saque/tarifa_ted/tarifa_pix_recebimento/tarifa_pix_envio`
  continuam no banco e no `Plano::$fillable`; `CatalogoDoComparador` continua
  exportando essas chaves no `conta` do JSON (só que agora sempre ignoradas
  por quem lê); `Cenario::saquesMensais/tedsMensais/pixEnviosMensais`
  continuam existindo (default 0, nunca lidos). Se um dia fizer sentido
  reduzir a superfície de verdade, é uma migration + limpeza de schema à
  parte — não urgente, e misturar isso com a mudança de comportamento de
  hoje só aumentaria o risco à toa.
- `resources/views/components/detalhe-do-resultado.blade.php`: o mapa de
  rótulos da conta (`saques`, `teds`, `pix_envios`, `pix_recebimentos`) foi
  limpo — só `mensalidade` pode aparecer ali agora.

**Conferido:** reproduzido o bug relatado direto em produção antes de
corrigir (`CompletudeDaMarca::avaliar()` contra a Ton real: "tarifa de Pix
recebido" pendente nos 6 planos). Depois da correção, suíte inteira rodada
de novo por pasta — 78 testes em `Admin` (68 + o novo
`test_pix_so_tem_campo_na_secao_na_hora`), e todo o resto igual — nenhuma
regressão, incluindo os testes de paridade PHP/JS (a mudança foi espelhada
nos dois motores). O `MotorSobreACargaRealTest` pré-existente continua sendo
o único caso vermelho, sem relação com esta mudança.

## Manual do administrador (etapa 18)

Página dentro do próprio `/admin` (`/admin/manual`), não PDF — de propósito,
para nunca envelhecer numa pasta esquecida. `App\Filament\Pages\Manual`
(descoberta automática via `discoverPages`, igual às demais páginas do
painel), view em `resources/views/filament/pages/manual.blade.php`. Ícone de
livro, sem grupo de navegação (fica sozinha, no topo do menu,
`navigationSort = -1`) — é a página que qualquer pessoa nova no painel deve
achar primeiro, não uma entre as outras.

Cobre, nesta ordem, os oito pontos pedidos: a ordem certa de cadastro
(adquirente → marca → plano → equipamento → cupom, e por que inverter
quebra), como lançar taxa em lote (Lançamento em Lote vs. Tabela do Plano,
e por que 1x grava como `credito_avista` e 2x–21x como `credito_parcelado`),
o que significa rascunho/publicado/aferido/reportado/vencido em português
de gente, como aprovar e por que `comparador:gerar-json` precisa rodar
depois (com o comando exato via SSH), como ler as duas filas de revisão sem
nunca publicar direto a partir delas, como ler cada cartão do painel de
saúde e o que fazer quando ele fica laranja ou vermelho, o passo a passo de
restaurar um backup (local e do Google Drive), e as três coisas que nunca
se deve fazer.

**Testado com o mesmo padrão dos outros testes de página** (`Livewire::test`,
não requisição HTTP — ver a nota da etapa 16 sobre por que isso é
suficiente para conteúdo, mas não para middleware).
`tests/Feature/Admin/ManualTest.php` cobre: a página monta sem exceção, o
slug é `manual`, os oito pontos do prompt aparecem no texto, a explicação de
1x/2x–21x está presente, o comando `comparador:gerar-json` aparece por
extenso, e os dois avisos centrais ("publicar no painel não muda o site
sozinho" e "as filas de revisão não publicam nada sozinhas") estão no texto.

**Achado rodando a suíte inteira, sem relação com esta etapa:** `php artisan
test` sem filtro estoura memória (`Allowed memory size of 134217728 bytes
exhausted`) dentro de `ImagemSeguraWebp.php:108` (a conversão para WebP via
GD), sempre no mesmo ponto da execução (`ParidadeDoResumoTest`). Confirmado
que não tem relação com o manual: removendo os três arquivos novos desta
etapa e rodando a suíte inteira de novo, o mesmo estouro aconteceu no mesmo
lugar. Parece acúmulo de memória do GD ao longo de uma suíte grande com
vários testes de imagem (nenhum `imagedestroy()` esquecido óbvio encontrado
numa olhada rápida) — os testes envolvidos passam normalmente sozinhos ou
em grupos menores (`--filter`). Não investigado a fundo nem corrigido, por
estar fora do escopo desta etapa; vale revisitar se a suíte completa virar
rotina de CI. **Achado de novo, mais preciso, no mesmo dia:** roda com
`--filter=Admin` sozinho também (não precisa da suíte inteira) — reproduz
com `php artisan test --filter=Admin` puro (memória default de 128M do CLI)
e passa limpo com `php -d memory_limit=1G artisan test --filter=Admin`. Group
`Admin` já tem `BuscaDeImagemPorUrlTest` (várias conversões WebP via GD em
sequência) — segue sendo o suspeito mais provável, ainda não confirmado.

**Achado no mesmo dia, usando o manual pela primeira vez:** o Everton copiou
o comando de `comparador:gerar-json` do manual e o terminal travou em
`quote>` — a aspa de fechamento do comando (que é comprido e quebra em duas
linhas visuais) se perdeu na cópia. Resolvido em duas frentes:

1. **Botão no painel, sem SSH.** `App\Filament\Actions\
   GerarJsonDoComparadorAction` chama `Artisan::call('comparador:gerar-json')`
   em processo (mesmo servidor web, sem shell, sem aspa nenhuma) e mostra o
   total de marcas/taxas do arquivo novo numa notificação. Registrado em
   quatro lugares — todo ponto onde uma taxa é aprovada, mais o primeiro
   lugar que se vê ao entrar no painel:
   - `App\Filament\Pages\Dashboard` (novo — estende `Filament\Pages\
     Dashboard` só para acrescentar este botão no cabeçalho; `Admin
     PanelProvider` foi ajustado para registrar esta classe em vez da
     padrão do pacote — `discoverPages` também a encontraria sozinho, mas
     `Panel::getPages()` já faz `array_unique`, então registrar nos dois
     lugares não duplica rota).
   - `ListTaxaDivulgadas`, `ListFaixaReportadas` (cabeçalho da listagem).
   - `TabelaDoPlano`, `LancamentoEmLote` (ao lado de "Publicar toda a
     tabela"/"Lançar tabela").

   O comando SSH continua existindo — pensado para quando o painel estiver
   fora do ar, não removido — mas o manual agora deixa claro que o botão é
   o caminho recomendado.
2. **O comando no manual saiu do `<code>` inline (que quebra linha dentro
   do parágrafo e é fácil de copiar pela metade) e foi para um `<pre>`
   sem quebra**, no mesmo padrão que a seção de restaurar backup já usava.
   O manual também passou a explicar o que `quote>` significa (aspa não
   fechada) e como sair dali (`'` + Enter, ou `Ctrl+C`), para quem cair
   nisso de novo antes do deploy chegar.

`tests/Feature/Admin/GerarJsonDoComparadorActionTest.php` cobre o botão: ele
existe no Dashboard, e clicar nele de fato regenera o arquivo (conferido
pelo campo `gerado_em`, que o comando real escreve com o timestamp da
execução) e notifica o admin. O teste restaura o `comparador.json` local ao
estado de antes no `tearDown()` — o arquivo é gitignored e não devia mudar
só por rodar a suíte.

### Alerta de mudança pendente ao lado do botão

Pedido do Everton no mesmo dia, ao usar o botão pela primeira vez: um selo
avisando se existe mudança aprovada ainda não refletida no arquivo público.
`App\Support\Saude\StatusDoJson::desatualizado()` decide isso, e
`GerarJsonDoComparadorAction` liga a cor do botão (`warning`/laranja) e um
badge "Pendente" a ele.

**A comparação é por conteúdo, não por `updated_at` de tabela — decisão
deliberada.** Uma heurística ingênua ("algo mudou depois de X") pegaria
toda edição de taxa em rascunho — e o Everton vai editar InfinitePay/SumUp/
Mercado Pago em rascunho por dias (decisão da etapa 17, registrada acima),
sem que isso deva acender alerta nenhum, porque rascunho nunca esteve no
arquivo. `StatusDoJson` resolve isso regenerando o catálogo com a mesma
classe que o comando usa (`CatalogoDoComparador::montar(false)`) e
comparando com o que está gravado no arquivo, ignorando só o campo
`gerado_em` (que muda toda geração, com ou sem mudança de conteúdo). Isso
cobre com precisão os três jeitos reais de o arquivo ficar desatualizado —
aprovação nova, edição de taxa já publicada, despublicação (inclusive
célula apagada, que uma comparação por `updated_at` perderia por completo,
já que a linha deixa de existir) — sem nenhum falso positivo de rascunho.
`tests/Feature/Comparador/StatusDoJsonTest.php::
test_criar_e_editar_taxa_em_rascunho_nao_marca_como_desatualizado` é o teste
que prova exatamente essa distinção.

Resultado cacheado por 1 minuto (`Cache::remember`, mesma família de
`OperacaoWidget`/`TrafegoWidget`) para não recalcular o catálogo inteiro a
cada carregamento do Dashboard — e `StatusDoJson::esquecer()` limpa esse
cache na hora, dentro da própria ação de gerar o JSON, para o selo sumir
assim que a geração termina, sem esperar o minuto passar.

### Revisão do manual (17/09/2026): mais explicação, espaçamento, glossário e diagramas

O Everton achou a primeira versão do manual (etapa 18) sucinta demais.
Reescrita completa de `resources/views/filament/pages/manual.blade.php`,
mantendo os mesmos oito títulos de seção (e os mesmos `id` de âncora) para
não quebrar `tests/Feature/Admin/ManualTest.php`, mas expandindo bastante o
texto de cada uma — sempre explicando o "por quê", não só o "onde clicar".
Espaçamento também aumentado: `space-y-10` → `space-y-16` na página,
`space-y-3` → `space-y-5` dentro de cada seção, e uma linha divisória
(`<hr>`) entre seções, que antes só tinham a borda do próprio `<h2>`.

**Duas coisas novas que o Everton pediu de propósito:**

- **Glossário de termos**, no fim da página (`id="glossario"`), com 19
  termos do domínio em ordem alfabética — de "Adquirente" a "2FA" — cada um
  numa frase ou duas, sem jargão. Implementado como uma lista `@php` simples
  dentro do próprio Blade (não uma tabela do banco nem um arquivo à parte),
  porque é conteúdo estático que só muda quando o domínio muda.
- **Imagens ilustrando menus e funções.** Resolvido com **esquemas
  desenhados em HTML/Tailwind** (divs com borda, setas, uma tabela mockup,
  os cartões do painel de saúde), não capturas de tela reais — o painel
  exige 2FA da conta do Everton, e entrar com senha ou código de acesso de
  qualquer conta, inclusive uma de teste criada só para isso, está fora do
  que o Claude pode fazer (é regra de segurança do próprio Claude, sem
  exceção para conta de teste). Cada esquema é rotulado explicitamente como
  "Esquema ilustrativo" no próprio manual, para não ser confundido com uma
  captura de tela real. Se o Everton quiser trocar algum por um print de
  verdade, é só mandar a imagem.

**Também aproveitado para responder, direto no texto do painel de saúde,
duas perguntas que o Everton fez no chat na mesma sessão:** o que é
`APP_DEBUG` (tela de erro detalhada — vaza código-fonte e configuração para
qualquer visitante se ficar ligada em produção) e o que é "Permissão do
.env" (a permissão Unix do arquivo que guarda todas as senhas do site; `600`
é o valor seguro, restrito ao dono do arquivo).

**Verificação visual, sem entrar no painel de verdade:** renderizado o
componente Livewire da página (`Livewire::test(Manual::class)->html()`),
envolvido num HTML solto com Tailwind via CDN só para inspeção, servido
temporariamente como arquivo estático em `public/` (removido depois) e
aberto no navegador para conferir espaçamento, os quatro esquemas e a
tabela do glossário — sem nenhum login. `tests/Feature/Admin/ManualTest.php`
ganhou dois testes novos: um confere o glossário (título e três termos), o
outro confere que `APP_DEBUG`, "Permissão do .env" e o valor `600` aparecem
explicados na seção do painel de saúde.

### Achado real depois do deploy: o painel nunca teve build próprio de CSS

O Everton mandou print de produção e o manual saiu **sem estilo nenhum** —
texto corrido, sem caixas, sem cor, sem espaçamento. A verificação visual
que eu tinha feito antes de publicar (Tailwind via CDN, ver acima) não
pegou isso porque escondia exatamente o problema real: eu estava conferindo
layout com um Tailwind genérico completo, nunca com o CSS que o Filament
realmente serve em produção.

**Causa raiz:** este painel nunca teve um tema próprio compilado.
`AdminPanelProvider` usava o CSS padrão do pacote Filament
(`public/css/filament/filament/app.css`, ~600 KB, versionado — ver "Armadilha:
os assets do Filament são versionados" na etapa 11), que só contém as
classes que os **componentes internos do próprio Filament** usam. Ele não é
um build de Tailwind que varre as views do projeto — é fixo, gerado uma vez
pelo pacote. Qualquer classe Tailwind usada em Blade **nosso** (não do
Filament) — `rounded-lg`, `bg-amber-50`, `grid-cols-2`, `text-[10px]`, o que
for — simplesmente não existe nesse CSS. A classe fica escrita no HTML, o
navegador não reconhece, e o elemento renderiza sem nenhum estilo. Conferido
direto: `grep -c "rounded-lg{" public/css/filament/filament/app.css` dava
zero.

**Consequência, além do manual:** o mesmo bug já existia, silencioso, em
pelo menos dois lugares de antes desta sessão —
`resources/views/filament/widgets/banco.blade.php` (BancoWidget, etapa 16,
painel de saúde) e `resources/views/filament/imagem-externa/preview.blade.php`
(preview da busca de imagem por URL, etapa 15). Os dois usam classes
Tailwind fora do vocabulário do Filament (`grid-cols-1 lg:grid-cols-2`,
`text-danger-600`, `size-24`...) e provavelmente estavam sem estilo em
produção desde que foram escritos — ninguém tinha reparado, ou reparou e
não relacionou à causa.

**Correção — a raiz, não um remendo no manual:**

```
php artisan make:filament-theme --panel=admin
```

Comando oficial do próprio Filament. Fez tudo sozinho:

1. Criou `resources/css/filament/admin/theme.css`, com `@import` do CSS
   base do Filament e duas diretivas `@source` (sintaxe do Tailwind 4)
   apontando para `app/Filament/**/*` e `resources/views/filament/**/*` —
   agora o painel tem o próprio build de Tailwind, escaneando exatamente as
   pastas onde o código deste projeto vive.
2. Adicionou essa entrada ao `input` do `vite.config.js`.
3. Adicionou `->viteTheme('resources/css/filament/admin/theme.css')` ao
   `AdminPanelProvider` — troca o CSS fixo do pacote pelo compilado.
4. Rodou `npm run build` (bumped `tailwindcss`/`@tailwindcss/vite` de
   `^4.0.0` para `^4.3.3` no `package.json`, versão que o comando exigiu),
   gerando `public/build/assets/theme-*.css` (637 KB) — commitado, porque
   produção não tem Node (mesma convenção do `app-*.css` público, etapa 11).

**Conferido depois, não só assumido:**

- `grep` no `theme-*.css` novo confirma a presença de toda classe usada no
  manual, inclusive as com caractere especial (`bg-white/25`, `text-[10px]`,
  `sm:grid-cols-4`, `dark:bg-amber-900/20`) — a primeira leitura desse grep
  deu zero em todas por escapar a regex errado num loop de shell; refeito
  uma a uma com o escape certo, todas estão lá.
- Também confirma as classes do `BancoWidget` e do preview de imagem —
  os dois ganharam estilo de graça, sem eu ter tocado no código deles.
- `node scripts/verifica-contraste.mjs` continua passando (o `app.css`
  **público** não foi afetado — ele só `@source`ia `storage/framework/views`
  e as views de paginação do Laravel, nunca `resources/views/filament`).
- As suítes `Admin` (71), `Comparador` (23) e `Interface` (10) passando.
- Verificação visual refeita **com o CSS real** (não mais CDN): mesmo
  truque de servir o HTML do componente Livewire como arquivo estático
  temporário em `public/`, desta vez com `<link>` para o
  `theme-*.css` de verdade — as caixas coloridas, os quatro esquemas e o
  glossário conferidos exatamente como vão aparecer em produção, removido
  depois.

**Porque essa arapuca pode voltar:** qualquer Blade novo dentro de
`app/Filament` ou `resources/views/filament` que use uma classe Tailwind
ainda não usada em nenhum outro lugar do projeto **precisa de
`npm run build` antes do deploy** para essa classe entrar no
`theme-*.css` — exatamente a mesma disciplina que já valia para o
`app.css` público desde a etapa 11, agora valendo também pro admin. Se uma
tela nova do painel sair sem estilo, isso é o primeiro lugar a suspeitar.

## Resultado que vende (etapa 20, 18/09/2026)

Pedida pelo Everton olhando o cartão da Ton publicado. Plano completo, em seis
blocos, no `PLANO.md` (etapa 20). O que já está feito fica registrado aqui.

### Bloco 1 — motor (18/09/2026)

**Cupom sem data de fim sumia do resultado.** `cupomVigente()` (PHP e JS)
comparava `valido_ate >= hoje`; com `valido_ate = null`, `null >= '2026-09-18'`
é falso nas duas linguagens. A regra 5 já dizia desde a etapa 17 que
`valido_ate` é opcional — o motor nunca tinha sido atualizado. Todo cupom de
afiliado sem data (o da Ton, `EVERTONLOURENCOBF20`, entre eles) aparecia como
"Sem cupom disponível hoje". Agora nulo em `valido_de`/`valido_ate` é "sem
limite" daquele lado.

**Um prazo só por plano; Pix fora.** Ver "Prazo: a quinta dimensão da chave".

**Ranking sem adesão.** Decisão do Everton: o comparador ordena pelo que sai
todo mês (taxas + mensalidade + aluguel), sem a adesão amortizada — adesão é
custo de entrada e vai aparecer junto do cupom, no bloco do botão de contratar.
`chaveDeOrdenacao()` = total do estado − `adesao.por_mes`. `resumo.diferenca_mensal`
e o topo "melhor/pior" passaram a usar `custo_mensal_recorrente`.

**Avisos fora da tela pública.** `item.avisos` continua no resultado do motor
(teste, diagnóstico), mas o cartão não o imprime mais: eram notas técnicas
("aparelho sem aluguel mensal", que além de tudo diz errado — o aparelho fica
em comodato). A condição da taxa foi para o "?" (ver "Taxa condicionada").

**O botão da tabela promocional não abria com o mouse.** O modal usava
`x-on:click.outside` no painel. Num clique de verdade o navegador roda os
microtasks entre um listener e outro do mesmo evento: o Alpine renderizava o
modal, registrava o `click.outside` na `window`, e o **mesmo** clique de
abertura chegava lá e fechava o modal. Com `el.click()` por script não
acontece (os microtasks só rodam no fim) — por isso o botão parecia funcionar
em teste. Trocado por `x-on:click.self` no fundo escuro. **Vale para qualquer
modal/popover novo: não abrir com clique e fechar com `click.outside` no
elemento recém-criado.**

**Teste que já estava quebrado:** `MotorSobreACargaRealTest` procurava o plano
"Taxas iniciais" do PagBank, excluído em 16/09/2026. Reescrito para a garantia
geral (item incompleto lista a falta e nunca tem `total_mensal`).

**Suíte:** `php artisan test` estoura os 128 MB padrão num teste de upload de
imagem; rodar `php -d memory_limit=2G vendor/bin/phpunit`.

**Preview local sem Herd:** `/Users/Everton/Claude Code/.claude/launch.json`
(fora do repo) sobe `php artisan serve --port=8765`. Para testar com dado real,
copiar o JSON de produção: `curl -s https://maquinacerta.com.br/dados/comparador.json
-o public/dados/comparador.json` (a pasta é ignorada pelo Git).

### Bloco 2 — cartão de resultado enxuto (18/09/2026)

Reforma do cartão do comparador (`resources/views/components/
resultado-comparado.blade.php`), sem mexer no motor — bloco A já tinha
fechado a regra de domínio. Testado no navegador local (porta 8765) contra o
JSON real de produção (Ton, com cupom e plano promocional).

**Dois números só.** `item.formatado.vendas` (o custo mensal só das taxas,
já formatado pelo motor) e `taxa_efetiva_das_vendas` — os dois vêm da mesma
conta (`custos.vendas`), por isso o par combina. `custo_mensal_recorrente`
(que soma mensalidade/aluguel) continua sendo o que ordena o ranking e
aparece no topo da página e na barra fixa do celular — só saiu do corpo do
cartão. "Sobra no mês" e "Custo inicial" (como bloco próprio) saíram: o
segundo virou a linha "Adesão a partir de R$ X" dentro do bloco de CTA.

**Chips de forma de pagamento** (`rotuloCurtoDaVenda()` em `comparador.js`):
rótulo curto (Débito, Crédito, `Nx`, Pix — sem o grupo de bandeiras, que
`rotuloDaVenda()` ainda carrega para a tabela detalhada) + percentual + o
mesmo "?" de condição que a tabela de "Ver simulação" já usava, agora também
aqui. Duas instâncias do mesmo tooltip por cartão (chip e tabela) — código
duplicado de propósito, componente próprio ficaria maior que o ganho.

**Bloco de CTA, sem depender de `eventos_cupom` duplicado.** A rota nova
`GET /ir/{marca}` (`App\Http\Controllers\SaidaMarcaController`) recebe o
`codigo` do cupom **por querystring**, resolvido no JavaScript
(`item.cupom`, já calculado pelo motor) — o controller só confere que o
código ainda está vigente (`Cupom::vigentes()`) e grava o clique, sem
duplicar `cupomVigente()` em PHP. Sem `cupom` na query (marca sem parceria)
ou com um código que não bate mais, cai para `marca.site_url` sem gravar
nada — mesmo espírito defensivo do `EventoCupomController` da etapa 09.
`PaginaOrigemCupom` ganhou o caso `Comparador = 'comparador'` (coluna é
`string(20)`, não `ENUM` do banco — sem migration). Botão "Copiar código"
reusa o mecanismo já existente de `resources/js/app.js`
(`data-copiar`/`rastrearEventoCupom`, delegado em `document`, funciona sem
mudança nenhuma dentro do conteúdo renderizado pelo Alpine). Testes em
`tests/Feature/Cupons/SaidaMarcaTest.php`.

**"Ver simulação" (era "Ver a conta aberta") ficou só com a tabela de linhas
de venda** — os blocos de mensalidade e de adesão amortizada saíram
(`resources/views/components/detalhe-do-resultado.blade.php`): a adesão já
tem linha própria no bloco de CTA, e mensalidade não tinha mais função ali
sem ela.

**Modal da promoção ganhou a tabela promocional × regular lado a lado**
(`linhasComparadasDaPromocao()` em `comparador.js`): pareia cada linha de
venda da tabela promocional com a mesma linha (forma de pagamento, grupo de
bandeiras, parcelas) do **plano permanente da marca no cenário atual**
(`itemPermanenteDaMarca()`). Sem plano permanente no cenário (a exceção das
"promocionais órfãs" da etapa 19), a coluna regular não aparece — nunca um
número inventado. "Regras de elegibilidade" reaproveita
`item.enquadramento.aviso`, que o motor já calcula a partir de
`tipo_enquadramento` (regra 6: nada além do que o dado sustenta — não existe
campo de elegibilidade próprio por marca).

**CTA fixo no celular**: barra `fixed` (`sm:hidden`) ligada a
`melhorItem` (getter novo — o primeiro item de `itensNoEstado('calculado')`,
que o motor já entrega ordenado). Texto do botão é uma versão curta
(`textoContratarCurto()`, só "Contratar" ou "Ir para {marca}") — a frase
inteira ("Contratar com 20,00% de desconto") estourava a largura em 375px e
truncava o preço ao lado. Um `<div class="h-20 sm:hidden">` com o mesmo
`x-show` reserva o espaço embaixo da página para a barra não tampar o
rodapé.

### Bloco C — segunda visualização "Tabela de taxas" (18/09/2026)

Alternância **Cartões | Tabela** (`visualizacaoResultado`, sem entrar na URL —
é só como a tela mostra o mesmo resultado, não muda cenário). A tabela é o
cartão de resultado **transposto**: uma linha por forma de pagamento, colunas
1ª, 2ª, 3ª… da menor para a maior taxa, com logo + nome + taxa em cada célula.

**Casamento das linhas por índice, não por assinatura.** `cenario.vendas`
(gerado uma vez, em `vendasDoMix()`) é mapeado 1 para 1 por `resolverLinhas()`
(`motor.mjs`) para todo plano avaliado — a posição `i` do array `item.vendas`
é sempre a mesma forma de pagamento em qualquer item. `linhasDaTabelaDeTaxas`
(getter em `comparador.js`) usa isso para casar a mesma linha entre marcas só
pelo índice, sem comparar `tipo_operacao`/`parcelas`/`grupo` na mão.

**Pool da tabela: `calculado` + `incompleto`, nunca `promocional`.** Mesma
regra do Everton (17/09/2026) de nunca favorecer o preço de entrada — a
tabela não pode virar uma porta lateral para a promoção aparecer disputando
posição. `faixa_reportada` também fica fora: nunca tem taxa exata por forma
de pagamento, só mediana de relatos.

**Rótulo da linha some com o grupo de bandeiras quando é redundante.**
`rotuloDaVenda()` foi separado em `rotuloBaseDaVenda()` (nome sem o grupo) +
o sufixo `— {grupo}`. A tabela só usa o sufixo quando o mix do lojista de
fato gera duas linhas para a mesma forma de pagamento (Visa/Master e Demais);
com `visaMaster = 100` (padrão da tela), a linha é só "Débito", não "Débito —
Visa e Mastercard" em toda linha sem motivo.

**Célula clicável = `irParaCartao()`**: troca `visualizacaoResultado` para
`'cartoes'` e rola/foca no cartão certo via `id` (`idDoCartaoResultado()`,
`cartao-resultado-{slug}-{plano_id}`, escrito no `<article>` de
`resultado-comparado.blade.php`). Mesmo método usado tanto pela tabela quanto
por qualquer chamador futuro — um id só por marca+plano em toda a tela.

**Celular**: cada linha é seu próprio `overflow-x-auto`, com a célula da
forma de pagamento `sticky left-0`. Testado em 375px arrastando a linha —
a coluna da esquerda fica fixa e as colunas de marca passam por baixo dela.

**Achado ao testar**: o `public/dados/comparador.json` de produção (18/09/2026,
16h) só tem a Ton publicada — as outras marcas cujo painel dizia 100%
publicado na curadoria da etapa 17 (FacilityPay, SidePay, Yelly, TrincaPay)
não aparecem no JSON de hoje. Não investigado nesta sessão (fora do escopo do
bloco C); vale conferir no início da próxima etapa antes de assumir que a
curadoria de 17/09 ainda está no ar do jeito que foi registrada.

### Bloco D — home e navegação (18/09/2026)

Título da home (`resources/views/comparador.blade.php`) virou a pergunta
direta do diagnóstico do Everton — "Qual maquininha cobra menos de você?" —
e o parágrafo abaixo dele saiu. No lugar, uma faixa decorativa com o logo de
cada marca do catálogo (`todasAsMarcas`, a mesma lista do passo 4), cada
`<img>` com `x-on:error` escondendo o próprio item (`x-data="{ logoComErro
}"` por marca, mesmo padrão do grid de marcas) — uma URL de logo quebrada
some da faixa em vez de aparecer como ícone de imagem partida. `aria-hidden`
no contêiner e `alt=""` nas imagens: é decorativo, as mesmas marcas já têm
nome e checkbox acessíveis no passo 4, então a faixa não duplica conteúdo
para leitor de tela.

**Contraste de verdade nos campos.** Até aqui todo campo (`x-campo`, e os
dois `<select>` nativos de parcelas/prazo) tinha `bg-papel` — a mesma cor do
cartão branco que o envolve, então só a borda de 1px (`border-contorno`)
separava campo de cartão. Trocado para `bg-superficie` (o cinza claro da
página) com `border-2`: a combinação já era validada em
`scripts/verifica-contraste.mjs` ("contorno de campo na superficie", 3.55:1
no claro e 5.21:1 no escuro — ambos acima do mínimo de 3:1 da 1.4.11) porque
o `<details>` do passo 2 já a usava, só não tinha chegado aos campos em si.
Mesma troca nos chips de segmento não selecionados (eram `bg-papel`, viraram
`bg-superficie` com `hover:bg-superficie-forte` — o hover antigo,
`hover:bg-superficie`, teria ficado igual ao estado de repouso novo). Os
chips selecionados (`bg-tinta`) e os botões Cartões/Tabela do bloco C não
mudaram — não são "campo" e o diagnóstico do Everton não os citou.

**Botão "Comparar agora"**, `variante="principal"` (a cor de ação, o mesmo
verde de "Escolha por mim") e `tamanho="grande"`, fecha o formulário depois
do passo 4. O cálculo já é reativo — `agendar()` recalcula a cada mudança de
campo, com debounce de 120ms — então o botão não dispara cálculo nenhum;
`irParaResultado()` (novo método em `comparador.js`, ao lado de
`escolhaPorMim()`) só rola até `#resultado` e move o foco para lá, para quem
preencheu tudo ter um lugar óbvio para clicar em vez de descobrir sozinho que
o resultado já está pronto mais abaixo. `class="w-full sm:w-auto"` na
própria tag: largura total no celular, do tamanho do texto a partir de `sm`.

**Rodapé enxuto** (`resources/views/components/rodape.blade.php`): as três
frases de transparência (comissão, degradação do selo, faixa reportada) que
ficavam no fim de todo rodapé saíram — elas já existem com mais detalhe em
`/metodologia` (seções "Comissão e independência do número", "O que o selo
de frescor significa" e "Por que Cielo, Rede, GetNet e Stone aparecem como
faixa", todas de etapas anteriores; nenhum texto novo precisou entrar lá). O
rodapé fica só com logo, data de atualização, links institucionais e a linha
de copyright. A linha curta de divulgação de afiliado que substitui as três
frases perto do CTA já existia desde o bloco B ("Link de parceiro. A taxa é
a mesma do site oficial.", no cartão de resultado) — nada novo precisou
entrar ali.

Testado no navegador local (porta 8765) contra o JSON real de produção
(cenário padrão e com a Ton selecionada), em 375px e desktop, com
`node scripts/verifica-contraste.mjs` passando nos dois temas.
`npm run build` rodado depois da troca de classes Tailwind.

### Bloco E — reforma visual (18/09/2026)

Tokens, não reescrita: quase tudo mora em `resources/css/app.css`, e o markup
só ganhou classes novas onde precisava.

**Tokens que mudaram ou entraram:**

| Token | Antes | Agora | Por quê |
|---|---|---|---|
| `--cor-superficie` (claro) | `#F6F7F9` | `#EEF1F5` | Fundo um degrau mais cinza para o cartão branco saltar. O escuro não mudou (o contraste papel/superfície lá já era grande) |
| `--cor-acao-forte` | — | `#005A4B` / escuro `#7DDCC9` | Hover do botão principal. Antes era `hover:opacity-90`, que **clareava** o verde e baixava o contraste do texto branco; agora escurece (8,19:1 no claro) |
| `--radius-botao` | 6px | 10px | Botão e campo (o `<code>` do cupom também usa) |
| `--radius-bloco` | 10px | 12px | Cartão. `--radius-selo` continua 4px: etiqueta não é controle |
| `--sombra-cartao`, `--sombra-cartao-elevado`, `--sombra-botao` | — | ver `app.css` | Sombras em camadas: uma curta e nítida que assenta, uma longa e difusa que dá altura. Tingidas de navy no claro (preto sujaria o cinza-azulado), pretas e mais fortes no escuro. Viram `shadow-cartao`, `shadow-cartao-elevado` e `shadow-botao` pelo `@theme inline` |

As sombras não são `--cor-*` de hexadecimal, então `verifica-contraste.mjs`
não as lê — e nem precisa: sombra é decorativa, e a borda `regua` de 1px
continua em todo cartão. O script ganhou um par novo: **texto sobre o botão
de ação no hover**. Os dois blocos escuros continuam idênticos (o script
cobra isso).

**`@utility elevavel`** (fim do `app.css`): sombra de repouso + transição de
180ms em `box-shadow` e `transform`; no hover, sombra longa e `translateY(-2px)`.
O hover fica dentro de `@media (hover: hover)` — no toque o `:hover` gruda
depois do toque e o cartão ficaria levantado. Vai só nos cartões que o lojista
percorre para escolher: cartão de resultado (`resultado-comparado`), cartão
de faixa reportada, `<x-cartao-marca>` e `<x-bloco-cupom>`. Blocos estáticos
(passos do formulário, veredito, tabela de taxas, equipamentos, CTA da página
de marca, metodologia, em-breve) ganham só `shadow-cartao`, sem subir.
Avisos (`px-4 py-3 text-sm`) continuam chapados — são recado, não cartão.

**Cor de ação só no que é clicável (e no destaque do 1º lugar).** Três verdes
que não eram clicáveis saíram:
- o bloco inteiro do CTA de `/maquininha/{slug}` era `bg-acao-fundo` com
  borda verde; virou cartão branco (igual ao de "sem cupom" logo abaixo) — o
  verde fica só no botão dentro dele. O valor da economia em reais saiu de
  `text-acao` para `text-tinta`;
- o percentual do cupom em `<x-bloco-cupom>` (`text-acao` → `text-tinta`);
- o selo "Em breve" da em-breve (`acao` → `aferido`).

Continuam verdes, de propósito: botão principal, CTA "Contratar", faixa
"Menor custo no seu cenário" e borda do 1º cartão, o bloco "Menor custo" do
veredito e a 1ª célula de cada linha da tabela de taxas (que é um botão). A
etiqueta `tom="economia"` passa a ser só para o 1º lugar; o "−30% adesão" do
`/guia-visual` foi para `parceiro`.

**Transições.** `<x-botao>` tinha `transition-opacity transition-colors` —
as duas escrevem `transition-property` e a última anulava a primeira. Virou
uma lista explícita (cor, fundo, borda, sombra) em 150ms. Os botões feitos à
mão (CTA do cartão, barra fixa do celular, cookies, formulário de taxa
incorreta) receberam o mesmo tratamento.

**Corrigido de passagem:**
- a descrição do bloco ranqueado ainda dizia "Ordenado pelo custo mensal com a
  adesão diluída" — o ranking perdeu a adesão no bloco A. Agora: "Ordenado pelo
  custo mensal em taxas, mais a mensalidade quando houver";
- dois testes quebrados desde o bloco D (ninguém rodou a suíte):
  `PaginaDoComparadorTest` procurava o título antigo da home, e
  `PaginasLegaisTest` procurava "mesma do site oficial", que saiu do rodapé e
  na `/metodologia` quebra linha no meio. Suíte: 312/312.

**Conferido** no navegador local (porta 8765, JSON de produção) em desktop e
375px (`scrollWidth` = 375), tema claro e escuro, cartões de marca e cupom
pelo `/guia-visual` (a base local não tem marca ativa, então `/maquininhas`
sai vazia localmente). O navegador embutido do Claude não dispara `:hover`
de CSS: o estado elevado foi conferido pela regra compilada no
`public/build` e aplicando o mesmo estilo à mão no cartão.

### Bloco F — perguntas frequentes (18/09/2026)

Página própria `/perguntas-frequentes` com as 10 perguntas do `PLANO.md`
(etapa 20), `FAQPage` em schema.org, e um bloco com 4 delas no fim da home.
Separada da metodologia de propósito: metodologia responde "posso confiar
nos números?"; esta responde "como eu contrato e o que acontece depois?" —
público e busca diferentes.

| Arquivo | Papel |
|---|---|
| `App\Support\PerguntasFrequentes` | As 10 perguntas/respostas (HTML curto) e o `schema()` do FAQPage — fonte única entre a página e o bloco da home |
| `resources/views/perguntas-frequentes.blade.php` | A página, via `Route::view` (conteúdo fixo, sem controller) |
| `resources/views/comparador.blade.php` | O bloco final, com `PerguntasFrequentes::destaque()` |

**Conteúdo fixo mora em código, não em tabela** — mesmo critério já usado
para os presets de segmento (etapa 07): não é dado de catálogo, é texto do
produto. `destaque: bool` por pergunta marca quais das 10 repetem na home,
sem duplicar texto — a home lê o mesmo array, filtrado.

**O `FAQPage` reduz a resposta a texto puro** (`strip_tags` + espaços
colapsados) para o `acceptedAnswer.text` — a tela mostra HTML curto (link
para `/metodologia` ou `/cupons` em duas respostas), o schema não. Testado
que as 10 perguntas do schema batem com as 10 da tela
(`PerguntasFrequentesTest::test_a_pagina_tem_o_faqpage_em_schema_org`).

**Perguntas 6 (a maquininha fica comigo?) e 7 (CNPJ ou CPF?) ficaram
deliberadamente gerais.** O `PLANO.md` pedia resposta conferida contra o
contrato de cada marca parceira — sem esse contrato em mãos nesta sessão, a
resposta virou "isso varia de marca para marca, confirme antes de
contratar" em vez de uma afirmação única que poderia não valer para todas
(regra 6 aplicada a texto, não só a taxa). Registrado como pendência no
`PLANO.md` para uma sessão que releia os contratos.

**Nenhuma rota nova entrou no `sitemap.xml`** — `/metodologia`,
`/privacidade` e `/termos` já não entravam antes desta etapa (só home,
`/maquininhas`, `/cupons` e as páginas por marca/cupom), então
`/perguntas-frequentes` seguiu o mesmo padrão institucional, sem mudar
`SitemapController`.

`Navegacao::principal()` e `Navegacao::rodape()` ganharam a entrada
"Perguntas frequentes" — aparece no cabeçalho de toda página e no rodapé,
como pedido.

**Conferido:** suíte inteira (316/316, `php -d memory_limit=2G vendor/bin/
phpunit` — o mesmo contorno de sempre para o vazamento de memória
pré-existente do `ImagemSeguraWebp`), `npm run build` e
`node scripts/verifica-contraste.mjs` sem alteração de paleta (nenhuma
classe nova fora do vocabulário já existente). Navegador local (porta
8765): página e bloco da home em desktop, 375px (`scrollWidth` = 375) e tema
escuro; accordion abre e fecha; link da resposta 1 para `/metodologia#s-
comissao` funcionando.

## Pendente ao fim da etapa 05

O motor está pronto e testado, mas ele é honesto sobre o que não sabe — e isso
transformou buracos silenciosos da carga em `estado: incompleto` visível. Rodar
um cenário qualquer hoje devolve, em boa parte das marcas, uma lista do que
falta. Essa lista é o trabalho de painel que antecede o comparador ir ao ar.

**Dados que faltam na carga (cada um é um campo no painel):**

- **Ton: Pix a 0% mediante ativação da chave no aplicativo.** O campo
  `taxas_divulgadas.condicao` existe e é exatamente para isso, mas a linha não
  entrou: falta a URL da página do Ton que publica essa condição. Regra 6 não
  admite taxa sem fonte, nem quando a informação está correta.
- **Pix promocional a 0% por 30 dias** existe em algumas marcas. Isso não é
  `condicao` — é plano promocional, e já é representável. Falta identificar
  quais marcas e ler a fonte de cada uma.
- **Parcelamento da adesão declarado só na InfinitePay** (12x). A informação de
  que praticamente todas parcelam em até 12x sem juros é provável, mas provável
  não entra: `parcelas_adesao` fica nulo até cada marca ser lida, e nulo aqui é
  "não declarado", nunca "só à vista".
- **Ton, promoção de entrada: o piso de R$ 2.000 foi descartado.** A carga da
  etapa 04 tinha `faturamento_min = 2000` no plano promocional; ele não tem
  contrapartida nos termos da promoção (30 dias ou R$ 5 mil processados) e
  provavelmente era leitura da faixa vizinha. Confirmar no site antes de
  publicar.
- **Nenhuma outra marca tem plano promocional cadastrado.** Só o Ton foi
  identificado na etapa 04. Se PagBank, SumUp ou InfinitePay tiverem tabela de
  entrada, ela hoje está ausente ou — pior — pode estar misturada como faixa de
  faturamento, do jeito que a do Ton estava. Vale uma passada marca a marca.

- **Ton e PagBank sem `mensalidade`.** InfinitePay e SumUp declaram "sem
  mensalidade" na própria página e entraram com `0`; Ton e PagBank não tiveram o
  campo lido na etapa 04, e nulo ali é "não se sabe", nunca zero. Enquanto
  estiver nulo, todo plano dessas duas sai `incompleto`.
- **SumUp: nenhum equipamento vinculado a plano**, e os aparelhos entraram sem
  preço — a página publica só o valor da parcela, e num dos modelos com dois
  valores sem dizer qual vigora. Qualquer cenário com SumUp fica `incompleto`.
- **PagBank: o equipamento está vinculado só ao plano "Super Max"**, que é
  justamente o que não tem taxa. O plano "Taxas iniciais", que carrega as 74
  taxas, não tem aparelho vinculado — então também sai `incompleto`.
- **Nenhum plano tem `taxa_antecipacao_mensal`.** Um cenário com antecipação
  avulsa ligada num prazo que não embute (D+30, parcela a parcela) reporta a
  falta em vez de estimar. Só a InfinitePay publica prazo longo hoje.
- **Ton e SumUp sem linha de Pix.** O impedimento de schema acabou; o que falta
  é a leitura verificada do percentual, que a etapa 04 não registrou.
- **Voucher continua sem nenhuma taxa** — cenário com vale-refeição é sempre
  `incompleto`.

**Ramos do motor que só têm cobertura sintética, por falta de dado real:**

- **`faixa_reportada`** — não existe uma única linha em `faixas_reportadas`. O
  ramo está implementado e testado contra o catálogo sintético, mas só encosta
  em dado real depois da captação de relatos (etapa 10).
- **Cupom** — não há cupom cadastrado. Regra 5 está implementada (desconta a
  adesão, nunca o percentual) e testada no sintético; a validação com cupom real
  é da etapa 09.

**Decisões tomadas aqui que valem revisitar depois:**

- **`IncideSobre::Equipamento` é tratado igual a `Adesao`** pelo motor: os dois
  descontam o valor de adesão do par equipamento+plano, porque nesse schema o
  preço do aparelho *é* a adesão. Se a etapa 09 precisar separar os dois casos,
  o lugar é `orcamentoDoAparelho()`.
- **Preço nulo no par equipamento+plano entra como zero, com aviso**, quando o
  outro preço está preenchido — é a marca dizendo que aquela forma de cobrança
  não existe ali ("sem aluguel: o aparelho é comprado"). Com os dois nulos, é
  falta. Se algum dia uma marca deixar um preço em branco por descuido, esse
  aviso é o que vai denunciar.
- **Mistura de prazos dentro do mesmo plano gera aviso, não bloqueio.** Se a
  marca vende mesmo a combinação que o motor montou é pergunta de exibição
  (etapa 07), não de cálculo.

**Atributos de comparação que ainda não têm onde morar:**

Não são custo — não entram no cálculo — mas são o que desempata duas marcas de
taxa parecida, e hoje não há campo para eles:

- **Garantia do equipamento**, que varia bastante: algumas marcas dão vitalícia,
  outras 1 ou 2 anos.
- **Frete**, que via de regra é grátis para todo o país, e **chip e bobina**, que
  vêm sem custo. Como são grátis, o efeito no custo é zero e o motor não muda;
  o valor está em poder afirmar isso na página, com fonte.

São campos de catálogo (`equipamentos` e/ou `marcas`) e trabalho de exibição —
etapas 07 e 08. Ficaram de fora da etapa 05 de propósito: acrescentar coluna sem
dado verificado só cria campo vazio, e nenhum deles muda um centavo do cálculo.

**Ainda não existe:**

- ~~**Rota servindo o JSON.**~~ Resolvido na etapa 07, e sem rota: o arquivo em
  `public/dados/comparador.json` é servido estaticamente pelo próprio servidor
  web, e o comparador o consome por `fetch`. Nenhum PHP no caminho (regra 9).
- **Nada publicado.** As 964 taxas estão em rascunho (regra 10), então
  `comparador:gerar-json` sem `--rascunhos` produz arquivo sem número nenhum, e
  avisa disso. A aprovação em lote no painel é o que destrava. Desde a etapa 07,
  a própria página carimba um aviso vermelho quando lê `contem_rascunhos`.

**Corrigido de passagem:** `APP_TIMEZONE=America/Sao_Paulo` e `APP_LOCALE=pt_BR`
faltavam no `.env.example` e no `phpunit.xml` — o `.env` local já os tinha. Sem
isso a suíte rodava em UTC, e o selo de frescor de 45 dias viraria o dia na hora
errada (regra 11).

### Revisão dos cartões, 19/09/2026 (pós-lançamento, pedido do Everton)

- **"Plano X" em todo lugar que cita plano** (cartão, veredito, modais, título da tabela na página da marca) — nunca o nome solto.
- **Cartão mostra só débito e crédito 1x das bandeiras padrão** (`GRUPOS_PADRAO` = `visa_master` e `geral` em `comparador.js`), com o rótulo "Bandeiras Visa e Mastercard" acima. O resto vai no modal "Ver todas as taxas": seletor de plano + alternador "Visa e Mastercard" (padrão) / "Outras bandeiras".
- **Pix nunca vira prazo próprio.** Ele cai na hora, mas a tabela do plano (modal e `TabelaDeTaxasDaMarca` na página da marca) o mostra uma vez só, no fim, repetido nas duas visões de bandeira, com prazo `—`. Vale para qualquer tela nova que liste taxa de um plano.
- **Tabela de taxas → clique na célula** rola até o cartão da marca (`irParaCartao` espera o `x-show` dos Cards abrir). O botão "Cartões" virou "Cards" para não confundir com cartão de crédito/débito.

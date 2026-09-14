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
   A vantagem do link é o cupom de desconto na adesão — entidade `cupons` separada,
   com `validade` obrigatória e ocultação automática ao vencer.
6. **Nenhuma taxa entra sem `fonte` e `data_verificacao`.** Campo vazio é honesto;
   número errado é risco de CDC. Selo de frescor degrada após 45 dias.
7. **Marcas têm `adquirente_subjacente`** — informação de transparência, não de
   deduplicação. Yelly, SidePay e FacilityPay compartilham adquirente mas são
   empresas distintas, com suporte, atendimento e política de adesão próprios, e
   concorrem como opções independentes. O desempate entre marcas de taxa idêntica
   é por reputação e custo total, nunca por adquirente.
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

| Seeder | O que carrega |
|---|---|
| `AdquirentesSeeder` | 8 adquirentes, cada um confirmado no rodapé ou no texto institucional do site da própria marca |
| `BandeirasSeeder` | 13 bandeiras, só as que aparecem em alguma marca já carregada |
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
- **PagBank: os planos Essencial e Super Max entraram sem taxa.** A página que
  os publica dá percentual sem dizer prazo de recebimento nem grupo de
  bandeiras — faltam duas das cinco dimensões da chave da regra 1. As taxas do
  PagBank vêm todas da página Taxas e Tarifas, que é dimensional, e ficam no
  plano "Taxas iniciais", nome que é o que a própria página usa.
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
sai colada no número, sempre, e entra nos avisos do resultado. Pix a 0% **por 30
dias** é outra coisa: isso é plano promocional.

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

Com prazo pedido no cenário, é aquele ou nada — se o plano não vende débito na
hora, o resultado diz que falta, não troca por outro prazo. Com `prazo: null`, o
motor escolhe o mais barato entre os que o plano oferece, por linha de venda,
somando a antecipação avulsa na comparação. Quando isso mistura prazos dentro do
mesmo plano — o caso do "sem antecipação" da InfinitePay, em que débito é D+1 e
crédito é D+30 —, o resultado registra os prazos usados e emite aviso.

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
- **Nenhum logo de marca no guia visual**: `<x-cartao-marca>` cai na inicial da marca
  quando não recebe `logo`. Os arquivos entram com as páginas de marca (etapa 08).

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
- **Logo e identidade visual próprios do Máquina Certa ainda não existem.**
  Decisão do Everton na etapa 11: a identidade específica da marca será
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

### O site publico esta fechado (`SITE_EM_BREVE`)

O portal esta em producao e **fechado ao publico** desde 11/09/2026: toda rota
de `routes/web.php` responde **503** com `Retry-After` e uma pagina "em breve"
marcada `noindex`. O `/admin` continua de pe — e por ele que as taxas serao
aprovadas enquanto o site espera a identidade visual da etapa 14.

`App\Http\Middleware\SiteEmBreve`, ligado por `SITE_EM_BREVE` no `.env`
(`config/site.php`). **Nao e `artisan down`**, que derrubaria o painel junto.

503 e nao 200 de proposito: um "em breve" servido com 200 e pagina real aos
olhos do buscador — ele indexa, e o dominio aparece com esse texto por semanas.
O 503 se desfaz sozinho quando a flag sair; nao ha `robots.txt` para lembrar de
reverter.

**Reabrir o site e trocar `SITE_EM_BREVE` para `false` e rodar o `deploy.sh`.**
E o unico passo da etapa 19.

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
- **Categoria `equipamento_cupom`: nenhuma fonte cadastrada ainda**, para
  nenhuma marca. O schema não guarda URL de preço de equipamento nem de
  termos de cupom — só `url_fonte` em `taxas_divulgadas`/`faixas_reportadas`
  (regra 4) — então não havia de onde puxar um valor real sem inventar.
- **`MONITOR_API_TOKEN` só está preenchido em local.** Falta gerar o valor
  de produção e salvá-lo nos dois lados: `.env` do servidor (config já lê
  `services.monitor.token`) e Settings → Secrets and variables → Actions
  **deste mesmo repositório** no GitHub.
- **Os outros três secrets dos workflows** (`TELEGRAM_BOT_TOKEN`,
  `TELEGRAM_CHAT_ID`, `GEMINI_API_KEY`), no mesmo lugar, ainda não foram
  criados — nenhum deles é algo que o Claude possa gerar sozinho.

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
- [ ] 14 — Identidade visual e reforma da interface
- [ ] 15 — Imagens: logos de marca, equipamentos e bandeiras
- [ ] 16 — Painel de saúde e observabilidade do administrador
- [ ] 17 — Curadoria e validação das taxas
- [ ] 18 — Manual do administrador
- [ ] 19 — Lançamento
- [ ] 20 — Decisão sobre programa de parceiros

**A ordem da 12 em diante foi refeita em 11/09/2026** (o motivo está em
`PLANO.md`). Nada até a 11 mudou. O resumo: a identidade visual própria ainda
não existe e leva dias para ficar pronta, então as etapas que não encostam em
estética passaram na frente, a reforma visual entra antes do lançamento — nunca
depois —, e a curadoria das taxas foi para depois dela, por decisão do Everton:
avaliar taxa e avaliar tela ao mesmo tempo confunde as duas coisas.

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

# Comparador de Maquininhas

Portal comparador de taxas de maquininhas de cartão para microempreendedores.
Projeto do canal Monetizando Negócios (YouTube, +500 mil inscritos).
Monetização: links de afiliado com cupom de desconto na adesão.

## Stack

| Camada | Tecnologia |
|---|---|
| Framework | Laravel 13.30 |
| Admin | Filament 5.7 (painel em `/admin`) |
| Banco | MySQL 8.0.40 |
| PHP | 8.4.23 |
| Ambiente local | Laravel Herd + DBngin (macOS ARM) |
| Produção | Hostinger Cloud Startup + Cloudflare |

Local: `/Users/Everton/Claude Code/Herd/comparador-maquininhas` → http://comparador-maquininhas.test
Repositório: `git@github.com:evertonlourenco/comparador-maquininhas.git` (privado)

Nota: o caminho do projeto contém um espaço ("Claude Code"). Sempre entre aspas em comandos de shell.

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
- **Sem histórico de taxa.** Aprovar é editar no lugar. A etapa 14 (monitor de mudanças)
  vai precisar de uma tabela de staging própria.
- **Sem rastreamento de cliques de cupom** — etapa 09.

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
- Uma etapa por sessão. O plano completo está no documento "Construção do Comparador
  de Maquininhas".

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
- **Sem logo de marca e sem link de afiliado no resultado.** Os dois entram com
  as páginas de marca (etapa 08) e a de cupons (etapa 09) — hoje o cartão mostra
  o cupom que o motor aplicou, mas não leva a lugar nenhum.
- **Navegação do cabeçalho continua vazia**, porque as outras páginas ainda não
  existem. Link morto é pior que cabeçalho sem link.
- **Produtos "celular como maquininha" continuam fora**, como a etapa 04 deixou.
  A pergunta de tratá-los como maquininha ficou para quando houver página de
  marca onde eles caibam.

## Etapas concluídas

- [x] **01** — Ambiente local, Filament, Git e CLAUDE.md
- [x] **02** — Schema do banco
- [x] **03** — Painel admin no Filament
- [x] **04** — Carga dos dados reais
- [x] **05** — Motor de cálculo
- [x] **06** — Identidade visual e design system
- [x] **07** — O comparador
- [ ] 08 — Páginas de marca e listagem
- [ ] 09 — Página de cupons
- [ ] 10 — Metodologia e captação de relatos
- [ ] 11 — Deploy, SSH, backup e commits
- [ ] 12 — Cloudflare, medição e performance
- [ ] 13 — Lançamento
- [ ] 14 — Monitor de mudanças
- [ ] 15 — Decisão sobre programa de parceiros

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

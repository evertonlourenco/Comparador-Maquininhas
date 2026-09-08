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
```

## Regras de domínio inegociáveis

Estas regras vêm da análise de viabilidade e não devem ser simplificadas:

1. **Prazo de recebimento é dimensão da taxa**, não atributo da marca. A chave de uma
   taxa é: marca + plano + tipo de operação + número de parcelas + prazo.
2. **Parcelas são inteiro de 1 a 21**, nunca faixas agrupadas. Agrupar só na exibição.
3. **Plano é entidade própria**, com `tipo_enquadramento`: automatico | escolhido | negociado.
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
| `grupos_bandeiras` | Dimensão: `visa_master`, `demais`, `voucher`. |
| `planos` | Regra 3. Custos da conta: mensalidade, saque, TED, Pix, antecipação avulsa. |
| `equipamentos` | Só o que é do aparelho. Preço não mora aqui. |
| `equipamento_plano` | Adesão e aluguel — variam por plano para o mesmo aparelho. |
| `prazos_recebimento` | Dimensão: `na_hora`, `d_1`, `d_14`, `d_30`, `parcela_a_parcela`. |
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

**Estado da carga, verificado em 08/09/2026** — 960 taxas divulgadas, todas em
rascunho (regra 10), todas com `url_fonte` e `data_verificacao`:

| Marca | Publica tabela | Planos | Taxas |
|---|---|---|---|
| Ton | sim | 6 faixas de faturamento | 528 (2 prazos × 2 grupos × 1x a 21x) |
| InfinitePay | sim | 4 faixas de faturamento | 280 (3 prazos × 2 grupos × 1x a 12x) |
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
- **Pix não tem nenhuma linha**, apesar de InfinitePay e Ton publicarem Pix a
  0%. `grupo_bandeira_id` é NOT NULL e Pix não tem bandeira — não existe grupo
  correto para ele. Escolher um seria inventar dimensão. Decidir isso é da
  etapa 05, junto com o motor de cálculo.
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

## Etapas concluídas

- [x] **01** — Ambiente local, Filament, Git e CLAUDE.md
- [x] **02** — Schema do banco
- [x] **03** — Painel admin no Filament
- [ ] 04 — Carga dos dados reais
- [ ] 05 — Motor de cálculo
- [ ] 06 — Identidade visual e design system
- [ ] 07 — O comparador
- [ ] 08 — Páginas de marca e listagem
- [ ] 09 — Página de cupons
- [ ] 10 — Metodologia e captação de relatos
- [ ] 11 — Deploy, SSH, backup e commits
- [ ] 12 — Cloudflare, medição e performance
- [ ] 13 — Lançamento
- [ ] 14 — Monitor de mudanças
- [ ] 15 — Decisão sobre programa de parceiros

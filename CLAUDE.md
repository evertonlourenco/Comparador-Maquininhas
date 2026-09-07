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

### Limitações conhecidas

- **Taxa por bandeira individual não é representável.** A granularidade é o grupo. Se
  uma marca publicar Amex separada de Elo, cria-se um grupo novo (é linha, não enum —
  não precisa de migration).
- **Sem histórico de taxa.** Aprovar é editar no lugar. A etapa 14 (monitor de mudanças)
  vai precisar de uma tabela de staging própria.
- **Sem rastreamento de cliques de cupom** — etapa 09.

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
- [ ] 03 — Painel admin no Filament
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

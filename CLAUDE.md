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
7. **Marcas têm `adquirente_subjacente`** — Yelly e SidePay publicam taxas idênticas.
   O "escolha por mim" retorna no máximo uma marca por adquirente.
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

<!-- Preenchido na etapa 2. -->

## Convenções

- Commits com prefixo da etapa: `etapa-09: pagina de cupons e rastreamento de cliques`
- Tags nos marcos: `v1.0-lancamento`
- Commit apenas quando solicitado, ao fim de cada etapa.
- Uma etapa por sessão. O plano completo está no documento "Construção do Comparador
  de Maquininhas".

## Etapas concluídas

- [x] **01** — Ambiente local, Filament, Git e CLAUDE.md
- [ ] 02 — Schema do banco
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

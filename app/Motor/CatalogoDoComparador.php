<?php

namespace App\Motor;

use App\Enums\StatusItem;
use App\Enums\StatusPublicacao;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use App\Support\Dinheiro;
use App\Support\Saude\CompletudeDaMarca;
use Illuminate\Support\Carbon;

/**
 * Banco -> array puro. E a unica peca do motor que conhece Eloquent.
 *
 * O array que sai daqui e exatamente o JSON estatico da regra 9 e exatamente o
 * que MotorDeCalculo (PHP) e motor.mjs (JavaScript) recebem como entrada. Ter
 * um so formato entre os tres e o que permite comparar as duas implementacoes
 * sobre os mesmos casos.
 *
 * Duas coisas que este arquivo deliberadamente NAO grava, por serem
 * dependentes da data em que alguem abre a pagina, e nao da data da geracao:
 *
 * - o nivel de frescor (regra 8). Vai data_verificacao crua; quem calcula e o
 *   motor, contra o "hoje" dele. JSON gerado ha 60 dias tem de dizer
 *   "desatualizada" sozinho, e nao repetir o "fresca" do dia em que nasceu.
 * - se um cupom esta vigente (regra 5). Vai valido_de/valido_ate; o motor
 *   esconde o vencido. A ocultacao e automatica de verdade, sem depender de
 *   alguem lembrar de regerar o arquivo.
 */
final class CatalogoDoComparador
{
    public const VERSAO = 1;

    /**
     * @param  bool  $incluirRascunhos  Regra 10: por padrao so entra o que
     *                                  passou por aprovacao humana. O modo com
     *                                  rascunho existe para conferir em
     *                                  localhost antes de publicar, e marca o
     *                                  proprio arquivo com contem_rascunhos.
     */
    public function montar(bool $incluirRascunhos = false): array
    {
        $status = $incluirRascunhos
            ? [StatusPublicacao::Rascunho, StatusPublicacao::Publicado]
            : [StatusPublicacao::Publicado];

        $marcas = $this->marcas($status);

        // A trava da marca (etapa 19, 17/09/2026): so entra no JSON publico
        // quem foi aprovada a mao E continua completa agora - as duas coisas,
        // sempre, sem excecao. So no modo com rascunho ela nao se aplica: esse
        // modo existe justamente para o Everton conferir uma marca ainda
        // incompleta em localhost, antes de aprova-la. Ver
        // App\Support\Saude\CompletudeDaMarca.
        if (! $incluirRascunhos) {
            $marcas = $this->apenasAprovadasECompletas($marcas);
        }

        return [
            'versao' => self::VERSAO,
            'gerado_em' => Carbon::now()->toIso8601String(),
            'contem_rascunhos' => $incluirRascunhos,
            // Constante de trait nao se le pelo trait; TaxaDivulgada usa TemFrescor.
            'dias_ate_degradar' => TaxaDivulgada::DIAS_ATE_DEGRADAR,
            'prazos' => $this->prazos(),
            'grupos' => $this->grupos(),
            'marcas' => $marcas,
        ];
    }

    /**
     * O array cru de uma marca so, no mesmo formato de `marcas()` - para
     * `App\Support\Saude\CompletudeDaMarca` conferir uma marca especifica sem
     * passar pela trava (senao nunca daria para ver o que falta numa marca
     * ainda nao aprovada).
     */
    public function paraMarca(int $marcaId, bool $incluirRascunhos = false): ?array
    {
        $status = $incluirRascunhos
            ? [StatusPublicacao::Rascunho, StatusPublicacao::Publicado]
            : [StatusPublicacao::Publicado];

        return $this->marcas($status, $marcaId)[0] ?? null;
    }

    /**
     * A trava vale sem exceção (decisão do Everton, 17/09/2026, revendo a
     * primeira versão desta regra): mesmo marca sem nenhuma taxa nem faixa
     * publicada (Cielo, Rede, GetNet, Stone, InfinitePay, SumUp, Mercado Pago
     * hoje) fica invisível até ter `aprovada_em` e estar completa — não existe
     * mais o "sem dado publicado" como bloco visível sem aprovação. Uma marca
     * assim só sai do limbo quando tiver pelo menos um plano com taxa ou
     * faixa publicada E alguém clicar em "Aprovar marca" em cima disso.
     *
     * @param  array<int, array<string, mixed>>  $marcas
     */
    private function apenasAprovadasECompletas(array $marcas): array
    {
        if ($marcas === []) {
            return [];
        }

        $ids = array_column($marcas, 'id');

        $aprovadas = Marca::query()
            ->whereIn('id', $ids)
            ->whereNotNull('aprovada_em')
            ->pluck('id')
            ->all();

        return array_values(array_filter(
            $marcas,
            fn (array $marca): bool => in_array($marca['id'], $aprovadas, true)
                && CompletudeDaMarca::avaliarArray($marca)['completa'],
        ));
    }

    /**
     * Mapa por codigo: o motor busca o prazo de uma taxa por ele. Publico
     * porque App\Support\Saude\CompletudeDaMarca monta um catalogo minimo
     * para avaliar um plano isolado sem passar por `montar()` inteiro.
     */
    public function prazos(): array
    {
        return PrazoRecebimento::query()->orderBy('ordem')->get()
            ->mapWithKeys(fn (PrazoRecebimento $p): array => [$p->codigo => [
                'codigo' => $p->codigo,
                'nome' => $p->nome_exibicao,
                'dias' => $p->dias,
                'antecipacao_embutida' => $p->embuteAntecipacao(),
                'ordem' => $p->ordem,
            ]])->all();
    }

    public function grupos(): array
    {
        return GrupoBandeira::query()->orderBy('ordem')->get()
            ->mapWithKeys(fn (GrupoBandeira $g): array => [$g->codigo => [
                'codigo' => $g->codigo,
                'nome' => $g->nome_exibicao,
                'de_pix' => $g->ehDePix(),
                'ordem' => $g->ordem,
            ]])->all();
    }

    /** @param  list<StatusPublicacao>  $status */
    private function marcas(array $status, ?int $apenasMarcaId = null): array
    {
        $marcas = Marca::query()
            ->ativas()
            ->when($apenasMarcaId !== null, fn ($q) => $q->where('id', $apenasMarcaId))
            ->with([
                'adquirente',
                'cupons' => fn ($q) => $q->where('status', StatusItem::Ativo)->orderBy('ordem'),
                'planos' => fn ($q) => $q->ativos()->orderBy('ordem'),
                'planos.equipamentos' => fn ($q) => $q->wherePivot('status', StatusItem::Ativo->value)
                    ->where('equipamentos.status', StatusItem::Ativo)->orderBy('equipamentos.ordem'),
                'planos.taxasDivulgadas' => fn ($q) => $q->whereIn('status', $status)
                    ->with(['grupoBandeira:id,codigo', 'prazoRecebimento:id,codigo']),
                'planos.faixasReportadas' => fn ($q) => $q->whereIn('status', $status)
                    ->with(['grupoBandeira:id,codigo', 'prazoRecebimento:id,codigo']),
            ])
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        return $marcas->map(fn (Marca $marca): array => [
            'id' => $marca->getKey(),
            'nome' => $marca->nome,
            'slug' => $marca->slug,
            'site_url' => $marca->site_url,
            // Etapa 15: mesmo caminho de listagem e pagina de marca — nulo
            // quando a marca nao tem logo, nunca um placeholder generico.
            'logo_url' => $marca->logo_url,
            // Regra 4: quem consome precisa saber de que classe de dado se
            // trata antes de ler qualquer numero.
            'publica_tabela' => (bool) $marca->publica_tabela,
            'adquirente' => $marca->adquirente === null ? null : [
                'nome' => $marca->adquirente->nome,
                'slug' => $marca->adquirente->slug,
            ],
            // Regra 8 do dominio: nota do Reclame Aqui e manual, com data e link.
            'reclame_aqui' => [
                'situacao' => $marca->situacaoReclameAqui(),
                'nota' => Dinheiro::doBanco($marca->reclame_aqui_nota),
                'url' => $marca->reclame_aqui_url,
                'consultado_em' => $marca->reclame_aqui_consultado_em?->toDateString(),
            ],
            'cupons' => $marca->cupons->map(fn ($cupom): array => [
                'codigo' => $cupom->codigo,
                'descricao' => $cupom->descricao,
                // Etapa 17: PagBank/Mercado Pago tem desconto real sem valor
                // conhecido - tipo_desconto e valor ficam nulos nesse caso.
                'tipo_desconto' => $cupom->tipo_desconto?->value,
                // Regra 5: nao existe cupom que mexa em percentual de taxa.
                'incide_sobre' => $cupom->incide_sobre->value,
                'valor' => Dinheiro::doBanco($cupom->valor),
                'valido_de' => $cupom->valido_de?->toDateString(),
                'valido_ate' => $cupom->valido_ate?->toDateString(),
                'equipamento_id' => $cupom->equipamento_id,
                'link_afiliado' => $cupom->link_afiliado,
            ])->values()->all(),
            'planos' => $marca->planos->map(fn ($plano): array => [
                'id' => $plano->getKey(),
                'nome' => $plano->nome,
                'slug' => $plano->slug,
                'tipo_enquadramento' => $plano->tipo_enquadramento->value,
                'faturamento_min' => Dinheiro::doBanco($plano->faturamento_min),
                'faturamento_max' => Dinheiro::doBanco($plano->faturamento_max),
                'compromisso' => $plano->compromisso,
                // Etapa 05: preenchido so no enquadramento promocional. Os dois
                // limites valem em disjuncao - o que vier antes. Nulo em um
                // deles e "esse limite nao existe", nunca zero.
                'promocao' => $plano->ehPromocional() ? [
                    'dias' => $plano->promocional_dias,
                    'valor_processado' => Dinheiro::doBanco($plano->promocional_valor_processado),
                    'sucessor_id' => $plano->promocional_sucessor_id,
                ] : null,
                // Custo da conta. Nulo aqui e "nao se sabe", nunca zero - o
                // motor trata os dois de forma diferente de proposito.
                'conta' => [
                    'mensalidade' => Dinheiro::doBanco($plano->mensalidade),
                    'tarifa_saque' => Dinheiro::doBanco($plano->tarifa_saque),
                    'tarifa_ted' => Dinheiro::doBanco($plano->tarifa_ted),
                    'tarifa_pix_recebimento' => Dinheiro::doBanco($plano->tarifa_pix_recebimento),
                    'tarifa_pix_envio' => Dinheiro::doBanco($plano->tarifa_pix_envio),
                    'taxa_antecipacao_mensal' => Dinheiro::doBanco($plano->taxa_antecipacao_mensal),
                ],
                'equipamentos' => $plano->equipamentos->map(fn ($equipamento): array => [
                    'id' => $equipamento->getKey(),
                    'nome' => $equipamento->nome,
                    'slug' => $equipamento->slug,
                    'tipo' => $equipamento->tipo->value,
                    'preco_adesao' => Dinheiro::doBanco($equipamento->pivot->preco_adesao),
                    'preco_adesao_promocional' => Dinheiro::doBanco($equipamento->pivot->preco_adesao_promocional),
                    'parcelas_adesao' => $equipamento->pivot->parcelas_adesao,
                    'aluguel_mensal' => Dinheiro::doBanco($equipamento->pivot->aluguel_mensal),
                ])->values()->all(),
                'taxas' => $plano->taxasDivulgadas->map(fn ($taxa): array => [
                    'tipo_operacao' => $taxa->tipo_operacao->value,
                    'grupo' => $taxa->grupoBandeira->codigo,
                    'parcelas' => $taxa->parcelas,
                    'prazo' => $taxa->prazoRecebimento->codigo,
                    'percentual' => Dinheiro::doBanco($taxa->percentual),
                    'valor_fixo' => Dinheiro::doBanco($taxa->valor_fixo),
                    // O que o lojista precisa fazer para este numero valer.
                    // Vai junto do numero, sempre - nao e nota interna.
                    'condicao' => $taxa->condicao,
                    'data_verificacao' => $taxa->data_verificacao?->toDateString(),
                    'url_fonte' => $taxa->url_fonte,
                ])->values()->all(),
                // Classe B, em chave separada e com nomes que nao permitem
                // confundir mediana com percentual publicado (regra 4).
                'faixas' => $plano->faixasReportadas->map(fn ($faixa): array => [
                    'tipo_operacao' => $faixa->tipo_operacao->value,
                    'grupo' => $faixa->grupoBandeira->codigo,
                    'parcelas' => $faixa->parcelas,
                    'prazo' => $faixa->prazoRecebimento->codigo,
                    'mediana' => Dinheiro::doBanco($faixa->percentual_mediana),
                    'minimo' => Dinheiro::doBanco($faixa->percentual_minimo),
                    'maximo' => Dinheiro::doBanco($faixa->percentual_maximo),
                    'n_relatos' => $faixa->n_relatos,
                    'periodo_inicio' => $faixa->periodo_inicio?->toDateString(),
                    'periodo_fim' => $faixa->periodo_fim?->toDateString(),
                    'data_verificacao' => $faixa->data_verificacao?->toDateString(),
                    'fonte_descricao' => $faixa->fonte_descricao,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }
}

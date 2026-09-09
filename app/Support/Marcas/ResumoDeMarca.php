<?php

namespace App\Support\Marcas;

use App\Models\Marca;
use App\Support\Dinheiro;

/**
 * Os quatro dados do cartão de listagem (etapa 08): logo (lido direto do
 * model), mensalidade, prazo mais rápido e faixa de taxa.
 *
 * Não é o motor de cálculo (App\Motor): não há cenário de lojista aqui, só o
 * resumo do catálogo publicado, para a grade de /maquininhas.
 *
 * Espera a marca com 'planos' (ativos, permanentes), 'taxasDivulgadas'
 * (publicadas, com prazoRecebimento) e 'faixasReportadas' (publicadas, com
 * prazoRecebimento) já carregados — ver MarcaController::index().
 */
final class ResumoDeMarca
{
    /** @return array{mensalidade: ?array, prazo: ?string, faixa_taxa: ?array} */
    public static function paraCartao(Marca $marca): array
    {
        return [
            'mensalidade' => self::mensalidade($marca),
            'prazo' => self::prazoMaisRapido($marca),
            'faixa_taxa' => self::faixaDeTaxa($marca),
        ];
    }

    /**
     * A mensalidade do plano permanente mais barato, entre os ativos. Nulo
     * quando nenhum plano declarou o campo (regra 6: não se sabe, nunca
     * zero) — só vira "sem mensalidade" quando algum plano de fato tem 0.
     */
    private static function mensalidade(Marca $marca): ?array
    {
        $valores = $marca->planos
            ->pluck('mensalidade')
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => (float) $v);

        if ($valores->isEmpty()) {
            return null;
        }

        $menor = $valores->min();

        return [
            'valor' => $menor,
            'gratis' => $menor <= 0.0,
            'formatado' => $menor <= 0.0 ? 'Sem mensalidade' : 'A partir de '.Dinheiro::real($menor).'/mês',
        ];
    }

    /**
     * Regra 4: quem não publica tabela só tem faixa reportada — o prazo mais
     * rápido vem de lá, nunca de uma taxa divulgada que não existe.
     */
    private static function prazoMaisRapido(Marca $marca): ?string
    {
        $prazos = $marca->publica_tabela
            ? $marca->taxasDivulgadas->pluck('prazoRecebimento')->filter()
            : $marca->faixasReportadas->pluck('prazoRecebimento')->filter();

        return $prazos->sortBy('ordem')->first()?->nome_exibicao;
    }

    /**
     * Regra 4 no cartão: a faixa de uma marca que não publica tabela vem da
     * mediana dos relatos, e sai marcada como tal — nunca lida como o mesmo
     * número de quem publica.
     */
    private static function faixaDeTaxa(Marca $marca): ?array
    {
        if ($marca->publica_tabela) {
            $percentuais = $marca->taxasDivulgadas->pluck('percentual')->map(fn ($v) => (float) $v);

            if ($percentuais->isEmpty()) {
                return null;
            }

            return [
                'classe' => 'divulgada',
                'minimo' => $percentuais->min(),
                'maximo' => $percentuais->max(),
                'formatado' => Dinheiro::percentual($percentuais->min()).' a '.Dinheiro::percentual($percentuais->max()),
            ];
        }

        $medianas = $marca->faixasReportadas->pluck('percentual_mediana')->map(fn ($v) => (float) $v);

        if ($medianas->isEmpty()) {
            return null;
        }

        return [
            'classe' => 'reportada',
            'minimo' => $medianas->min(),
            'maximo' => $medianas->max(),
            'formatado' => Dinheiro::percentual($medianas->min()).' a '.Dinheiro::percentual($medianas->max()),
        ];
    }
}

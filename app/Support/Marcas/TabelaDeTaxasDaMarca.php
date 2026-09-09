<?php

namespace App\Support\Marcas;

use App\Enums\TipoOperacao;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Motor\VendaDoCenario;

/**
 * "Tabela de taxas completa por plano e prazo" da página individual de marca
 * (etapa 08). Cada plano vira um bloco de tabela próprio.
 *
 * Regra 4: nunca mistura taxa divulgada com faixa reportada — mas isso nunca
 * precisa ser decidido linha a linha aqui, porque a marca inteira é de uma
 * classe só (o próprio model recusa gravar a outra em TaxaDivulgada::booted()).
 *
 * Espera 'planos.taxasDivulgadas.grupoBandeira'/'prazoRecebimento' e
 * 'planos.faixasReportadas.grupoBandeira'/'prazoRecebimento' já carregados.
 */
final class TabelaDeTaxasDaMarca
{
    /** @return list<array{titulo: string, classe: string, plano: Plano, linhas: list<array>}> */
    public static function montar(Marca $marca): array
    {
        $grupos = GrupoBandeira::query()->orderBy('ordem')->get()
            ->mapWithKeys(fn (GrupoBandeira $g): array => [$g->codigo => ['nome' => $g->nome_exibicao]])
            ->all();

        return $marca->planos
            ->map(fn (Plano $plano): ?array => $marca->publica_tabela
                ? self::blocoDivulgado($plano, $grupos)
                : self::blocoReportado($plano, $grupos))
            ->filter()
            ->values()
            ->all();
    }

    private static function blocoDivulgado(Plano $plano, array $grupos): ?array
    {
        if ($plano->taxasDivulgadas->isEmpty()) {
            return null;
        }

        $linhas = $plano->taxasDivulgadas
            ->sortBy(fn ($t) => sprintf('%02d-%s-%02d', $t->prazoRecebimento->ordem, $t->tipo_operacao->value, $t->parcelas))
            ->map(fn ($t): array => [
                'rotulo' => self::rotulo($t->tipo_operacao, $t->grupoBandeira->codigo, $t->parcelas, $grupos),
                'percentual' => (float) $t->percentual,
                'fixo' => (float) $t->valor_fixo > 0 ? (float) $t->valor_fixo : null,
                'prazo' => $t->prazoRecebimento->nome_exibicao,
                'condicao' => $t->condicao,
                'data_verificacao' => $t->data_verificacao,
                'url_fonte' => $t->url_fonte,
            ])
            ->values()
            ->all();

        return [
            'titulo' => $plano->nome,
            'classe' => 'divulgada',
            'plano' => $plano,
            'linhas' => $linhas,
        ];
    }

    private static function blocoReportado(Plano $plano, array $grupos): ?array
    {
        if ($plano->faixasReportadas->isEmpty()) {
            return null;
        }

        $linhas = $plano->faixasReportadas
            ->sortBy(fn ($f) => sprintf('%02d-%s-%02d', $f->prazoRecebimento->ordem, $f->tipo_operacao->value, $f->parcelas))
            ->map(fn ($f): array => [
                'rotulo' => self::rotulo($f->tipo_operacao, $f->grupoBandeira->codigo, $f->parcelas, $grupos),
                'minimo' => (float) $f->percentual_minimo,
                'mediana' => (float) $f->percentual_mediana,
                'maximo' => (float) $f->percentual_maximo,
                'relatos' => $f->n_relatos,
                'data_verificacao' => $f->data_verificacao,
                'url_fonte' => $f->url_fonte,
            ])
            ->values()
            ->all();

        return [
            'titulo' => $plano->nome,
            'classe' => 'reportada',
            'plano' => $plano,
            'linhas' => $linhas,
        ];
    }

    /** Reaproveita o mesmo rótulo que a etapa 07 já usa na tela do lojista. */
    private static function rotulo(TipoOperacao $tipo, string $grupo, int $parcelas, array $grupos): string
    {
        return VendaDoCenario::deArray([
            'tipo_operacao' => $tipo->value,
            'grupo' => $grupo,
            'parcelas' => $parcelas,
        ])->rotulo($grupos);
    }
}

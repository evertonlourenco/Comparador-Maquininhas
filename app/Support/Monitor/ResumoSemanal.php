<?php

namespace App\Support\Monitor;

use App\Models\Cupom;
use App\Models\FaixaReportada;
use App\Models\TaxaDivulgada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Etapa 13: os dois numeros do resumo semanal do monitor no Telegram. As
 * mesmas contas que já alimentam os dois primeiros cartões de
 * App\Filament\Widgets\PainelInicial — extraídas para cá para não existirem
 * duas fontes da verdade sobre "quantos dias é alerta antecipado" e "quantos
 * dias faltam para o cupom vencer".
 */
class ResumoSemanal
{
    /** Etapa 16 (painel inicial): mais cedo que o selo de frescor (45 dias, regra 8), para dar tempo de agir. */
    public const DIAS_ALERTA_VERIFICACAO = 30;

    public const DIAS_ALERTA_CUPOM = 7;

    public static function taxasSemVerificacaoHaMaisDe30Dias(): int
    {
        $limite = Carbon::today()->subDays(self::DIAS_ALERTA_VERIFICACAO);

        return TaxaDivulgada::query()->where('data_verificacao', '<', $limite)->count()
            + FaixaReportada::query()->where('data_verificacao', '<', $limite)->count();
    }

    /** @return Collection<int, array{marca: string, codigo: string, valido_ate: string}> */
    public static function cuponsVencendoEm7Dias(): Collection
    {
        return Cupom::query()
            ->with('marca:id,nome')
            ->vigentes()
            ->whereDate('valido_ate', '<=', Carbon::today()->addDays(self::DIAS_ALERTA_CUPOM))
            ->orderBy('valido_ate')
            ->get()
            ->map(fn (Cupom $cupom): array => [
                'marca' => $cupom->marca->nome,
                'codigo' => $cupom->codigo,
                'valido_ate' => $cupom->valido_ate->format('d/m/Y'),
            ]);
    }

    /** @return array{taxas_sem_verificacao_30_dias: int, cupons_vencendo_7_dias: Collection} */
    public static function gerar(): array
    {
        return [
            'taxas_sem_verificacao_30_dias' => self::taxasSemVerificacaoHaMaisDe30Dias(),
            'cupons_vencendo_7_dias' => self::cuponsVencendoEm7Dias(),
        ];
    }
}

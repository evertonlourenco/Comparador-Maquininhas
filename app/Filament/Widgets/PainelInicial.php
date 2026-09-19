<?php

namespace App\Filament\Widgets;

use App\Enums\StatusRevisao;
use App\Filament\Resources\Cupons\CupomResource;
use App\Filament\Resources\DeteccoesDeMudanca\DeteccaoDeMudancaResource;
use App\Filament\Resources\Marcas\MarcaResource;
use App\Filament\Resources\PropostasRecebidas\PropostaRecebidaResource;
use App\Filament\Resources\RelatosTaxaIncorreta\RelatoTaxaIncorretaResource;
use App\Filament\Resources\TaxaDivulgadas\TaxaDivulgadaResource;
use App\Models\Cupom;
use App\Models\DeteccaoDeMudanca;
use App\Models\Marca;
use App\Models\PropostaRecebida;
use App\Models\RelatoTaxaIncorreta;
use App\Support\Monitor\ResumoSemanal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PainelInicial extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $taxasNaoVerificadas = ResumoSemanal::taxasSemVerificacaoHaMaisDe30Dias();

        $cuponsVencendo = ResumoSemanal::cuponsVencendoEm7Dias()->count();

        $marcasSemTaxa = Marca::query()
            ->whereDoesntHave('taxasDivulgadas')
            ->whereDoesntHave('faixasReportadas')
            ->count();

        // Etapa 10: as duas filas de revisão humana da captação de relatos —
        // regra 10 na forma mais forte, nada delas publica sozinho.
        $propostasPendentes = PropostaRecebida::query()->where('status', StatusRevisao::Pendente)->count();
        $relatosTaxaPendentes = RelatoTaxaIncorreta::query()->where('status', StatusRevisao::Pendente)->count();

        // Etapa 13: o monitor de mudancas (repositorio Node separado) so
        // propoe aqui — nunca publica sozinho (regra 10).
        $deteccoesPendentes = DeteccaoDeMudanca::query()->pendentes()->count();

        // Etapa 16, prioridade 1: `links:verificar` (cron diário) grava isto.
        // Link nunca verificado não entra na contagem de quebrado — ele
        // ainda não teve chance de falhar, e contar como quebrado inventaria
        // um alerta que o comando ainda não deu.
        $linksQuebrados = Marca::query()->comLinkQuebrado()->count()
            + Cupom::query()->comLinkQuebrado()->count();

        // Nota do Reclame Aqui e manual (regra 8), uma consulta por mes: marca
        // aprovada sem nota, sem data ou consultada ha mais de 30 dias.
        $notasRaDesatualizadas = Marca::query()
            ->ativas()
            ->whereNotNull('aprovada_em')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('reclame_aqui_situacao')->whereNull('reclame_aqui_nota'))
                ->orWhereNull('reclame_aqui_consultado_em')
                ->orWhere('reclame_aqui_consultado_em', '<', now()->subDays(30)->toDateString()))
            ->count();

        return [
            Stat::make('Notas do Reclame Aqui desatualizadas', $notasRaDesatualizadas)
                ->description('Marcas no ar sem nota ou consultadas há +30 dias')
                ->color($notasRaDesatualizadas > 0 ? 'danger' : 'success')
                ->url(MarcaResource::getUrl())
                ->icon('heroicon-o-star'),
            Stat::make('Links de afiliado quebrados', $linksQuebrados)
                ->description('Site da marca ou link de cupom fora do ar (HEAD diário)')
                ->color($linksQuebrados > 0 ? 'danger' : 'success')
                ->url(MarcaResource::getUrl())
                ->icon('heroicon-o-link-slash'),
            Stat::make('Taxas não verificadas há +30 dias', $taxasNaoVerificadas)
                ->description('Somando taxas divulgadas e faixas reportadas')
                ->color($taxasNaoVerificadas > 0 ? 'danger' : 'success')
                ->url(TaxaDivulgadaResource::getUrl())
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Cupons vencendo em 7 dias', $cuponsVencendo)
                ->description('Cupons vigentes perto do vencimento')
                ->color($cuponsVencendo > 0 ? 'warning' : 'success')
                ->url(CupomResource::getUrl())
                ->icon('heroicon-o-ticket'),
            Stat::make('Marcas sem taxa cadastrada', $marcasSemTaxa)
                ->description('Nem taxa divulgada, nem faixa reportada')
                ->color($marcasSemTaxa > 0 ? 'warning' : 'success')
                ->url(MarcaResource::getUrl())
                ->icon('heroicon-o-building-storefront'),
            Stat::make('Propostas pendentes de revisão', $propostasPendentes)
                ->description('Relatos de /enviar-proposta ainda não revisados')
                ->color($propostasPendentes > 0 ? 'warning' : 'success')
                ->url(PropostaRecebidaResource::getUrl())
                ->icon('heroicon-o-inbox-arrow-down'),
            Stat::make('Relatos de taxa incorreta pendentes', $relatosTaxaPendentes)
                ->description('Avisos do botão "reportar taxa errada" ainda não revisados')
                ->color($relatosTaxaPendentes > 0 ? 'warning' : 'success')
                ->url(RelatoTaxaIncorretaResource::getUrl())
                ->icon('heroicon-o-flag'),
            Stat::make('Detecções do monitor pendentes', $deteccoesPendentes)
                ->description('Mudanças e falhas de coleta ainda não revisadas')
                ->color($deteccoesPendentes > 0 ? 'warning' : 'success')
                ->url(DeteccaoDeMudancaResource::getUrl())
                ->icon('heroicon-o-signal'),
        ];
    }
}

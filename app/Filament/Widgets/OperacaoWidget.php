<?php

namespace App\Filament\Widgets;

use App\Support\Saude\StatusDeOperacao;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Etapa 16, prioridade 3: um lugar só para "o site está bem?" sem abrir SSH
 * nem hPanel. As seis contas moram em App\Support\Saude\StatusDeOperacao;
 * esta classe só escolhe cor, texto e ícone.
 */
class OperacaoWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            $this->statBackup(),
            $this->statFila(),
            $this->statSsl(),
            $this->statAppDebug(),
            $this->statPermissaoEnv(),
            $this->statDoisFatores(),
        ];
    }

    private function statBackup(): Stat
    {
        $b = StatusDeOperacao::ultimoBackup();

        if ($b['sem_dado']) {
            return Stat::make('Backup diário', 'Sem dado')
                ->description($b['motivo'])
                ->color('gray')
                ->icon('heroicon-o-circle-stack');
        }

        $rotulo = match ($b['status']) {
            'ok' => 'Ok',
            'falha' => 'Falhou',
            default => 'Incompleto',
        };

        $cor = match ($b['status']) {
            'ok' => 'success',
            'falha' => 'danger',
            default => 'warning',
        };

        $descricao = collect([
            $b['quando']?->format('d/m/Y H:i'),
            $b['motivo'],
            $b['detalhe'],
        ])->filter()->implode(' — ');

        return Stat::make('Backup diário', $rotulo)
            ->description($descricao ?: null)
            ->color($cor)
            ->icon('heroicon-o-circle-stack');
    }

    private function statFila(): Stat
    {
        $fila = StatusDeOperacao::jobsNaFila();

        return Stat::make('Jobs pendentes na fila', $fila['pendentes'])
            ->description("{$fila['falhos']} job(s) com falha registrada")
            ->color(match (true) {
                $fila['falhos'] > 0 => 'danger',
                $fila['pendentes'] > 0 => 'warning',
                default => 'success',
            })
            ->icon('heroicon-o-queue-list');
    }

    private function statSsl(): Stat
    {
        $ssl = StatusDeOperacao::certificadoSsl();

        if ($ssl['sem_dado']) {
            return Stat::make('Certificado SSL', 'Sem dado')
                ->description($ssl['motivo'])
                ->color('gray')
                ->icon('heroicon-o-lock-closed');
        }

        $dias = $ssl['dias_restantes'];

        return Stat::make('Certificado SSL', "{$dias} dia(s)")
            ->description('Vence em '.$ssl['valido_ate']->format('d/m/Y'))
            ->color(match (true) {
                $dias < 14 => 'danger',
                $dias < 30 => 'warning',
                default => 'success',
            })
            ->icon('heroicon-o-lock-closed');
    }

    private function statAppDebug(): Stat
    {
        $ligado = (bool) config('app.debug');
        $producao = config('app.env') === 'production';

        return Stat::make('APP_DEBUG', $ligado ? 'Ligado' : 'Desligado')
            ->description($producao ? 'Ambiente de produção' : 'Ambiente '.config('app.env'))
            ->color(match (true) {
                $ligado && $producao => 'danger',
                $ligado => 'warning',
                default => 'success',
            })
            ->icon('heroicon-o-bug-ant');
    }

    private function statPermissaoEnv(): Stat
    {
        $perm = StatusDeOperacao::permissaoEnv();

        if ($perm['sem_dado']) {
            return Stat::make('Permissão do .env', 'Sem dado')
                ->description('Arquivo .env não encontrado ou sem leitura')
                ->color('gray')
                ->icon('heroicon-o-document-text');
        }

        return Stat::make('Permissão do .env', $perm['octal'])
            ->description($perm['seguro'] ? 'Restrita ao dono' : 'Legível por grupo ou outros — aperte para 600')
            ->color($perm['seguro'] ? 'success' : 'danger')
            ->icon('heroicon-o-document-text');
    }

    private function statDoisFatores(): Stat
    {
        $sem2fa = StatusDeOperacao::usuariosSemDoisFatores();

        return Stat::make('Usuários sem 2FA', $sem2fa)
            ->description($sem2fa > 0 ? 'Ainda não configuraram o autenticador' : 'Todos com 2FA configurado')
            ->color($sem2fa > 0 ? 'danger' : 'success')
            ->icon('heroicon-o-shield-exclamation');
    }
}

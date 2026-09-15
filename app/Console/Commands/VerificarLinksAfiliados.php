<?php

namespace App\Console\Commands;

use App\Models\Cupom;
use App\Models\Marca;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Etapa 16, prioridade 1: link de afiliado quebrado é receita perdida e
 * invisível — ninguém nota um cupom apontando para uma página que não
 * responde mais. HEAD em `marcas.site_url` e `cupons.link_afiliado`, e o
 * resultado fica gravado nas colunas de link_* dos dois models, para o
 * painel ler sem refazer a requisição.
 *
 * Roda diário via cron do hPanel (mesmo padrão do backup — ver CLAUDE.md),
 * não via Schedule::command, porque este servidor não tem `php artisan
 * schedule:run` agendado e um cron direto é mais simples que criar essa
 * dependência para um comando só.
 */
class VerificarLinksAfiliados extends Command
{
    protected $signature = 'links:verificar';

    protected $description = 'HEAD em cada URL de marca e de cupom, para achar link de afiliado quebrado (etapa 16).';

    private const TIMEOUT_SEGUNDOS = 10;

    public function handle(): int
    {
        $marcas = Marca::query()->whereNotNull('site_url')->get();
        $cupons = Cupom::query()->whereNotNull('link_afiliado')->get();

        $quebrados = 0;

        foreach ($marcas as $marca) {
            $quebrados += $this->verificarUm($marca, 'site_url') ? 1 : 0;
        }

        foreach ($cupons as $cupom) {
            $quebrados += $this->verificarUm($cupom, 'link_afiliado') ? 1 : 0;
        }

        $this->info(sprintf(
            '%d marca(s) + %d cupom(ns) verificados. %d link(s) quebrado(s).',
            $marcas->count(),
            $cupons->count(),
            $quebrados,
        ));

        return self::SUCCESS;
    }

    /** @return bool true se o link saiu marcado como quebrado. */
    private function verificarUm(Marca|Cupom $registro, string $campoUrl): bool
    {
        $url = $registro->{$campoUrl};

        [$status, $falha] = $this->checar($url);

        $quebrado = $falha !== null || $status === null || $status >= 400;

        $registro->forceFill([
            'link_ultimo_status' => $status,
            'link_ultima_falha' => $falha,
            'link_quebrado' => $quebrado,
            'link_verificado_em' => now(),
        ])->save();

        if ($quebrado) {
            $this->warn("QUEBRADO [{$registro->getTable()}#{$registro->id}] {$url} — ".($falha ?? "HTTP {$status}"));
        }

        return $quebrado;
    }

    /** @return array{0: ?int, 1: ?string} [status HTTP, motivo da falha de conexão] */
    private function checar(string $url): array
    {
        try {
            $resposta = Http::timeout(self::TIMEOUT_SEGUNDOS)
                ->withUserAgent('MaquinaCertaVerificadorDeLinks/1.0 (+https://maquinacerta.com.br)')
                ->withHeaders(['Accept' => '*/*'])
                ->head($url);

            // Alguns servidores não implementam HEAD e devolvem 405 mesmo
            // com a URL no ar — um GET real desempata.
            if ($resposta->status() === 405) {
                $resposta = Http::timeout(self::TIMEOUT_SEGUNDOS)
                    ->withUserAgent('MaquinaCertaVerificadorDeLinks/1.0 (+https://maquinacerta.com.br)')
                    ->get($url);
            }

            return [$resposta->status(), null];
        } catch (ConnectionException $e) {
            return [null, 'sem conexão: '.str($e->getMessage())->limit(150)];
        } catch (Throwable $e) {
            return [null, str($e->getMessage())->limit(150)->toString()];
        }
    }
}

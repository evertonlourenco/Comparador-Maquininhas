<?php

namespace App\Support\Saude;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Etapa 16, prioridade 6: a API de Analytics do Cloudflare conta o que o
 * servidor viu — server-side, então não depende de consentimento de cookie
 * (diferente do GA4 da etapa 12, que só dispara depois do aceite no banner).
 * A etapa 12 configurou DNS, SSL e regras da Cloudflare, mas não gerou o
 * token da Analytics API — isso ficou para quando houvesse tráfego real.
 * Sem `CLOUDFLARE_API_TOKEN` e `CLOUDFLARE_ZONE_ID` no .env, todo método
 * aqui devolve `sem_dado: true`. Nunca inventa acesso.
 */
class ClienteAnalyticsCloudflare
{
    private const ENDPOINT = 'https://api.cloudflare.com/client/v4/graphql';

    private const DIAS = 30;

    /**
     * @return array{
     *     sem_dado: bool,
     *     motivo: ?string,
     *     dias: array<int, array{data: string, requisicoes: int, visitantes_unicos: int}>,
     * }
     */
    public static function acessosDiarios(): array
    {
        $token = config('saude.cloudflare.token');
        $zona = config('saude.cloudflare.zone_id');

        if (blank($token) || blank($zona)) {
            return [
                'sem_dado' => true,
                'motivo' => 'CLOUDFLARE_API_TOKEN e/ou CLOUDFLARE_ZONE_ID não configurados — a etapa 12 preparou o proxy e o SSL, mas não gerou o token da Analytics API.',
                'dias' => [],
            ];
        }

        return Cache::remember('saude:trafego-cloudflare', now()->addHour(), function () use ($token, $zona): array {
            $desde = Carbon::today()->subDays(self::DIAS - 1)->toDateString();
            $ate = Carbon::today()->toDateString();

            $consulta = <<<'GRAPHQL'
                query PainelDeSaude($zoneTag: String!, $desde: Date!, $ate: Date!) {
                    viewer {
                        zones(filter: { zoneTag: $zoneTag }) {
                            httpRequests1dGroups(
                                limit: 31
                                filter: { date_geq: $desde, date_leq: $ate }
                                orderBy: [date_ASC]
                            ) {
                                dimensions { date }
                                sum { requests }
                                uniq { uniques }
                            }
                        }
                    }
                }
                GRAPHQL;

            try {
                $resposta = Http::withToken($token)
                    ->timeout(10)
                    ->post(self::ENDPOINT, [
                        'query' => $consulta,
                        'variables' => ['zoneTag' => $zona, 'desde' => $desde, 'ate' => $ate],
                    ]);

                if ($resposta->failed()) {
                    return ['sem_dado' => true, 'motivo' => "Cloudflare respondeu HTTP {$resposta->status()}", 'dias' => []];
                }

                $corpo = $resposta->json();

                if (! empty($corpo['errors'])) {
                    $mensagem = $corpo['errors'][0]['message'] ?? 'erro desconhecido';

                    return ['sem_dado' => true, 'motivo' => "Cloudflare: {$mensagem}", 'dias' => []];
                }

                $grupos = $corpo['data']['viewer']['zones'][0]['httpRequests1dGroups'] ?? null;

                if ($grupos === null) {
                    return ['sem_dado' => true, 'motivo' => 'resposta da Cloudflare sem os dados esperados', 'dias' => []];
                }

                $dias = collect($grupos)->map(fn (array $g): array => [
                    'data' => $g['dimensions']['date'],
                    'requisicoes' => (int) ($g['sum']['requests'] ?? 0),
                    'visitantes_unicos' => (int) ($g['uniq']['uniques'] ?? 0),
                ])->all();

                return ['sem_dado' => false, 'motivo' => null, 'dias' => $dias];
            } catch (Throwable $e) {
                return ['sem_dado' => true, 'motivo' => str($e->getMessage())->limit(150)->toString(), 'dias' => []];
            }
        });
    }
}

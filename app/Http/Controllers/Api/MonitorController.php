<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoriaFonteMonitorada;
use App\Enums\StatusRevisao;
use App\Enums\TipoDeteccaoDeMudanca;
use App\Filament\Resources\Marcas\MarcaResource;
use App\Http\Controllers\Controller;
use App\Models\DeteccaoDeMudanca;
use App\Models\Marca;
use App\Support\Monitor\ResumoSemanal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Etapa 13: a unica porta de entrada do monitor de mudancas (repositorio
 * Node separado, cron no GitHub Actions e, para as fontes que bloquearem
 * IP de datacenter, cron no Mac do Everton). Protegida por
 * App\Http\Middleware\AutenticaMonitor — ver routes/api.php.
 *
 * O monitor decide sozinho, no proprio repositorio, se algo mudou (hash do
 * texto normalizado contra o estado commitado em estado/*.json) — este
 * controller so registra o que ele ja concluiu. Nao ha endpoint de leitura
 * de configuracao: a lista de fontes mora em fontes.json, versionada no
 * repositorio do monitor, nao neste banco.
 */
class MonitorController extends Controller
{
    public function registrarDeteccao(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'fonte_id' => ['required', 'string', 'max:100'],
            'marca_slug' => ['nullable', 'string', 'max:120'],
            'categoria' => ['required', Rule::enum(CategoriaFonteMonitorada::class)],
            'tipo' => ['required', Rule::enum(TipoDeteccaoDeMudanca::class)],
            'url' => ['required', 'string', 'max:500'],
            'resumo' => ['required_if:tipo,mudanca', 'nullable', 'string'],
            'trecho_alterado' => ['nullable', 'string'],
            'hash_anterior' => ['nullable', 'string', 'max:64'],
            'hash_novo' => ['nullable', 'string', 'max:64'],
            'mensagem_erro' => ['required_if:tipo,falha', 'nullable', 'string'],
        ]);

        $marca = isset($dados['marca_slug'])
            ? Marca::query()->where('slug', $dados['marca_slug'])->first()
            : null;

        $deteccao = DeteccaoDeMudanca::create([
            'marca_id' => $marca?->id,
            'fonte_id' => $dados['fonte_id'],
            'categoria' => $dados['categoria'],
            'tipo' => $dados['tipo'],
            'url' => $dados['url'],
            'resumo' => $dados['resumo'] ?? null,
            'trecho_alterado' => $dados['trecho_alterado'] ?? null,
            'hash_anterior' => $dados['hash_anterior'] ?? null,
            'hash_novo' => $dados['hash_novo'] ?? null,
            'mensagem_erro' => $dados['mensagem_erro'] ?? null,
            'status' => StatusRevisao::Pendente,
            'detectado_em' => Carbon::now(),
        ]);

        return response()->json([
            'id' => $deteccao->id,
            'link_admin' => $marca ? MarcaResource::getUrl('edit', ['record' => $marca]) : null,
        ], 201);
    }

    public function resumoSemanal(): JsonResponse
    {
        $resumo = ResumoSemanal::gerar();

        return response()->json([
            'taxas_sem_verificacao_30_dias' => $resumo['taxas_sem_verificacao_30_dias'],
            'cupons_vencendo_7_dias' => $resumo['cupons_vencendo_7_dias']->values(),
        ]);
    }
}

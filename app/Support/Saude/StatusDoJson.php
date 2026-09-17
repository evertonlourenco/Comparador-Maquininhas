<?php

namespace App\Support\Saude;

use App\Motor\CatalogoDoComparador;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Pedido do Everton em 17/09/2026, logo depois de o botão de gerar o JSON
 * existir: sinalizar, ao lado do próprio botão, se há mudança aprovada que
 * ainda não está no arquivo público.
 *
 * Comparação por CONTEÚDO, não por `updated_at` de tabela. Uma heurística de
 * "algo mudou depois de X" pegaria toda edição de taxa em rascunho — e o
 * Everton vai editar InfinitePay/SumUp/Mercado Pago em rascunho por dias,
 * sem que isso afete o arquivo publicado nem precise de aviso nenhum. Regenerar
 * o catálogo (mesma classe que o comando usa) e comparar com o que está no
 * arquivo, ignorando só o carimbo `gerado_em`, cobre com precisão os três
 * jeitos de o arquivo ficar desatualizado: aprovação nova, edição de taxa já
 * publicada, e despublicação (ou célula apagada) — sem falso positivo de
 * rascunho.
 */
final class StatusDoJson
{
    private const CHAVE_CACHE = 'comparador:json_desatualizado';

    public static function desatualizado(): bool
    {
        return Cache::remember(self::CHAVE_CACHE, now()->addMinute(), fn (): bool => self::calcular());
    }

    /** Chamado depois de gerar o arquivo, para o alerta sumir na hora — sem esperar o cache de 1 minuto expirar. */
    public static function esquecer(): void
    {
        Cache::forget(self::CHAVE_CACHE);
    }

    public static function geradoEm(): ?Carbon
    {
        $dados = self::lerArquivo();

        return isset($dados['gerado_em']) ? Carbon::parse($dados['gerado_em']) : null;
    }

    private static function calcular(): bool
    {
        $publicado = self::lerArquivo();

        if ($publicado === null) {
            return true;
        }

        $recalculado = app(CatalogoDoComparador::class)->montar(incluirRascunhos: false);

        // Loose (==): compara chave/valor recursivamente, sem exigir a mesma
        // ordem — as duas vêm do mesmo código, então a ordem já bate de
        // qualquer forma, mas usar == em vez de === evita um falso positivo
        // bobo se algum dia isso deixar de ser verdade.
        return self::semCarimbo($publicado) != self::semCarimbo($recalculado);
    }

    private static function lerArquivo(): ?array
    {
        $caminho = public_path('dados/comparador.json');

        if (! File::exists($caminho)) {
            return null;
        }

        $dados = json_decode(File::get($caminho), true);

        return is_array($dados) ? $dados : null;
    }

    private static function semCarimbo(array $dados): array
    {
        unset($dados['gerado_em']);

        return $dados;
    }
}

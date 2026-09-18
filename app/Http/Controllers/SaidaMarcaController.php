<?php

namespace App\Http\Controllers;

use App\Enums\PaginaOrigemCupom;
use App\Enums\TipoEventoCupom;
use App\Models\Cupom;
use App\Models\EventoCupom;
use App\Models\Marca;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Etapa 20 (bloco B): a rota de saída do CTA primário do cartão do
 * comparador — "Contratar com desconto" nunca aponta direto para o link de
 * afiliado, passa por aqui. O código do cupom vem por querystring porque o
 * motor (JavaScript, regra 9) já resolveu qual cupom vale para aquele item;
 * refazer essa conta em PHP duplicaria a lógica de `cupomVigente()` sem
 * ganho nenhum. O servidor só confere que o código ainda é vigente, grava o
 * clique em eventos_cupom (mesma tabela da etapa 09, `pagina_origem` =
 * comparador) e redireciona.
 *
 * Sem cupom (marca sem parceria, ou o código não bate mais — cupom editado
 * entre o carregamento da página e o clique) cai para o site oficial sem
 * gravar nada, no mesmo espírito do EventoCupomController: nunca estourar
 * erro para o lojista por causa de um cupom que já não existe.
 */
class SaidaMarcaController extends Controller
{
    public function __invoke(Request $request, string $marca): RedirectResponse
    {
        $marcaModel = Marca::query()->ativas()->where('slug', $marca)->first();

        abort_if($marcaModel === null, 404);

        $codigo = $request->query('cupom');

        $cupom = $codigo
            ? Cupom::query()->where('marca_id', $marcaModel->id)->where('codigo', $codigo)->vigentes()->first()
            : null;

        if ($cupom === null || ! $cupom->link_afiliado) {
            abort_if($marcaModel->site_url === null, 404);

            return redirect()->away($marcaModel->site_url);
        }

        EventoCupom::create([
            'marca_id' => $marcaModel->id,
            'cupom_id' => $cupom->id,
            'codigo' => $cupom->codigo,
            'tipo_evento' => TipoEventoCupom::UsarCupom,
            'pagina_origem' => PaginaOrigemCupom::Comparador,
        ]);

        return redirect()->away($cupom->link_afiliado);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\PaginaOrigemCupom;
use App\Enums\TipoEventoCupom;
use App\Models\Cupom;
use App\Models\EventoCupom;
use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Etapa 09: grava um evento por clique em "usar cupom" ou por cópia de
 * código, disparado pelo fetch de resources/js/app.js. Sem sessão, sem
 * autenticação — é telemetria pública, e uma marca ou código que não bate
 * mais (cupom editado entre o carregamento da página e o clique) não pode
 * estourar erro para o lojista: o evento simplesmente não é gravado.
 */
class EventoCupomController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $dados = $request->validate([
            'marca' => ['required', 'string', 'max:80'],
            'codigo' => ['required', 'string', 'max:60'],
            'tipo_evento' => ['required', Rule::enum(TipoEventoCupom::class)],
            'pagina_origem' => ['required', Rule::enum(PaginaOrigemCupom::class)],
        ]);

        $marca = Marca::query()->where('slug', $dados['marca'])->first();

        if ($marca === null) {
            return response()->noContent();
        }

        $cupom = Cupom::query()
            ->where('marca_id', $marca->id)
            ->where('codigo', $dados['codigo'])
            ->first();

        EventoCupom::create([
            'marca_id' => $marca->id,
            'cupom_id' => $cupom?->id,
            'codigo' => $dados['codigo'],
            'tipo_evento' => $dados['tipo_evento'],
            'pagina_origem' => $dados['pagina_origem'],
        ]);

        return response()->noContent();
    }
}

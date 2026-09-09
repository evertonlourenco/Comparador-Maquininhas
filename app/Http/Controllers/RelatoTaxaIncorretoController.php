<?php

namespace App\Http\Controllers;

use App\Enums\StatusRevisao;
use App\Models\Marca;
use App\Models\RelatoTaxaIncorreta;
use App\Support\Antispam\FormularioProtegido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Etapa 10: o botão "reportar taxa errada" reaproveitável de
 * <x-tabela-taxas>. Sem sessão, sem autenticação — telemetria pública com
 * uma mensagem de texto, no mesmo espírito de EventoCupomController: marca
 * que não bate mais não pode virar erro para o lojista.
 *
 * Responde JSON quando o app.js chama via fetch (progressivo) e redirect
 * quando o formulário submete sem JavaScript (padrão, sempre funcional).
 */
class RelatoTaxaIncorretoController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        if (FormularioProtegido::pareceAutomatizado($request)) {
            return $this->sucesso($request);
        }

        $marca = Marca::query()->where('slug', $request->input('marca'))->first();

        if ($marca === null) {
            return $this->sucesso($request);
        }

        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'min:5', 'max:2000'],
            'email_contato' => ['nullable', 'email', 'max:190'],
            'contexto' => ['nullable', 'string', 'max:160'],
        ]);

        RelatoTaxaIncorreta::create([
            'marca_id' => $marca->id,
            'contexto' => $dados['contexto'] ?? null,
            'pagina_url' => ($url = $request->input('pagina_url') ?: $request->headers->get('referer')) ? Str::limit($url, 500, '') : null,
            'mensagem' => $dados['mensagem'],
            'email_contato' => $dados['email_contato'] ?? null,
            'status' => StatusRevisao::Pendente,
        ]);

        return $this->sucesso($request);
    }

    private function sucesso(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('sucesso', 'Obrigado! Vamos conferir essa taxa.');
    }
}

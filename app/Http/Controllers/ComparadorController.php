<?php

namespace App\Http\Controllers;

use App\Support\Navegacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * A pagina do comparador (etapa 07).
 *
 * Regra 9: este controlador nao encosta no banco e nao le o JSON. O
 * comparador roda no navegador sobre /dados/comparador.json, servido como
 * arquivo estatico pelo proprio servidor web - o pico de visita de um video
 * nao passa por PHP nenhum.
 *
 * O unico toque no disco e um `stat` para saber se o arquivo existe e de
 * quando ele e. Existe porque a alternativa - a pagina descobrir isso so
 * depois do fetch - deixaria o rodape sem a data de atualizacao no primeiro
 * quadro, e deixaria a pagina sem nada a dizer quando o arquivo faltasse (o
 * estado normal de um clone novo, ja que /public/dados esta fora do Git).
 */
class ComparadorController extends Controller
{
    /** Caminho publico do JSON, relativo a raiz do site. */
    public const CAMINHO_DO_JSON = '/dados/comparador.json';

    public function __invoke(): View
    {
        $arquivo = public_path(ltrim(self::CAMINHO_DO_JSON, '/'));
        $existe = File::exists($arquivo);

        return view('comparador', [
            'caminhoDoJson' => self::CAMINHO_DO_JSON,
            'jsonExiste' => $existe,
            'atualizadoEm' => $existe
                ? Carbon::createFromTimestamp(File::lastModified($arquivo))
                : null,
            'navegacao' => Navegacao::principal('comparador'),
        ]);
    }
}

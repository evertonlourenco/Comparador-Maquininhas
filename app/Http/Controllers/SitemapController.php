<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use DOMDocument;
use Illuminate\Http\Response;

/**
 * sitemap.xml automático (etapa 08): home, listagem e a página de cada marca
 * ativa. Gerado via DOMDocument em vez de uma view Blade — evitando de
 * propósito o `<?xml ...?>` cru que uma view .blade.php interpretaria como
 * possível tag curta do PHP.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $documento = new DOMDocument('1.0', 'UTF-8');
        $urlset = $documento->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $documento->appendChild($urlset);

        $adicionar = function (string $loc, ?string $lastmod, string $prioridade) use ($documento, $urlset): void {
            $url = $documento->createElement('url');
            $url->appendChild($documento->createElement('loc', $loc));

            if ($lastmod) {
                $url->appendChild($documento->createElement('lastmod', $lastmod));
            }

            $url->appendChild($documento->createElement('changefreq', 'weekly'));
            $url->appendChild($documento->createElement('priority', $prioridade));

            $urlset->appendChild($url);
        };

        $adicionar(url('/'), null, '1.0');
        $adicionar(url('/maquininhas'), null, '0.9');

        Marca::query()->ativas()->orderBy('nome')->get(['slug', 'updated_at'])
            ->each(fn (Marca $marca) => $adicionar(
                url('/maquininha/'.$marca->slug),
                $marca->updated_at?->toAtomString(),
                '0.8',
            ));

        return response($documento->saveXML(), 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }
}

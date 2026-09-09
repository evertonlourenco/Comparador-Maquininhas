<?php

namespace App\Http\Controllers;

use App\Enums\StatusItem;
use App\Enums\StatusPublicacao;
use App\Models\Marca;
use App\Support\Marcas\EconomiaDoCupom;
use App\Support\Marcas\ResumoDeMarca;
use App\Support\Marcas\TabelaDeTaxasDaMarca;
use App\Support\Marcas\VantagensDaMarca;
use App\Support\Navegacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * A listagem de marcas (/maquininhas) e a página individual de cada uma
 * (/maquininha/{slug}), etapa 08.
 *
 * Ao contrário do comparador (etapa 07, regra 9), estas páginas são
 * renderizadas no servidor a partir do banco: não existe aqui o pico de
 * visita de vídeo que a home precisa absorver sem tocar o banco, e o
 * conteúdo (descrição, cupom em destaque, dados estruturados) precisa estar
 * no HTML na primeira resposta para valer alguma coisa para SEO.
 */
class MarcaController extends Controller
{
    public function index(): View
    {
        $marcas = Marca::query()
            ->ativas()
            ->with([
                'planos' => fn ($q) => $q->ativos()->permanentes(),
                'taxasDivulgadas' => fn ($q) => $q->where('status', StatusPublicacao::Publicado)->with('prazoRecebimento'),
                'faixasReportadas' => fn ($q) => $q->where('status', StatusPublicacao::Publicado)->with('prazoRecebimento'),
            ])
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        return view('maquininhas', [
            'marcas' => $marcas,
            'resumos' => $marcas->mapWithKeys(fn (Marca $m) => [$m->getKey() => ResumoDeMarca::paraCartao($m)]),
            'navegacao' => Navegacao::principal('marcas'),
            'canonical' => url('/maquininhas'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => $marcas->values()->map(fn (Marca $m, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => route('maquininhas.show', $m->slug),
                    'name' => $m->nome,
                ])->all(),
            ],
        ]);
    }

    public function show(string $marca): View
    {
        $marcaModel = Marca::query()
            ->ativas()
            ->where('slug', $marca)
            ->with([
                'adquirente',
                'cupons' => fn ($q) => $q->vigentes()->orderBy('ordem'),
                'planos' => fn ($q) => $q->ativos()->orderBy('ordem'),
                'planos.taxasDivulgadas' => fn ($q) => $q->where('status', StatusPublicacao::Publicado)
                    ->with(['grupoBandeira', 'prazoRecebimento']),
                'planos.faixasReportadas' => fn ($q) => $q->where('status', StatusPublicacao::Publicado)
                    ->with(['grupoBandeira', 'prazoRecebimento']),
                'planos.equipamentos' => fn ($q) => $q->wherePivot('status', StatusItem::Ativo->value)
                    ->where('equipamentos.status', StatusItem::Ativo)->orderBy('equipamentos.ordem'),
                'bandeiras' => fn ($q) => $q->orderBy('ordem'),
            ])
            ->firstOrFail();

        $cupomDestaque = $marcaModel->cupons->first();
        $canonical = url('/maquininha/'.$marcaModel->slug);

        return view('maquininha', [
            'marca' => $marcaModel,
            'tabelasDeTaxas' => TabelaDeTaxasDaMarca::montar($marcaModel),
            'vantagens' => VantagensDaMarca::listar($marcaModel),
            'cupomDestaque' => $cupomDestaque,
            'economia' => $cupomDestaque ? EconomiaDoCupom::calcular($cupomDestaque, $marcaModel) : null,
            'navegacao' => Navegacao::principal('marcas'),
            'canonical' => $canonical,
            'metaDescricao' => self::metaDescricao($marcaModel),
            'schema' => self::schema($marcaModel, $canonical),
        ]);
    }

    private static function metaDescricao(Marca $marca): string
    {
        if ($marca->descricao) {
            return Str::limit(strip_tags($marca->descricao), 155);
        }

        return "Taxas, cupom de desconto e modelos de maquininha da {$marca->nome}, com a fonte e a data em que cada número foi conferido.";
    }

    private static function schema(Marca $marca, string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => "Maquininha de cartão {$marca->nome}",
            'brand' => ['@type' => 'Brand', 'name' => $marca->nome],
            'url' => $url,
            'description' => self::metaDescricao($marca),
        ];

        if ($marca->logo_url) {
            $schema['image'] = $marca->logo_url;
        }

        // Regra 8: a nota do Reclame Aqui é campo manual, com data. Só entra
        // como Review quando o admin de fato preencheu — nunca inventada.
        if ($marca->reclame_aqui_nota !== null) {
            $schema['review'] = [
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (float) $marca->reclame_aqui_nota,
                    'bestRating' => 10,
                    'worstRating' => 0,
                ],
                'author' => ['@type' => 'Organization', 'name' => 'Reclame Aqui'],
                'datePublished' => $marca->reclame_aqui_consultado_em?->toDateString(),
            ];

            if ($marca->reclame_aqui_url) {
                $schema['review']['url'] = $marca->reclame_aqui_url;
            }
        }

        return $schema;
    }
}

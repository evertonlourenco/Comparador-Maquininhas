<?php

namespace App\Http\Controllers;

use App\Enums\StatusItem;
use App\Models\Cupom;
use App\Models\Marca;
use App\Support\Marcas\EconomiaDoCupom;
use App\Support\Navegacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * A área de cupons (etapa 09): a listagem consolidada (/cupons) e a página
 * de cada marca (/cupom/{slug}), otimizada para a busca "cupom NOME_DA_MARCA".
 *
 * Como a página de marca (etapa 08), renderiza do banco — não há pico de
 * vídeo nesta rota, e o cupom precisa estar no HTML da primeira resposta.
 * Regra 5 vale inteira aqui: nenhum campo de taxa, só o desconto na adesão.
 */
class CupomController extends Controller
{
    public function index(): View
    {
        $marcas = self::marcasComCupomVigente();

        $cartoes = $marcas
            ->flatMap(fn (Marca $marca) => $marca->cupons->map(fn (Cupom $cupom): array => [
                'marca' => $marca,
                'cupom' => $cupom,
                'economia' => EconomiaDoCupom::calcular($cupom, $marca),
            ]))
            // Regra: ordenar por maior desconto. A única unidade comparável
            // entre cupom percentual e cupom em valor é o tanto que ele
            // economiza em reais — a mesma conta que a página de marca já usa
            // no CTA (EconomiaDoCupom). Sem preço de adesão para calcular
            // sobre, o cupom não tem como se comparar aos outros: fica por
            // último, nunca no topo por acaso de ordenação de array.
            ->sortByDesc(fn (array $c) => $c['economia']['valor'] ?? -1)
            ->values();

        return view('cupons', [
            'cartoes' => $cartoes,
            'navegacao' => Navegacao::principal('cupons'),
            'canonical' => url('/cupons'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => $cartoes->values()->map(fn (array $c, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => url('/cupom/'.$c['marca']->slug),
                    'name' => "Cupom {$c['marca']->nome}",
                ])->all(),
            ],
        ]);
    }

    public function show(string $marca): View
    {
        $marcaModel = self::marcasComCupomVigente()
            ->firstWhere('slug', $marca);

        abort_if($marcaModel === null || $marcaModel->cupons->isEmpty(), 404);

        $cupom = $marcaModel->cupons->first();
        $economia = EconomiaDoCupom::calcular($cupom, $marcaModel);
        $canonical = url('/cupom/'.$marcaModel->slug);

        return view('cupom', [
            'marca' => $marcaModel,
            'cupom' => $cupom,
            'economia' => $economia,
            'navegacao' => Navegacao::principal('cupons'),
            'canonical' => $canonical,
            'metaDescricao' => self::metaDescricao($marcaModel, $cupom, $economia),
            'schema' => self::schema($marcaModel, $cupom, $economia, $canonical),
        ]);
    }

    /**
     * Só marca ativa com pelo menos um cupom vigente — as outras nem aparecem.
     *
     * @return Collection<int, Marca>
     */
    private static function marcasComCupomVigente(): Collection
    {
        return Marca::query()
            ->ativas()
            ->visiveisNoSite()
            ->with([
                'cupons' => fn ($q) => $q->vigentes()->orderBy('ordem'),
                'planos' => fn ($q) => $q->ativos(),
                'planos.equipamentos' => fn ($q) => $q->wherePivot('status', StatusItem::Ativo->value)
                    ->where('equipamentos.status', StatusItem::Ativo),
            ])
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get()
            ->filter(fn (Marca $m) => $m->cupons->isNotEmpty())
            ->values();
    }

    private static function metaDescricao(Marca $marca, Cupom $cupom, ?array $economia): string
    {
        // Etapa 17: a maioria dos cupons nao tem valido_ate - a frase de
        // validade so entra quando ha data de verdade pra citar.
        $validade = $cupom->valido_ate ? ', válido até '.$cupom->valido_ate->format('d/m/Y') : '';

        // Codigo generico de afiliados nao e divulgado: o desconto vale so pelo link.
        $rotulo = $cupom->codigo_generico ? 'Desconto' : "Cupom {$cupom->codigo}";

        if ($economia) {
            return "{$rotulo} da {$marca->nome}: economize {$economia['formatado']} na "
                ."adesão{$validade}. A taxa é a mesma do site oficial.";
        }

        return "{$rotulo} da {$marca->nome}{$validade}. A taxa é a mesma do site oficial "
            .'— o cupom só desconta a adesão.';
    }

    private static function schema(Marca $marca, Cupom $cupom, ?array $economia, string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Offer',
            'name' => $cupom->codigo_generico ? "Desconto {$marca->nome}" : "Cupom {$marca->nome}: {$cupom->codigo}",
            'description' => self::metaDescricao($marca, $cupom, $economia),
            'url' => $url,
            'seller' => ['@type' => 'Organization', 'name' => $marca->nome],
            'validFrom' => $cupom->valido_de->toDateString(),
            'availability' => 'https://schema.org/InStock',
        ];

        // Etapa 17: sem validade divulgada (o comum), nao ha validThrough pra
        // declarar - regra 6 vale para dado estruturado tambem.
        if ($cupom->valido_ate) {
            $schema['validThrough'] = $cupom->valido_ate->toDateString();
        }

        // Regra 6 vale para dado estruturado também: preço só entra quando dá
        // para calcular sobre um valor de adesão real e verificado.
        if ($economia && $economia['valor'] > 0.0) {
            $schema['priceCurrency'] = 'BRL';
            $schema['price'] = number_format($economia['valor'], 2, '.', '');
            $schema['priceSpecification'] = [
                '@type' => 'UnitPriceSpecification',
                'priceType' => 'https://schema.org/DiscountPrice',
                'price' => number_format($economia['valor'], 2, '.', ''),
                'priceCurrency' => 'BRL',
            ];
        }

        return $schema;
    }
}

<?php

namespace App\Support\Marcas;

use App\Enums\TipoDesconto;
use App\Models\Cupom;
use App\Models\Marca;
use App\Support\Dinheiro;

/**
 * "A economia em reais" do CTA final da página de marca (etapa 08, seção 8).
 *
 * Regra 5: o cupom desconta a adesão (ou o aparelho), nunca a taxa. Um
 * desconto em valor já é um número em reais por si só. Um desconto percentual
 * só vira reais quando colado a um preço de adesão real e verificado — nunca
 * uma "adesão típica" inventada. Sem esse preço, o motivo some e a tela cai
 * para o percentual puro (nunca zero, nunca estimativa).
 *
 * Espera a marca com 'planos.equipamentos' (pivot ativo, com preço) carregado.
 */
final class EconomiaDoCupom
{
    /** @return array{valor: float, formatado: string, base: ?string}|null */
    public static function calcular(Cupom $cupom, Marca $marca): ?array
    {
        // Etapa 17: PagBank e Mercado Pago tem desconto real mas sem valor
        // conhecido (varia por equipamento/mes) - sem isso, (float) null vira
        // 0.0 e a tela mentiria "economize R$ 0,00" em vez de cair pro texto.
        if ($cupom->valor === null) {
            return null;
        }

        // Etapa 17, achado com a TrincaPay: quando o único preço publicado já
        // vem com o cupom aplicado (a própria marca avisa isso), o preço de
        // adesão cadastrado já É o preço com desconto - calcular o percentual
        // do cupom em cima dele soma o mesmo desconto duas vezes. Diferente
        // do caso acima (sem valor conhecido): aqui o percentual é real e
        // fixo, só não pode virar uma segunda economia em reais.
        if ($cupom->desconto_ja_no_preco) {
            return null;
        }

        if ($cupom->tipo_desconto === TipoDesconto::Valor) {
            $valor = (float) $cupom->valor;

            return [
                'valor' => $valor,
                'formatado' => Dinheiro::real($valor),
                'base' => null,
            ];
        }

        $par = self::equipamentoMaisBarato($cupom, $marca);

        if ($par === null) {
            return null;
        }

        [$equipamento, $plano] = $par;

        // Pedido do Everton (19/09/2026): quando a pagina do link cobra preco
        // proprio, a economia e a diferenca para o preco do site.
        if ($equipamento->pivot->preco_adesao_no_link !== null) {
            $valor = max(0.0, Dinheiro::arredondar(
                (float) $equipamento->pivot->preco_adesao_vigente - (float) $equipamento->pivot->preco_adesao_no_link,
            ));

            return [
                'valor' => $valor,
                'formatado' => Dinheiro::real($valor),
                'base' => "sobre a adesão de {$equipamento->nome} no plano {$plano->nome}",
            ];
        }

        $precoBase = (float) $equipamento->pivot->preco_adesao_vigente;
        $valor = Dinheiro::arredondar($precoBase * (float) $cupom->valor / 100);

        return [
            'valor' => $valor,
            'formatado' => Dinheiro::real($valor),
            'base' => "sobre a adesão de {$equipamento->nome} no plano {$plano->nome}",
        ];
    }

    /**
     * Cupom preso a um equipamento (regra 5) só pode economizar sobre aquele
     * aparelho. Sem essa amarra, usa o mais barato entre os que a marca vende
     * hoje — a economia mínima que o cupom garante, nunca uma máxima chutada.
     */
    private static function equipamentoMaisBarato(Cupom $cupom, Marca $marca): ?array
    {
        $pares = $marca->planos->flatMap(
            fn ($plano) => $plano->equipamentos->map(fn ($equipamento) => [$equipamento, $plano])
        );

        if ($cupom->equipamento_id !== null) {
            $pares = $pares->filter(fn (array $par) => $par[0]->getKey() === $cupom->equipamento_id);
        }

        return $pares
            ->filter(fn (array $par) => $par[0]->pivot->preco_adesao_vigente !== null)
            ->sortBy(fn (array $par) => (float) $par[0]->pivot->preco_adesao_vigente)
            ->first();
    }
}

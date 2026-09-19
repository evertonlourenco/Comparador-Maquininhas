<?php

namespace Database\Seeders;

use App\Enums\IncideSobre;
use App\Enums\StatusItem;
use App\Enums\TipoDesconto;
use App\Models\Cupom;
use App\Models\Marca;
use Illuminate\Database\Seeder;

/**
 * Regra 5: cupons dos links de afiliado do Everton, passados em 15/09/2026
 * (etapa 17). O desconto do link é sempre além do que a página oficial da
 * marca já mostra - nunca desconto na taxa (a taxa do afiliado é igual à do
 * site oficial, regra 5). Todos incidem sobre a adesão de qualquer
 * maquininha da marca (dito pelo Everton: "assim como de todas as demais"),
 * nunca preso a um equipamento - por isso `equipamento_id` fica sempre nulo.
 *
 * `valido_ate` fica nulo em todos: o Everton confirmou que cupom de afiliado
 * via de regra não expira - o que muda de vez em quando é o link, o código
 * ou o percentual, não uma data publicada. Isso deixou de ser um campo
 * obrigatório na etapa 17 (ver migration
 * alter_cupons_permite_prazo_e_desconto_indefinidos) exatamente por causa
 * desta correção.
 *
 * PagBank e Mercado Pago têm desconto real mas sem valor conhecido - o
 * Everton confirmou que existe desconto adicional pelo link, variável por
 * equipamento e que muda mês a mês, mas não sabe o percentual. `valor` e
 * `tipo_desconto` ficam nulos (também liberado na etapa 17) e a descrição
 * carrega o texto em vez de um número - regra 6 não admite valor chutado.
 */
class CuponsAfiliadoSeeder extends Seeder
{
    public function run(): void
    {
        $cupons = [
            [
                'marca' => 'ton',
                'codigo' => 'EVERTONLOURENCOBF20',
                'tipo_desconto' => TipoDesconto::Percentual,
                'valor' => 20,
                'descricao' => null,
                // URL real, já usada pelo monitor de mudanças (monitor/fontes.json,
                // id ton-equipamento-cupom, fonte etapa 13).
                'link' => 'https://www.ton.com.br/catalogo?coupon=EVERTONLOURENCOBF20&userAnticipation=0&utm_medium=invite_share&utm_source=revendedor',
                'termos' => null,
            ],
            [
                'marca' => 'pagbank',
                'codigo' => 'vzArXydV',
                'tipo_desconto' => null,
                'valor' => null,
                'descricao' => 'Desconto adicional variável na adesão pelo link',
                'link' => 'https://loja.pagbank.com.br/?cm=vzArXydV&utm_source=mgm&utm_medium=midia-interna&utm_campaign=indicacao-maquina',
                'termos' => 'Desconto real, mas sem percentual fixo divulgado: varia por equipamento '
                    .'e pode mudar de um mês para outro. Confirmado pelo Everton em 15/09/2026.',
            ],
            [
                'marca' => 'mercado-pago',
                'codigo' => 'ZQXZWS3OLW',
                'tipo_desconto' => null,
                'valor' => null,
                'descricao' => 'Desconto adicional variável na adesão pelo link',
                'link' => 'https://www.mercadopago.com.br/ferramentas-para-vender/maquininhas-point?code=ZQXZWS3OLW&utm_source=share_mgm_web&utm_medium=APP&matt_tool=53347321&matt_word=mgm_point_all',
                'termos' => 'Desconto real, mas sem percentual fixo divulgado: varia por equipamento '
                    .'e pode mudar de um mês para outro. Confirmado pelo Everton em 15/09/2026.',
            ],
            [
                'marca' => 'yelly',
                'codigo' => 'AFILIADOS10',
                'tipo_desconto' => TipoDesconto::Percentual,
                'valor' => 10,
                'descricao' => null,
                'link' => 'https://checkout.yelly.com.br/monetizando/?cupom=AFILIADOS10',
                'termos' => null,
                // Everton, 19/09/2026: AFILIADOS10 e um cupom generico de todos
                // os afiliados - digitado no site oficial nao credita a
                // comissao. O desconto vale so pelo link; o codigo nao aparece.
                'codigo_generico' => true,
            ],
            [
                'marca' => 'sidepay',
                'codigo' => 'MONETIZANDO',
                'tipo_desconto' => TipoDesconto::Percentual,
                'valor' => 10,
                'descricao' => null,
                'link' => 'https://checkout.sidepay.com.br/?afiliado=MONETIZANDO',
                'termos' => null,
            ],
            [
                'marca' => 'facilitypay',
                'codigo' => 'EVERTON10',
                'tipo_desconto' => TipoDesconto::Percentual,
                'valor' => 10,
                'descricao' => null,
                'link' => 'https://app.facilitypay.com.br/indicacao/EVERTON10',
                'termos' => null,
            ],
            [
                'marca' => 'trincapay',
                // O Everton escreveu "CANAL-MONETIZANDO" (com hífen), mas a
                // própria página, ao abrir o link, mostra e aplica
                // "CANALMONETIZANDO" (sem hífen) - usado o que foi
                // verificado na página real. Vale confirmar com o Everton.
                'codigo' => 'CANALMONETIZANDO',
                'tipo_desconto' => TipoDesconto::Percentual,
                'valor' => 16,
                'descricao' => null,
                'link' => 'https://www.trincapay.com.br/canal-monetizando/',
                'termos' => null,
                // Etapa 17, confirmado com o Everton: o único preço de adesão
                // publicado (R$ 298,80) já é o preço COM esse cupom aplicado -
                // não existe um "preço sem cupom" em lugar nenhum verificável.
                // Sem isso, EconomiaDoCupom somaria o mesmo desconto de novo.
                'desconto_ja_no_preco' => true,
            ],
        ];

        foreach ($cupons as $dados) {
            $marca = Marca::where('slug', $dados['marca'])->sole();
            $chave = ['marca_id' => $marca->getKey(), 'codigo' => $dados['codigo']];
            $valores = [
                'equipamento_id' => null,
                'descricao' => $dados['descricao'],
                'tipo_desconto' => $dados['tipo_desconto'],
                'incide_sobre' => IncideSobre::Adesao,
                'valor' => $dados['valor'],
                'desconto_ja_no_preco' => $dados['desconto_ja_no_preco'] ?? false,
                'codigo_generico' => $dados['codigo_generico'] ?? false,
                'valido_de' => now()->toDateString(),
                'valido_ate' => null,
                'link_afiliado' => $dados['link'],
                'termos' => $dados['termos'],
                'status' => StatusItem::Ativo,
                'ordem' => 0,
            ];

            // Achado em produção (etapa 17): reseed não pode reativar um
            // cupom que o admin desativou no painel (ex.: link quebrou) -
            // status só vale na criação.
            if (Cupom::where($chave)->exists()) {
                unset($valores['status']);
            }

            Cupom::updateOrCreate($chave, $valores);
        }
    }
}

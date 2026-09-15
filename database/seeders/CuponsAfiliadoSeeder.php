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
 * site oficial, regra 5).
 *
 * Nem toda marca com link de afiliado entra aqui: PagBank (vzArXydV) não tem
 * desconto adicional nenhum - só rastreamento -, e Mercado Pago (ZQXZWS3OLW)
 * tem desconto real mas o Everton não sabe o percentual. Regra 6 vale para
 * cupom também: sem valor confirmado, sem linha.
 *
 * `valido_ate` é NOT NULL (regra 5), mas nenhuma marca declarou validade -
 * são links de afiliado "correntes", sem data de expiração publicada. Usado
 * como checkpoint de revisão, não como validade real: 90 dias a partir da
 * carga, para o cupom aparecer no alerta de "vencendo em 7 dias" do painel
 * inicial e alguém confirmar que o link/percentual ainda vale.
 */
class CuponsAfiliadoSeeder extends Seeder
{
    private const REVISAR_EM = '2026-12-15';

    public function run(): void
    {
        $cupons = [
            [
                'marca' => 'ton',
                'codigo' => 'EVERTONLOURENCOBF20',
                'tipo_desconto' => TipoDesconto::Percentual,
                // Suposição a confirmar com o Everton: o desconto do Ton incide
                // sobre a adesão (não há equipamento específico amarrado ao
                // link). Se for sobre um aparelho em especial, mover para
                // incide_sobre = Equipamento e vincular equipamento_id.
                'incide_sobre' => IncideSobre::Adesao,
                'valor' => 20,
                // URL real, ja usada pelo monitor de mudancas (monitor/fontes.json,
                // id ton-equipamento-cupom, fonte etapa 13).
                'link' => 'https://www.ton.com.br/catalogo?coupon=EVERTONLOURENCOBF20&userAnticipation=0&utm_medium=invite_share&utm_source=revendedor',
            ],
        ];

        foreach ($cupons as $dados) {
            $marca = Marca::where('slug', $dados['marca'])->sole();

            Cupom::updateOrCreate(
                ['marca_id' => $marca->getKey(), 'codigo' => $dados['codigo']],
                [
                    'equipamento_id' => null,
                    'tipo_desconto' => $dados['tipo_desconto'],
                    'incide_sobre' => $dados['incide_sobre'],
                    'valor' => $dados['valor'],
                    'valido_de' => now()->toDateString(),
                    'valido_ate' => self::REVISAR_EM,
                    'link_afiliado' => $dados['link'],
                    'termos' => 'Sem validade declarada pela marca - data acima é checkpoint de '
                        .'revisão (90 dias), não expiração real.',
                    'status' => StatusItem::Ativo,
                    'ordem' => 0,
                ],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoOperacao;
use App\Models\PrazoRecebimento;

/**
 * Mercado Pago, etapa 17. So a oferta promocional de entrada: a tabela
 * permanente fica atras de login (ver MarcasSeeder). Lida em 15/09/2026 na
 * pagina publica de afiliado do Everton, que declara abertamente taxa, prazo
 * e validade - por isso pode entrar como plano promocional (regra revisitada
 * desde a etapa 05: o impedimento era nao ter como declarar validade, e aqui
 * ha validade declarada).
 *
 * So dois pontos da tabela de parcelado foram publicados na pagina (1x e
 * 12x) - "vendas em ate 18x" e mencionado, mas as parcelas 2x a 11x e 13x a
 * 18x nao apareceram. Regra 6: nao inventar o meio da serie. Elas ficam
 * ausentes ate alguem conferir a tabela completa (o simulador dentro do
 * checkout provavelmente a mostra).
 */
class MercadoPagoSeeder extends SeederDeMarca
{
    private const URL = 'https://www.mercadopago.com.br/ferramentas-para-vender/maquininhas-point';

    public function run(): void
    {
        $marca = $this->marca('mercado-pago');

        $plano = $this->plano($marca, 'Oferta de entrada', [
            'tipo_enquadramento' => TipoEnquadramento::Promocional,
            'faturamento_min' => null,
            'faturamento_max' => null,
            'promocional_dias' => 30,
            'promocional_valor_processado' => 5000,
            'promocional_sucessor_id' => null,
            'compromisso' => 'Tabela promocional de entrada. Vale por 30 dias ou até R$ 5.000,00 '
                .'processados na maquininha, o que vier antes. Depois dela o lojista cai na tabela '
                .'permanente por faturamento, que não tem página pública - só aparece no simulador '
                .'dentro da conta, atrás de login.',
            'status' => StatusItem::Ativo,
            'ordem' => 0,
        ]);

        $fonte = $this->fonte(
            self::URL,
            'Página pública de oferta de entrada (link de afiliado). "Mesma taxa para todas as '
            .'bandeiras e vendas em até 18x", mas só 1x e 12x apareceram com número - 2x a 11x e '
            .'13x a 18x ficam sem linha até alguém conferir a tabela completa.',
            dataVerificacao: '2026-09-15',
        );

        $this->debito($plano, 'geral', PrazoRecebimento::NA_HORA, 0.74, $fonte);
        $this->taxa($plano, TipoOperacao::CreditoAvista, 'geral', PrazoRecebimento::NA_HORA, 1, 0.74, $fonte);
        $this->taxa($plano, TipoOperacao::CreditoParcelado, 'geral', PrazoRecebimento::NA_HORA, 12, 8.99, $fonte);
    }
}

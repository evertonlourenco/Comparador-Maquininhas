<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 17, achado em 17/09/2026 com o cupom da TrincaPay: o único preço de
 * adesão que a marca publica (`trincapay.com.br/canal-monetizando/`) já vem
 * com o cupom de afiliado aplicado automaticamente - a própria página diz
 * isso. `EconomiaDoCupom` calcula "economize R$X" como um percentual do
 * `preco_adesao_vigente`, mas se esse preço já é o preço COM cupom, a conta
 * soma o mesmo desconto duas vezes (confirmado: R$ 47,81 calculado, quando o
 * desconto real do cupom sobre a adesão já está todo dentro dos R$ 298,80
 * gravados).
 *
 * Diferente do caso do PagBank/Mercado Pago (`valor` nulo, "desconto real
 * mas variável, sem percentual fixo"): aqui o percentual é fixo e conhecido
 * (16%), só não pode ser somado de novo. Zerar `valor` também apagaria o
 * selo "16% off" do cupom (que `x-bloco-cupom` lê direto de `cupom->valor`),
 * que continua sendo uma informação real e válida sobre o cupom - só a conta
 * em reais que não pode repetir o desconto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            $table->boolean('desconto_ja_no_preco')->default(false)->after('valor');
        });
    }

    public function down(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            $table->dropColumn('desconto_ja_no_preco');
        });
    }
};

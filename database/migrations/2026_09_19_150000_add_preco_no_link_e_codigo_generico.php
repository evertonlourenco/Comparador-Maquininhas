<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido do Everton, 19/09/2026: a adesão que o portal mostra é SEMPRE a que
 * o cliente paga entrando pelo link dele.
 *
 * - `equipamento_plano.preco_adesao_no_link`: o preço final que a marca cobra
 *   na página do link de afiliado, quando ele não é "preço do site menos o
 *   percentual do cupom" (FacilityPay: a página do link tem preço próprio,
 *   R$ 55,50 no Mini, contra R$ 64,90/104,90 no site). Nulo = vale a conta
 *   pelo desconto do cupom, como sempre.
 * - `cupons.codigo_generico`: cupom que qualquer afiliado usa (AFILIADOS10 da
 *   Yelly). Digitar o código no site oficial não credita a comissão, então o
 *   portal não o exibe nem manda copiar: o desconto vale só pelo link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipamento_plano', function (Blueprint $table) {
            $table->decimal('preco_adesao_no_link', 10, 2)->nullable()->after('preco_adesao_promocional');
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->boolean('codigo_generico')->default(false)->after('desconto_ja_no_preco');
        });
    }

    public function down(): void
    {
        Schema::table('equipamento_plano', function (Blueprint $table) {
            $table->dropColumn('preco_adesao_no_link');
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->dropColumn('codigo_generico');
        });
    }
};

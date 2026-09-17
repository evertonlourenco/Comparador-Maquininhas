<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trava da marca (pedido do Everton, 17/09/2026, depois de um plano
 * incompleto — mensalidade nula — ter passado despercebido na revisão da
 * etapa 17).
 *
 * `aprovada_em` nulo é a marca inteira fora do JSON público, mesmo que ela
 * tenha taxa publicada, sem exceção — ver `CatalogoDoComparador::montar()` e
 * `App\Support\Saude\CompletudeDaMarca`. Timestamp, não boolean: registra
 * quando alguém aprovou, no mesmo espírito de `data_verificacao` nas taxas —
 * e se algum dado da marca mudar depois e ela deixar de estar completa, a
 * aprovação por si só não basta mais (o motor é conferido de novo a cada
 * `comparador:gerar-json`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->timestamp('aprovada_em')->nullable()->after('publica_tabela');
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn('aprovada_em');
        });
    }
};

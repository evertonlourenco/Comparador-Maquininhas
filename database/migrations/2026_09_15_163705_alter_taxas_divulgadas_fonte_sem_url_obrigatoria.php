<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 17, pedido do Everton: nem toda taxa vem de uma página pública -
 * "e se eu receber a tabela direto da marca?" (PDF por e-mail, WhatsApp do
 * gerente de contas, planilha). `url_fonte` era NOT NULL desde a etapa 02,
 * o que forçava inventar um link nesses casos - o oposto do que a regra 6
 * pede.
 *
 * `faixas_reportadas` já resolvia isso desde a etapa 10, com o mesmo padrão
 * agora copiado aqui: `fonte_descricao` (texto livre - "PDF enviado por
 * e-mail pelo gerente de contas em 15/09/2026") ao lado de `url_fonte`
 * opcional. A regra 8 continua valendo - só deixou de significar
 * especificamente "uma URL": o formulário exige um dos dois preenchidos,
 * nunca nenhum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxas_divulgadas', function (Blueprint $table) {
            $table->string('url_fonte', 500)->nullable()->change();
            $table->string('fonte_descricao', 255)->nullable()->after('url_fonte');
        });
    }

    public function down(): void
    {
        Schema::table('taxas_divulgadas', function (Blueprint $table) {
            $table->dropColumn('fonte_descricao');
            $table->string('url_fonte', 500)->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 17. `adquirente_id` era NOT NULL desde a etapa 02 - toda marca
 * precisava de um adquirente cadastrado antes de existir. Bloqueou o
 * cadastro da TrincaPay, cujo adquirente o Everton nao sabe (marca nova, o
 * cadastro dela nem abriu ainda). A regra 7 do domínio ("marcas têm
 * adquirente_subjacente") continua valendo quando a informação existe - ela
 * nunca foi sobre deduplicar, só sobre não confundir uma marca com a outra
 * quando compartilham infraestrutura. Vira NULL = "não verificado ainda",
 * igual a qualquer outro campo do domínio (regra 6 no mesmo espírito: campo
 * vazio é honesto, adquirente chutado não é).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->foreignId('adquirente_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->foreignId('adquirente_id')->nullable(false)->change();
        });
    }
};

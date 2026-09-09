<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 10: quem aceita relato de proposta em /enviar-proposta. Coluna, nao
 * lista travada no codigo — mesmo espirito de grupos_bandeiras e
 * prazos_recebimento (dimensao e linha, editavel no painel, nao enum).
 *
 * Hoje so as quatro marcas que nao publicam tabela (regra 4) fazem sentido
 * aqui: sao as que dependem de faixa_reportada para ter numero nenhum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->boolean('aceita_relatos')->default(false)->after('publica_tabela');
        });

        DB::table('marcas')
            ->whereIn('slug', ['cielo', 'rede', 'getnet', 'stone'])
            ->update(['aceita_relatos' => true]);
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn('aceita_relatos');
        });
    }
};

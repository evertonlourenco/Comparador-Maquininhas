<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table): void {
            // com_nota | sem_nota (perfil existe, poucas avaliacoes) | sem_perfil.
            $table->string('reclame_aqui_situacao', 20)->nullable()->after('reclame_aqui_nota');
        });

        DB::table('marcas')->whereNotNull('reclame_aqui_nota')->update(['reclame_aqui_situacao' => 'com_nota']);

        // Dado unico, informado pelo Everton em 19/09/2026: FacilityPay tem perfil
        // mas ainda sem nota; TrincaPay nao tem perfil. Sem isto as duas saem do
        // JSON no deploy que liga a trava (nota obrigatoria) ate alguem abrir o painel.
        DB::table('marcas')->where('slug', 'facilitypay')->whereNull('reclame_aqui_nota')
            ->update(['reclame_aqui_situacao' => 'sem_nota']);
        DB::table('marcas')->where('slug', 'trincapay')->whereNull('reclame_aqui_nota')
            ->update(['reclame_aqui_situacao' => 'sem_perfil']);
        DB::table('marcas')->whereIn('slug', ['facilitypay', 'trincapay'])->whereNull('reclame_aqui_consultado_em')
            ->update(['reclame_aqui_consultado_em' => '2026-09-19']);
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table): void {
            $table->dropColumn('reclame_aqui_situacao');
        });
    }
};

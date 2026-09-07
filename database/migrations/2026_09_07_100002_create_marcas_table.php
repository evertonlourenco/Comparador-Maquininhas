<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adquirente_id')->constrained('adquirentes')->restrictOnDelete();
            $table->string('nome', 120);
            $table->string('slug', 120)->unique();
            $table->string('site_url', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->text('descricao')->nullable();

            // Regra 8: nota do Reclame Aqui e campo manual, com data e link. Nunca raspar.
            $table->decimal('reclame_aqui_nota', 3, 1)->nullable();
            $table->string('reclame_aqui_url', 500)->nullable();
            $table->date('reclame_aqui_consultado_em')->nullable();

            // Regra 4: marca que nao publica tabela (Cielo, Rede, GetNet, Stone)
            // so admite faixa_reportada, nunca taxa_divulgada.
            $table->boolean('publica_tabela')->default(true);

            $table->string('status', 20)->default('ativa');
            $table->smallInteger('ordem')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'ordem']);
            $table->index('adquirente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas');
    }
};

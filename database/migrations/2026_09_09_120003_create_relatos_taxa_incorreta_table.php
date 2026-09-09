<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 10: o botao "reportar taxa errada" reaproveitavel em toda tabela de
 * taxas (ver x-tabela-taxas). Staging operacional, no mesmo espirito de
 * eventos_cupom — nao e entidade de dominio, so fila de revisao humana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatos_taxa_incorreta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();

            // Qual tabela/plano, e de onde veio — contexto para quem revisa,
            // nunca fonte de verdade (regra 6 nao se aplica a isto).
            $table->string('contexto', 160)->nullable();
            $table->string('pagina_url', 500)->nullable();

            $table->text('mensagem');
            $table->string('email_contato', 190)->nullable();

            $table->string('status', 20)->default('pendente');
            $table->text('observacao_admin')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatos_taxa_incorreta');
    }
};

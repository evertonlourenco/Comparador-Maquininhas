<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custos 2 e 3 da regra 6. O preco de adesao varia por plano para o mesmo
 * equipamento, entao ele pertence ao par - nao ao aparelho.
 * A existencia da linha ja responde "esse aparelho e vendido nesse plano?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamento_plano', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipamento_id')->constrained('equipamentos')->cascadeOnDelete();
            $table->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $table->decimal('preco_adesao', 10, 2)->nullable();
            $table->decimal('preco_adesao_promocional', 10, 2)->nullable();
            $table->decimal('aluguel_mensal', 10, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->string('status', 20)->default('ativo');
            $table->timestamps();

            $table->unique(['equipamento_id', 'plano_id']);
            $table->index(['plano_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamento_plano');
    }
};

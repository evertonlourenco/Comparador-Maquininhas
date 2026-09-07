<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regra 3: plano e entidade propria.
 * Guarda os custos que sao da conta, nao do aparelho. Adesao e aluguel ficam
 * no pivot equipamento_plano, porque variam por plano para o mesmo aparelho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->string('slug', 120);
            $table->string('tipo_enquadramento', 20);

            // So fazem sentido em tipo_enquadramento = automatico.
            $table->decimal('faturamento_min', 12, 2)->nullable();
            $table->decimal('faturamento_max', 12, 2)->nullable();

            // O compromisso de volume que o lojista assume em tipo = escolhido.
            $table->text('compromisso')->nullable();

            // Custos recorrentes da conta (regra 6).
            $table->decimal('mensalidade', 10, 2)->nullable();
            $table->decimal('tarifa_saque', 10, 2)->nullable();
            $table->decimal('tarifa_ted', 10, 2)->nullable();
            $table->decimal('tarifa_pix_recebimento', 10, 2)->nullable();
            $table->decimal('tarifa_pix_envio', 10, 2)->nullable();

            // Antecipacao avulsa, em % ao mes. A antecipacao automatica nao mora
            // aqui: ela ja esta embutida no percentual da taxa cujo prazo e
            // "na hora" ou "d_1" (regra 1).
            $table->decimal('taxa_antecipacao_mensal', 6, 4)->nullable();

            $table->text('condicao_isencao')->nullable();
            $table->string('status', 20)->default('ativo');
            $table->smallInteger('ordem')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['marca_id', 'slug']);
            $table->index(['marca_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};

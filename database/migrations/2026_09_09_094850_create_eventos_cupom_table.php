<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 09: um evento por clique em "usar cupom" ou por cópia de código.
 * `codigo` é uma foto do código no momento do clique — o cupom pode vencer ou
 * ser editado depois, e a reconciliação com o relatório do parceiro no fim do
 * mês precisa do código que o lojista de fato usou, não do estado atual da
 * linha em `cupons`. Por isso `cupom_id` é nulo-ao-apagar: perder o cupom não
 * pode apagar o histórico do clique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_cupom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->foreignId('cupom_id')->nullable()->constrained('cupons')->nullOnDelete();

            $table->string('codigo', 60);
            $table->string('tipo_evento', 20);
            $table->string('pagina_origem', 20);

            $table->timestamps();

            $table->index(['marca_id', 'created_at']);
            $table->index(['tipo_evento', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_cupom');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda apenas o que e do aparelho. Preco nao mora aqui: a mesma maquininha
 * custa diferente em cada plano da marca (ver equipamento_plano).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->string('slug', 120);
            $table->string('tipo', 20);
            $table->text('descricao')->nullable();
            $table->string('imagem_path', 255)->nullable();
            $table->boolean('tem_chip_gratis')->default(false);
            $table->boolean('imprime_comprovante')->default(false);
            $table->boolean('aceita_nfc')->default(true);
            $table->boolean('exige_celular')->default(false);
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
        Schema::dropIfExists('equipamentos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regra 1: o prazo e dimensao da taxa, nao atributo da marca.
 * E tabela e nao coluna inteira porque um dos valores nao e um numero:
 * "parcela a parcela" (cada parcela cai no mes dela) tem dias = null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prazos_recebimento', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nome_exibicao', 60);
            $table->string('descricao', 255)->nullable();
            $table->smallInteger('dias')->nullable();
            $table->smallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prazos_recebimento');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensao da taxa. As marcas publicam por grupo, nao por bandeira individual:
 * uma tabela para Visa/Mastercard, outra para as demais, outra para voucher.
 * E tabela e nao enum justamente para admitir um grupo novo sem migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_bandeiras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nome_exibicao', 60);
            $table->string('descricao', 255)->nullable();
            $table->smallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_bandeiras');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bandeiras aceitas por marca. O grupo mora aqui, e nao em bandeiras, porque
 * cada marca decide onde Elo e Amex caem: numa marca Elo esta com Visa/Master,
 * em outra esta nas demais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandeira_marca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->foreignId('bandeira_id')->constrained('bandeiras')->cascadeOnDelete();
            $table->foreignId('grupo_bandeira_id')->nullable()
                ->constrained('grupos_bandeiras')->nullOnDelete();
            $table->timestamps();

            $table->unique(['marca_id', 'bandeira_id']);
            $table->index('grupo_bandeira_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bandeira_marca');
    }
};

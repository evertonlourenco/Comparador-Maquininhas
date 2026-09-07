<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regra 7: adquirente subjacente e informacao de transparencia, nao de
 * deduplicacao. Yelly, SidePay e FacilityPay compartilham adquirente mas
 * concorrem como marcas independentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adquirentes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80);
            $table->string('slug', 80)->unique();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adquirentes');
    }
};

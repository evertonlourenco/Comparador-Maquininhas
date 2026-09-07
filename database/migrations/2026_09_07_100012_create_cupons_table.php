<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regra 5. Nao existe nenhuma coluna de taxa aqui, de proposito: a taxa do
 * afiliado e igual a do site oficial. A vantagem do link e o desconto.
 * valido_ate e NOT NULL para que a ocultacao ao vencer seja automatica.
 *
 * equipamento_id nulo (o caso comum) = cupom vale para o catalogo todo da marca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->foreignId('equipamento_id')->nullable()
                ->constrained('equipamentos')->cascadeOnDelete();

            $table->string('codigo', 60);
            $table->string('descricao', 255)->nullable();
            $table->string('tipo_desconto', 20);
            $table->string('incide_sobre', 20);
            $table->decimal('valor', 10, 2);

            $table->date('valido_de');
            $table->date('valido_ate');

            // Link de afiliado com o cupom ja aplicado.
            $table->string('link_afiliado', 500);
            $table->text('termos')->nullable();

            $table->string('status', 20)->default('ativo');
            $table->smallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['marca_id', 'codigo', 'valido_ate']);
            $table->index(['status', 'valido_ate']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE cupons
                ADD CONSTRAINT chk_cupons_validade CHECK (valido_de <= valido_ate)
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cupons');
    }
};

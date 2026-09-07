<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regra 4, classe A: taxa publicada pela marca, com url de fonte e data de
 * verificacao. Tabela separada de faixas_reportadas de proposito - se fossem
 * a mesma tabela, um dia alguem leria mediana como numero publicado.
 *
 * Regra 1: a chave e plano + tipo de operacao + grupo de bandeiras + parcelas
 * + prazo de recebimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxas_divulgadas', function (Blueprint $table) {
            $table->id();

            // Denormalizado a partir de planos.marca_id para o gerador de JSON
            // estatico e os filtros do admin. Sincronizado no model, nunca na mao.
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $table->foreignId('grupo_bandeira_id')->constrained('grupos_bandeiras')->restrictOnDelete();
            $table->foreignId('prazo_recebimento_id')->constrained('prazos_recebimento')->restrictOnDelete();

            $table->string('tipo_operacao', 30);

            // Regra 2: inteiro de 1 a 21, nunca faixa agrupada.
            $table->unsignedTinyInteger('parcelas');

            $table->decimal('percentual', 6, 4);
            $table->decimal('valor_fixo', 8, 2)->default(0);

            // Regra 8: nenhuma taxa entra sem fonte e data de verificacao.
            $table->string('url_fonte', 500);
            $table->string('fonte_tipo', 30);
            $table->date('data_verificacao');
            $table->foreignId('verificado_por')->nullable()->constrained('users')->nullOnDelete();

            // Regra 10: nada vai ao ar sem aprovacao humana.
            $table->string('status', 20)->default('rascunho');
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(
                ['plano_id', 'tipo_operacao', 'grupo_bandeira_id', 'parcelas', 'prazo_recebimento_id'],
                'taxas_divulgadas_chave_unica'
            );
            $table->index(['marca_id', 'status']);
            $table->index('data_verificacao');
        });

        // SQLite (usado nos testes) nao aceita ADD CONSTRAINT.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE taxas_divulgadas
                ADD CONSTRAINT chk_taxas_divulgadas_parcelas CHECK (
                    (tipo_operacao = 'credito_parcelado' AND parcelas BETWEEN 2 AND 21)
                    OR (tipo_operacao <> 'credito_parcelado' AND parcelas = 1)
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxas_divulgadas');
    }
};

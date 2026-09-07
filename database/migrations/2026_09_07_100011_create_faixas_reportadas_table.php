<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regra 4, classe B: para Cielo, Rede, GetNet e Stone, que nao publicam tabela.
 * Nenhuma coluna aqui se chama "percentual" - so "percentual_mediana",
 * "percentual_minimo" e "percentual_maximo". Isso impede um $taxa->percentual
 * acidental numa view e obriga a exibicao como faixa, nunca como numero exato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faixas_reportadas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $table->foreignId('grupo_bandeira_id')->constrained('grupos_bandeiras')->restrictOnDelete();
            $table->foreignId('prazo_recebimento_id')->constrained('prazos_recebimento')->restrictOnDelete();

            $table->string('tipo_operacao', 30);
            $table->unsignedTinyInteger('parcelas');

            $table->decimal('percentual_mediana', 6, 4);
            $table->decimal('percentual_minimo', 6, 4);
            $table->decimal('percentual_maximo', 6, 4);
            $table->unsignedInteger('n_relatos');
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->text('metodologia')->nullable();

            // Regra 8, tambem aqui.
            $table->string('fonte_descricao', 255);
            $table->string('url_fonte', 500)->nullable();
            $table->date('data_verificacao');
            $table->foreignId('verificado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('rascunho');
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(
                ['plano_id', 'tipo_operacao', 'grupo_bandeira_id', 'parcelas', 'prazo_recebimento_id'],
                'faixas_reportadas_chave_unica'
            );
            $table->index(['marca_id', 'status']);
            $table->index('data_verificacao');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE faixas_reportadas
                ADD CONSTRAINT chk_faixas_reportadas_parcelas CHECK (
                    (tipo_operacao = 'credito_parcelado' AND parcelas BETWEEN 2 AND 21)
                    OR (tipo_operacao <> 'credito_parcelado' AND parcelas = 1)
                )
            ");

            DB::statement('
                ALTER TABLE faixas_reportadas
                ADD CONSTRAINT chk_faixas_reportadas_ordem CHECK (
                    percentual_minimo <= percentual_mediana
                    AND percentual_mediana <= percentual_maximo
                )
            ');

            DB::statement('
                ALTER TABLE faixas_reportadas
                ADD CONSTRAINT chk_faixas_reportadas_periodo CHECK (periodo_inicio <= periodo_fim)
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faixas_reportadas');
    }
};

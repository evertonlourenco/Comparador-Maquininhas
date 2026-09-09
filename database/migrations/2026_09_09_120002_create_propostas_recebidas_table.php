<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 10: staging da captacao de relatos, formulario publico e anonimo em
 * /enviar-proposta. Regra 10 vale aqui tambem, na sua forma mais forte: nada
 * aqui vira faixa_reportada sozinho. Um humano le, decide e cadastra a faixa
 * a mao no painel — esta tabela nunca escreve em faixas_reportadas.
 *
 * Sem nome, sem e-mail, sem IP: o formulario e anonimo por desenho (pedido
 * explicito), e o unico dado de contato possivel e o anexo, que o proprio
 * lojista opta por enviar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propostas_recebidas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();
            $table->foreignId('prazo_recebimento_id')->nullable()->constrained('prazos_recebimento')->nullOnDelete();

            // Array de {tipo_operacao, parcelas, percentual} — o lojista relata
            // quantas linhas quiser, a comecar de quatro pre-preenchidas na tela.
            $table->json('taxas_relatadas');

            $table->decimal('mensalidade', 10, 2)->nullable();
            $table->date('data_proposta');
            $table->char('estado', 2);
            $table->string('segmento', 30);
            $table->decimal('faturamento_aproximado', 12, 2)->nullable();

            $table->string('anexo_caminho', 255)->nullable();
            $table->string('anexo_mime', 60)->nullable();

            $table->boolean('consentimento_uso_agregado');

            $table->string('status', 20)->default('pendente');
            $table->text('observacao_admin')->nullable();

            $table->timestamps();

            $table->index(['status', 'marca_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propostas_recebidas');
    }
};

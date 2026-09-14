<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 13: staging do monitor de mudancas (projeto Node separado, em
 * outro repositorio). A etapa 11 registrou que "nao ha historico de taxa":
 * aprovar e editar no lugar. Por isso o monitor nunca escreve em
 * taxas_divulgadas/equipamento_plano/cupons sozinho — ele so propoe aqui, e
 * um humano decide no painel (regra 10, forma mais forte, mesmo espirito de
 * propostas_recebidas e relatos_taxa_incorreta da etapa 10).
 *
 * fonte_id e a chave estavel definida no fontes.json do repositorio do
 * monitor (ex.: "pagbank-tabela-taxas") — nao ha tabela "fontes_monitoradas"
 * neste banco de proposito: a lista de URLs e o estado de hash entre
 * execucoes moram versionados no repositorio do monitor (pasta estado/,
 * commitada pela propria Action a cada execucao), nao aqui. Isso mantem o
 * monitor autossuficiente e nao abre uma API publica de configuracao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deteccoes_de_mudanca', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marca_id')->nullable()
                ->constrained('marcas')->nullOnDelete();

            $table->string('fonte_id', 100);
            $table->string('categoria', 30);
            $table->string('tipo', 20);
            $table->string('url', 500);

            // Preenchidos so quando tipo = mudanca.
            $table->text('resumo')->nullable();
            $table->longText('trecho_alterado')->nullable();
            $table->string('hash_anterior', 64)->nullable();
            $table->string('hash_novo', 64)->nullable();

            // Preenchido so quando tipo = falha.
            $table->text('mensagem_erro')->nullable();

            $table->string('status', 20)->default('pendente');
            $table->text('observacao_admin')->nullable();

            $table->timestamp('detectado_em');

            $table->timestamps();

            $table->index(['status', 'detectado_em']);
            $table->index('fonte_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deteccoes_de_mudanca');
    }
};

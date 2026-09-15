<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 16: o verificador de link de afiliado (`links:verificar`) grava aqui
 * o resultado do último HEAD em `marcas.site_url` e `cupons.link_afiliado`.
 *
 * `link_ultimo_status` é o código HTTP quando a requisição completou (nulo
 * quando nem isso — timeout, DNS, TLS: aí `link_ultima_falha` guarda o
 * motivo). `link_quebrado` é a coluna que o painel filtra e colore; existe
 * separada do status para não obrigar todo leitor a saber que "sem status" e
 * "status >= 400" são os dois jeitos de estar quebrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->unsignedSmallInteger('link_ultimo_status')->nullable()->after('site_url');
            $table->string('link_ultima_falha', 191)->nullable()->after('link_ultimo_status');
            $table->boolean('link_quebrado')->default(false)->after('link_ultima_falha');
            $table->timestamp('link_verificado_em')->nullable()->after('link_quebrado');
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->unsignedSmallInteger('link_ultimo_status')->nullable()->after('link_afiliado');
            $table->string('link_ultima_falha', 191)->nullable()->after('link_ultimo_status');
            $table->boolean('link_quebrado')->default(false)->after('link_ultima_falha');
            $table->timestamp('link_verificado_em')->nullable()->after('link_quebrado');
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn(['link_ultimo_status', 'link_ultima_falha', 'link_quebrado', 'link_verificado_em']);
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->dropColumn(['link_ultimo_status', 'link_ultima_falha', 'link_quebrado', 'link_verificado_em']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 17, achado em 16/09/2026: a Yelly devolve HTTP 404 pra URL do cupom
 * de afiliado mesmo com a página funcionando normalmente no navegador (o
 * mesmo achado do monitor de mudanças, `ignora_status_http` em
 * `monitor/fontes.json` - CloudFront/S3 dela serve o app React de verdade
 * numa resposta de erro customizada, sem sobrescrever o status pra 200).
 * `links:verificar` não tem como distinguir isso sozinho - o status HTTP
 * é o único sinal que ele olha, sem o "sinal de bloqueio por conteúdo" que
 * o monitor tem. `link_confirmado_manualmente` é a válvula de escape: um
 * humano que já abriu o link e viu que funciona pode marcar aqui, e o
 * comando para de reportar "quebrado" pra aquele registro - sem deixar de
 * gravar o status HTTP real, que continua honesto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->boolean('link_confirmado_manualmente')->default(false)->after('link_verificado_em');
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->boolean('link_confirmado_manualmente')->default(false)->after('link_verificado_em');
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn('link_confirmado_manualmente');
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->dropColumn('link_confirmado_manualmente');
        });
    }
};

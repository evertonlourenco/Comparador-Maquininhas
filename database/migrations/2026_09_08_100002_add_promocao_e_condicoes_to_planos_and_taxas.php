<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 05, segunda rodada: o preco que dura 30 dias nao pode ocupar o lugar
 * do preco que dura.
 *
 * O que motivou. A carga da etapa 04 gravou o "Periodo Promocional" do Ton
 * como plano de enquadramento automatico com faixa de faturamento de R$ 2.000
 * a R$ 5.000. Esses R$ 5.000 nunca foram faturamento: sao o teto de volume
 * processado da promocao ("30 primeiros dias ou ate R$ 5 mil processados, o
 * que vier antes"). Com isso o motor tratava a tabela promocional como um
 * plano permanente e a ranqueava - com percentual de entrada, ela ganhava de
 * todo mundo, inclusive do plano regular da propria marca. Vender como preco
 * permanente uma taxa de 30 dias e exatamente o risco de CDC que a regra 6
 * existe para evitar.
 *
 * Tres campos novos, e nenhum deles e opiniao:
 *
 * - planos.promocional_dias / promocional_valor_processado - os dois limites
 *   da promocao, que valem em disjuncao (o que vier antes). Nulo em um deles
 *   significa que aquele limite nao existe, nao que e zero.
 * - planos.promocional_sucessor_id - em qual plano o lojista cai quando a
 *   promocao acaba. Nulo com enquadramento promocional significa "cai no
 *   enquadramento automatico da marca", que e o caso do Ton.
 * - taxas_divulgadas.condicao - a taxa publicada que so vale se o lojista
 *   fizer alguma coisa. O caso concreto e o Pix a 0% do Ton, que depende de
 *   ativar a chave Pix no aplicativo. Isso nao e promocao (nao vence) nem
 *   observacao interna: e uma condicao que precisa aparecer junto do numero.
 * - equipamento_plano.parcelas_adesao - em quantas vezes sem juros a marca
 *   parcela a adesao. Sem isso o motor nao consegue distinguir "12x de
 *   R$ 16,58, oferecido pela marca" da amortizacao em 12 meses que ele mesmo
 *   faz para comparar - e as duas viram o mesmo numero na tela, uma sendo
 *   fato e a outra sendo criterio nosso.
 *
 * O quarto tipo de enquadramento (promocional) nao precisa de migration: a
 * coluna e string justamente porque ENUM do MySQL exige SQL cru para mudar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->unsignedSmallInteger('promocional_dias')->nullable()->after('compromisso');
            $table->decimal('promocional_valor_processado', 12, 2)->nullable()->after('promocional_dias');
            $table->foreignId('promocional_sucessor_id')->nullable()->after('promocional_valor_processado')
                ->constrained('planos')->nullOnDelete();
        });

        Schema::table('taxas_divulgadas', function (Blueprint $table) {
            $table->string('condicao', 255)->nullable()->after('valor_fixo');
        });

        Schema::table('equipamento_plano', function (Blueprint $table) {
            $table->unsignedSmallInteger('parcelas_adesao')->nullable()->after('preco_adesao_promocional');
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promocional_sucessor_id');
            $table->dropColumn(['promocional_dias', 'promocional_valor_processado']);
        });

        Schema::table('taxas_divulgadas', function (Blueprint $table) {
            $table->dropColumn('condicao');
        });

        Schema::table('equipamento_plano', function (Blueprint $table) {
            $table->dropColumn('parcelas_adesao');
        });
    }
};

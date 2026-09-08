<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 05, decisao 3: o motor nao pode cobrar antecipacao duas vezes.
 *
 * A antecipacao automatica ja esta embutida no percentual da taxa quando o
 * prazo e curto ("na hora", "em 1 dia util", "em 14 dias" - este ultimo e
 * antecipacao parcial contratada). A antecipacao avulsa e o campo
 * planos.taxa_antecipacao_mensal, cobrado sobre recebiveis que ainda nao
 * foram antecipados.
 *
 * Isto e coluna, e nao uma lista de codigos no PHP, porque o painel permite
 * cadastrar prazos novos (foi o motivo de prazo ser tabela e nao enum). Um
 * prazo "d_7" criado amanha precisa declarar se ja embute antecipacao; sem a
 * coluna, o motor teria que adivinhar - e adivinhar aqui e cobrar duas vezes
 * ou nao cobrar nenhuma.
 *
 * Default false: o prazo novo entra como "nao antecipado" ate alguem afirmar
 * o contrario no painel. Errar para o lado de nao embutir e o lado honesto -
 * o motor cobra a antecipacao avulsa visivel, com aviso, em vez de esconder
 * um custo dentro do percentual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prazos_recebimento', function (Blueprint $table) {
            $table->boolean('antecipacao_embutida')->default(false)->after('dias');
        });
    }

    public function down(): void
    {
        Schema::table('prazos_recebimento', function (Blueprint $table) {
            $table->dropColumn('antecipacao_embutida');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 17. Duas premissas da etapa 02 nao se sustentaram na pratica:
 *
 * - `valido_ate` era NOT NULL "para a ocultacao ao vencer ser automatica".
 *   Dito pelo Everton (dono do domínio): via de regra cupom de afiliado nao
 *   tem prazo nenhum - o que muda de vez em quando e o link, o codigo ou o
 *   percentual, nao uma data de expiracao publicada pela marca. NULL passa a
 *   significar "sem prazo definido, vale ate alguem trocar a mao no painel";
 *   uma data continua funcionando normalmente para o cupom raro que de fato
 *   tem validade.
 * - `valor`/`tipo_desconto` eram NOT NULL. PagBank e Mercado Pago tem link de
 *   afiliado com desconto real, mas o Everton nao sabe quanto - varia por
 *   equipamento e pode mudar de mes para mes. `App\Support\Marcas\
 *   EconomiaDoCupom` ja tinha o padrao de "sem numero, cai para o texto"
 *   (preco de equipamento ausente); passa a valer tambem para o proprio
 *   `cupom->valor` ausente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cupons DROP CONSTRAINT chk_cupons_validade');
        }

        Schema::table('cupons', function (Blueprint $table) {
            $table->string('tipo_desconto', 20)->nullable()->change();
            $table->decimal('valor', 10, 2)->nullable()->change();
            $table->date('valido_ate')->nullable()->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE cupons
                ADD CONSTRAINT chk_cupons_validade CHECK (valido_ate IS NULL OR valido_de <= valido_ate)
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cupons DROP CONSTRAINT chk_cupons_validade');
        }

        Schema::table('cupons', function (Blueprint $table) {
            $table->string('tipo_desconto', 20)->nullable(false)->change();
            $table->decimal('valor', 10, 2)->nullable(false)->change();
            $table->date('valido_ate')->nullable(false)->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE cupons
                ADD CONSTRAINT chk_cupons_validade CHECK (valido_de <= valido_ate)
            ');
        }
    }
};

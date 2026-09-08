<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Nao use WithoutModelEvents aqui.
 *
 * O dominio depende de eventos de model: o hook de TemChaveDeTaxa e o unico
 * lugar que preenche marca_id a partir do plano (regra 1), e a guarda de
 * TaxaDivulgada e o que recusa taxa em marca que nao publica tabela (regra 4).
 * Com os eventos desligados, a carga gravaria marca_id nulo e passaria por
 * cima da regra 4 em silencio.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Estrutura: dimensoes curadas da taxa.
        $this->call(DimensoesSeeder::class);

        // Etapa 04 - dados reais. Ordem importa: marca depende de adquirente e
        // de bandeira; taxa depende de plano, que depende de marca.
        $this->call([
            AdquirentesSeeder::class,
            BandeirasSeeder::class,
            MarcasSeeder::class,
            PagBankSeeder::class,
            InfinitePaySeeder::class,
            TonSeeder::class,
            SumUpSeeder::class,
        ]);

        $this->usuarioLocal();
    }

    /**
     * So em ambiente local, e so se nao houver ninguem: sem isto um
     * migrate:fresh --seed deixaria o /admin sem nenhum usuario para entrar -
     * e o painel exige 2FA, entao nem daria para criar um pelo login.
     *
     * Nunca roda fora do local: usuario semeado em producao e porta aberta.
     */
    private function usuarioLocal(): void
    {
        if (! app()->environment('local') || User::exists()) {
            return;
        }

        User::factory()->create([
            'name' => 'Admin local',
            'email' => 'admin@comparador-maquininhas.test',
        ]);

        $this->command?->warn(
            'Usuario local criado: admin@comparador-maquininhas.test / senha padrao da factory. '
            .'O painel exige configurar o 2FA no primeiro login.'
        );
    }
}

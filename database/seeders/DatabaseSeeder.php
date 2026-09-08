<?php

namespace Database\Seeders;

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
        ]);
    }
}

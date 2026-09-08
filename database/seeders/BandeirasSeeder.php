<?php

namespace Database\Seeders;

use App\Models\Bandeira;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * So bandeiras que aparecem em pelo menos uma marca ja carregada. Em qual
 * grupo cada uma cai nao se decide aqui: e decisao de cada marca, no pivot
 * bandeira_marca (ver MarcasSeeder).
 */
class BandeirasSeeder extends Seeder
{
    public function run(): void
    {
        $bandeiras = [
            'Visa', 'Mastercard', 'Elo', 'American Express', 'Hipercard', 'Hiper',
            'Diners Club', 'Cabal',
            // Vale-refeicao e alimentacao. Operam como debito, com prazo e
            // percentual proprios - por isso sao grupo de bandeira, nao tipo
            // de operacao.
            'Alelo', 'Pluxee', 'Ticket', 'Up Brasil', 'VR',
        ];

        foreach ($bandeiras as $ordem => $nome) {
            Bandeira::updateOrCreate(
                ['slug' => Str::slug($nome)],
                ['nome' => $nome, 'ordem' => $ordem],
            );
        }
    }
}

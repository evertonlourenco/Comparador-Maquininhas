<?php

namespace Database\Seeders;

use App\Enums\StatusMarca;
use App\Models\Adquirente;
use App\Models\Bandeira;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use Illuminate\Database\Seeder;

/**
 * Regra 4: publica_tabela decide qual classe de taxa a marca admite. Cielo,
 * Rede, GetNet e Stone entram com false - o dado delas e faixa reportada, que
 * depende de relatos (etapa 10) e por isso ainda nao tem linha nenhuma.
 *
 * Regra 8: nota do Reclame Aqui e campo manual, com data e link. Fica nula
 * aqui de proposito - campo vazio e honesto, numero chutado nao e.
 */
class MarcasSeeder extends Seeder
{
    public function run(): void
    {
        $vm = [GrupoBandeira::VISA_MASTER, ['visa', 'mastercard']];

        $marcas = [
            [
                'nome' => 'PagBank', 'slug' => 'pagbank', 'adquirente' => 'pagseguro',
                'site' => 'https://pagbank.com.br/para-seu-negocio/maquininhas',
                'publica_tabela' => true, 'ordem' => 1,
                'descricao' => 'Marca de maquininhas do PagSeguro. Publica tabela por prazo de '
                    .'recebimento (na hora, 14 dias e 30 dias) e por grupo de bandeiras.',
                'bandeiras' => [
                    $vm,
                    [GrupoBandeira::DEMAIS, ['elo', 'american-express', 'hipercard', 'hiper', 'diners-club', 'cabal']],
                ],
            ],
            [
                'nome' => 'InfinitePay', 'slug' => 'infinitepay', 'adquirente' => 'cloudwalk',
                'site' => 'https://www.infinitepay.io/taxas',
                'publica_tabela' => true, 'ordem' => 2,
                'descricao' => 'Marca da CloudWalk. Publica quatro tabelas por faixa de faturamento '
                    .'mensal, cada uma com tres planos de recebimento e parcelas de 1x a 12x.',
                'bandeiras' => [
                    $vm,
                    [GrupoBandeira::DEMAIS, ['elo', 'american-express', 'hiper', 'hipercard']],
                ],
            ],
            [
                'nome' => 'Ton', 'slug' => 'ton', 'adquirente' => 'stone',
                'site' => 'https://www.ton.com.br/',
                'publica_tabela' => true, 'ordem' => 3,
                'descricao' => 'Marca da Stone voltada ao pequeno negocio. Publica tabela por faixa '
                    .'de faturamento, com parcelamento em ate 21x e recebimento na hora ou em 1 dia util.',
                'bandeiras' => [
                    $vm,
                    [GrupoBandeira::DEMAIS, ['elo', 'american-express']],
                    [GrupoBandeira::VOUCHER, ['alelo', 'pluxee', 'ticket', 'up-brasil', 'vr']],
                ],
            ],
            [
                'nome' => 'Stone', 'slug' => 'stone', 'adquirente' => 'stone',
                'site' => 'https://www.stone.com.br/',
                'publica_tabela' => false, 'ordem' => 4,
                'descricao' => 'Nao publica tabela de taxas: a condicao e negociada caso a caso. '
                    .'O dado desta marca e faixa reportada, nunca taxa divulgada.',
                'bandeiras' => [],
            ],
            [
                'nome' => 'Cielo', 'slug' => 'cielo', 'adquirente' => 'cielo',
                'site' => 'https://www.cielo.com.br/',
                'publica_tabela' => false, 'ordem' => 5,
                'descricao' => 'Nao publica tabela de taxas. O dado desta marca e faixa reportada.',
                'bandeiras' => [],
            ],
            [
                'nome' => 'Rede', 'slug' => 'rede', 'adquirente' => 'rede',
                'site' => 'https://www.userede.com.br/',
                'publica_tabela' => false, 'ordem' => 6,
                'descricao' => 'Nao publica tabela de taxas. O dado desta marca e faixa reportada.',
                'bandeiras' => [],
            ],
            [
                'nome' => 'GetNet', 'slug' => 'getnet', 'adquirente' => 'getnet',
                'site' => 'https://www.getnet.com.br/',
                'publica_tabela' => false, 'ordem' => 7,
                'descricao' => 'Nao publica tabela de taxas. O dado desta marca e faixa reportada.',
                'bandeiras' => [],
            ],
        ];

        foreach ($marcas as $dados) {
            $marca = Marca::updateOrCreate(
                ['slug' => $dados['slug']],
                [
                    'adquirente_id' => Adquirente::where('slug', $dados['adquirente'])->value('id'),
                    'nome' => $dados['nome'],
                    'site_url' => $dados['site'],
                    'descricao' => $dados['descricao'],
                    'publica_tabela' => $dados['publica_tabela'],
                    'status' => StatusMarca::Ativa,
                    'ordem' => $dados['ordem'],
                ],
            );

            $this->vincularBandeiras($marca, $dados['bandeiras']);
        }
    }

    /** O grupo mora no pivot: cada marca decide onde Elo e Amex caem. */
    private function vincularBandeiras(Marca $marca, array $porGrupo): void
    {
        $vinculos = [];

        foreach ($porGrupo as [$codigoGrupo, $slugs]) {
            $grupoId = GrupoBandeira::where('codigo', $codigoGrupo)->value('id');

            foreach ($slugs as $slug) {
                $bandeiraId = Bandeira::where('slug', $slug)->value('id');
                $vinculos[$bandeiraId] = ['grupo_bandeira_id' => $grupoId];
            }
        }

        $marca->bandeiras()->sync($vinculos);
    }
}

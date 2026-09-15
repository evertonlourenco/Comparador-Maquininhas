<?php

namespace Tests\Feature\Dominio;

use App\Enums\StatusItem;
use App\Enums\StatusMarca;
use App\Enums\StatusPublicacao;
use App\Models\Cupom;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\TaxaDivulgada;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Achado em produção em 15/09/2026: rodar `db:seed` de novo (para pegar uma
 * marca nova, ou corrigir um número) revalidava a chave de TUDO que já
 * existia e reescrevia `status` para o padrão do seeder - desfazendo, em
 * silêncio, taxa que o Everton já tinha aprovado no painel, marca que ele
 * tinha pausado, cupom que ele tinha desativado, e o nome que renomeou numa
 * "Tabela do Plano" (etapa 17). `status`/`nome` só valem na criação: em quem
 * já existe, o reseed atualiza número/fonte (fato, releitura corrige) e
 * nunca mexe no que é decisão do admin.
 */
class ReseedNaoDesfazAprovacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseed_preserva_status_aprovado_de_uma_taxa(): void
    {
        $this->seed(DatabaseSeeder::class);

        $taxa = TaxaDivulgada::whereHas('plano.marca', fn ($q) => $q->where('slug', 'ton'))->firstOrFail();
        $taxa->update(['status' => StatusPublicacao::Publicado]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(StatusPublicacao::Publicado, $taxa->fresh()->status);
    }

    public function test_reseed_preserva_nome_renomeado_de_um_plano(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plano = Plano::whereHas('marca', fn ($q) => $q->where('slug', 'ton'))->firstOrFail();
        $plano->update(['nome' => 'Nome Que a Marca Deu Depois']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Nome Que a Marca Deu Depois', $plano->fresh()->nome);
    }

    public function test_reseed_preserva_marca_pausada_no_painel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $marca = Marca::where('slug', 'ton')->firstOrFail();
        $marca->update(['status' => StatusMarca::Pausada]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(StatusMarca::Pausada, $marca->fresh()->status);
    }

    public function test_reseed_preserva_cupom_desativado_no_painel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $cupom = Cupom::whereHas('marca', fn ($q) => $q->where('slug', 'ton'))->firstOrFail();
        $cupom->update(['status' => StatusItem::Pausado]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(StatusItem::Pausado, $cupom->fresh()->status);
    }

    /** O reseed continua funcionando de verdade: número novo ainda entra. */
    public function test_reseed_ainda_atualiza_o_percentual_de_uma_taxa_nao_aprovada(): void
    {
        $this->seed(DatabaseSeeder::class);

        $taxa = TaxaDivulgada::whereHas('plano.marca', fn ($q) => $q->where('slug', 'ton'))->firstOrFail();
        $percentualOriginal = $taxa->percentual;
        $taxa->update(['percentual' => 99.9999]);

        $this->seed(DatabaseSeeder::class);

        $this->assertNotEquals('99.9999', $taxa->fresh()->percentual);
        $this->assertSame($percentualOriginal, $taxa->fresh()->percentual);
    }
}

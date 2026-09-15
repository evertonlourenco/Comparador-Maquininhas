<?php

namespace Tests\Feature\Admin;

use App\Enums\StatusItem;
use App\Enums\StatusMarca;
use App\Filament\Actions\BuscarImagemPorUrlAction;
use App\Filament\Resources\Bandeiras\Pages\ListBandeiras;
use App\Filament\Resources\Equipamentos\Pages\ListEquipamentos;
use App\Filament\Resources\Marcas\Pages\ListMarcas;
use App\Models\Adquirente;
use App\Models\Bandeira;
use App\Models\Equipamento;
use App\Models\Marca;
use App\Models\User;
use App\Support\Uploads\ImagemSeguraWebp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 15: a acao "Buscar imagem por URL" existe em Marca, Equipamento e
 * Bandeira, e a garantia central e a aprovacao humana (regra 10) — a busca
 * sozinha nunca grava nada; so BuscarImagemPorUrlAction::aprovarEGravar()
 * grava, e so quando ha um candidato de verdade.
 *
 * A busca em si (SSRF, tipo invalido, extracao de og:image/icone) tem teste
 * proprio em BuscaDeImagemExternaTest — aqui interessa so o contrato "sem
 * candidato aprovado, nada muda no registro", e que a acao esta de fato
 * ligada nas tres tabelas do painel.
 */
class BuscaDeImagemPorUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Storage::fake('public');
    }

    private function criarMarca(): Marca
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Teste', 'slug' => 'adquirente-teste-'.uniqid()]);

        return Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Teste',
            'slug' => 'marca-teste-'.uniqid(),
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);
    }

    private function pngValidoEmBase64(): string
    {
        $imagem = imagecreatetruecolor(4, 4);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 5, 15, 25));
        ob_start();
        imagepng($imagem);
        $bytes = ob_get_clean();
        imagedestroy($imagem);

        return base64_encode(ImagemSeguraWebp::converterParaWebp($bytes));
    }

    public function test_sem_candidato_buscado_a_aprovacao_nao_grava_nada(): void
    {
        $marca = $this->criarMarca();

        BuscarImagemPorUrlAction::aprovarEGravar(
            data: ['url' => 'https://exemplo.test/imprensa', 'candidato_base64' => null],
            record: $marca,
            campo: 'logo_path',
            diretorio: 'marcas/logos',
        );

        $this->assertNull($marca->fresh()->logo_path);
    }

    public function test_candidato_adulterado_que_nao_decodifica_em_webp_nao_grava(): void
    {
        $marca = $this->criarMarca();

        BuscarImagemPorUrlAction::aprovarEGravar(
            data: ['url' => 'https://exemplo.test/imprensa', 'candidato_base64' => base64_encode('isto não é um webp')],
            record: $marca,
            campo: 'logo_path',
            diretorio: 'marcas/logos',
        );

        $this->assertNull($marca->fresh()->logo_path);
    }

    public function test_candidato_aprovado_grava_o_caminho_no_registro(): void
    {
        $marca = $this->criarMarca();

        BuscarImagemPorUrlAction::aprovarEGravar(
            data: ['url' => 'https://exemplo.test/imprensa', 'candidato_base64' => $this->pngValidoEmBase64()],
            record: $marca,
            campo: 'logo_path',
            diretorio: 'marcas/logos',
        );

        $marca->refresh();

        $this->assertNotNull($marca->logo_path);
        $this->assertStringStartsWith('marcas/logos/', $marca->logo_path);
    }

    public function test_equipamento_grava_no_campo_imagem_path(): void
    {
        $marca = $this->criarMarca();

        $equipamento = Equipamento::create([
            'marca_id' => $marca->id,
            'nome' => 'Maquininha Teste',
            'slug' => 'maquininha-teste-'.uniqid(),
            'tipo' => 'smart',
            'status' => StatusItem::Ativo,
        ]);

        BuscarImagemPorUrlAction::aprovarEGravar(
            data: ['url' => 'https://exemplo.test/produto', 'candidato_base64' => $this->pngValidoEmBase64()],
            record: $equipamento,
            campo: 'imagem_path',
            diretorio: 'equipamentos',
        );

        $this->assertStringStartsWith('equipamentos/', $equipamento->fresh()->imagem_path);
    }

    public function test_bandeira_grava_no_campo_logo_path(): void
    {
        $bandeira = Bandeira::create(['nome' => 'Visa', 'slug' => 'visa-'.uniqid()]);

        BuscarImagemPorUrlAction::aprovarEGravar(
            data: ['url' => 'https://exemplo.test/visa', 'candidato_base64' => $this->pngValidoEmBase64()],
            record: $bandeira,
            campo: 'logo_path',
            diretorio: 'bandeiras',
        );

        $this->assertStringStartsWith('bandeiras/', $bandeira->fresh()->logo_path);
    }

    public function test_a_acao_esta_ligada_na_listagem_de_marcas(): void
    {
        $marca = $this->criarMarca();

        Livewire::test(ListMarcas::class)
            ->assertTableActionExists('buscarImagemPorUrl', record: $marca);
    }

    public function test_a_acao_esta_ligada_na_listagem_de_equipamentos(): void
    {
        $marca = $this->criarMarca();
        $equipamento = Equipamento::create([
            'marca_id' => $marca->id,
            'nome' => 'Maquininha Teste',
            'slug' => 'maquininha-teste-'.uniqid(),
            'tipo' => 'smart',
            'status' => StatusItem::Ativo,
        ]);

        Livewire::test(ListEquipamentos::class)
            ->assertTableActionExists('buscarImagemPorUrl', record: $equipamento);
    }

    public function test_a_acao_esta_ligada_na_listagem_de_bandeiras(): void
    {
        $bandeira = Bandeira::create(['nome' => 'Visa', 'slug' => 'visa-'.uniqid()]);

        Livewire::test(ListBandeiras::class)
            ->assertTableActionExists('buscarImagemPorUrl', record: $bandeira);
    }

    public function test_o_modal_da_acao_abre_sem_erro(): void
    {
        $marca = $this->criarMarca();

        Livewire::test(ListMarcas::class)
            ->mountTableAction('buscarImagemPorUrl', $marca)
            ->assertOk();
    }
}

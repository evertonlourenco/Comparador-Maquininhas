<?php

namespace Tests\Feature\Relatos;

use App\Enums\StatusMarca;
use App\Models\Adquirente;
use App\Models\Marca;
use App\Models\PropostaRecebida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Etapa 10: /enviar-proposta. Regra 10 na forma mais forte — nada aqui vira
 * faixa_reportada sozinho, só entra pendente de revisão.
 */
class EnviarPropostaTest extends TestCase
{
    use RefreshDatabase;

    private function criarMarca(bool $aceitaRelatos = true, ?string $nome = null): Marca
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Teste', 'slug' => 'adquirente-teste-'.uniqid()]);
        $nome ??= 'Cielo Teste '.uniqid();

        return Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => $nome,
            'slug' => \Illuminate\Support\Str::slug($nome),
            'publica_tabela' => false,
            'aceita_relatos' => $aceitaRelatos,
            'status' => StatusMarca::Ativa,
        ]);
    }

    private function dadosValidos(Marca $marca, array $sobrescreve = []): array
    {
        return array_merge([
            'marca_id' => $marca->id,
            'taxas' => [
                ['tipo_operacao' => 'debito', 'percentual' => '1,99'],
                ['tipo_operacao' => 'credito_avista', 'percentual' => ''],
                ['tipo_operacao' => 'credito_parcelado', 'parcelas' => '6', 'percentual' => '3,49'],
                ['tipo_operacao' => 'pix', 'percentual' => ''],
            ],
            'data_proposta' => Carbon::yesterday()->toDateString(),
            'estado' => 'SP',
            'segmento' => 'padaria',
            'consentimento_uso_agregado' => '1',
            'carregado_em' => now()->subSeconds(10)->timestamp,
        ], $sobrescreve);
    }

    public function test_pagina_lista_so_marcas_que_aceitam_relatos(): void
    {
        $aceita = $this->criarMarca(true, 'Marca Que Aceita Relatos');
        $naoAceita = $this->criarMarca(false, 'Marca Que Nao Aceita Relatos');

        $resposta = $this->get('/enviar-proposta');

        $resposta->assertOk();
        $resposta->assertSee($aceita->nome);
        $resposta->assertDontSee($naoAceita->nome);
    }

    public function test_envio_feliz_grava_proposta_pendente(): void
    {
        $marca = $this->criarMarca();

        $this->post('/enviar-proposta', $this->dadosValidos($marca))
            ->assertRedirect(route('propostas.create'));

        $this->assertDatabaseCount('propostas_recebidas', 1);

        $proposta = PropostaRecebida::first();
        $this->assertSame('pendente', $proposta->status->value);
        $this->assertSame($marca->id, $proposta->marca_id);
        $this->assertCount(2, $proposta->taxas_relatadas);
        $this->assertSame(1.99, (float) $proposta->taxas_relatadas[0]['percentual']);
    }

    public function test_honeypot_preenchido_nao_grava_e_responde_sucesso(): void
    {
        $marca = $this->criarMarca();

        $this->post('/enviar-proposta', $this->dadosValidos($marca, [
            'confirmar_contato' => 'sou um robô',
        ]))->assertRedirect();

        $this->assertDatabaseCount('propostas_recebidas', 0);
    }

    public function test_envio_rapido_demais_nao_grava(): void
    {
        $marca = $this->criarMarca();

        $this->post('/enviar-proposta', $this->dadosValidos($marca, [
            'carregado_em' => now()->timestamp,
        ]))->assertRedirect();

        $this->assertDatabaseCount('propostas_recebidas', 0);
    }

    public function test_limite_de_taxa_bloqueia_apos_cinco_envios_na_hora(): void
    {
        $marca = $this->criarMarca();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/enviar-proposta', $this->dadosValidos($marca))->assertRedirect();
        }

        $this->post('/enviar-proposta', $this->dadosValidos($marca))->assertStatus(429);
    }

    public function test_sem_nenhuma_taxa_preenchida_falha_a_validacao(): void
    {
        $marca = $this->criarMarca();

        $dados = $this->dadosValidos($marca, [
            'taxas' => [
                ['tipo_operacao' => 'debito', 'percentual' => ''],
                ['tipo_operacao' => 'pix', 'percentual' => ''],
            ],
        ]);

        $this->post('/enviar-proposta', $dados)->assertSessionHasErrors('taxas');
        $this->assertDatabaseCount('propostas_recebidas', 0);
    }

    public function test_anexo_imagem_e_convertido_para_webp_em_disco_privado(): void
    {
        Storage::fake('local');
        $marca = $this->criarMarca();

        $arquivo = UploadedFile::fake()->image('proposta.jpg', 100, 100);

        $this->post('/enviar-proposta', $this->dadosValidos($marca, ['anexo' => $arquivo]))
            ->assertRedirect();

        $proposta = PropostaRecebida::first();
        $this->assertNotNull($proposta->anexo_caminho);
        $this->assertStringEndsWith('.webp', $proposta->anexo_caminho);
        $this->assertSame('image/webp', $proposta->anexo_mime);
        Storage::disk('local')->assertExists($proposta->anexo_caminho);
    }

    public function test_anexo_pdf_e_aceito(): void
    {
        Storage::fake('local');
        $marca = $this->criarMarca();

        // finfo confere os bytes reais (regra de AnexoDeProposta), então o
        // fake precisa de um cabeçalho de PDF de verdade — create() do
        // Laravel só recheia o arquivo de bytes nulos.
        $arquivo = UploadedFile::fake()->createWithContent('proposta.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $this->post('/enviar-proposta', $this->dadosValidos($marca, ['anexo' => $arquivo]))
            ->assertRedirect();

        $proposta = PropostaRecebida::first();
        $this->assertStringEndsWith('.pdf', $proposta->anexo_caminho);
        $this->assertSame('application/pdf', $proposta->anexo_mime);
    }

    public function test_anexo_de_tipo_nao_permitido_e_rejeitado(): void
    {
        $marca = $this->criarMarca();

        $arquivo = UploadedFile::fake()->createWithContent('proposta.txt', 'apenas um arquivo de texto qualquer');

        $this->post('/enviar-proposta', $this->dadosValidos($marca, ['anexo' => $arquivo]))
            ->assertSessionHasErrors('anexo');

        $this->assertDatabaseCount('propostas_recebidas', 0);
    }
}

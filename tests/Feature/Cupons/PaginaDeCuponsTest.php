<?php

namespace Tests\Feature\Cupons;

use App\Enums\IncideSobre;
use App\Enums\StatusItem;
use App\Enums\StatusMarca;
use App\Enums\TipoDesconto;
use App\Enums\TipoEnquadramento;
use App\Models\Adquirente;
use App\Models\Cupom;
use App\Models\Equipamento;
use App\Models\Marca;
use App\Models\Plano;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A área de cupons da etapa 09: a listagem consolidada (/cupons) e a página
 * de cada marca (/cupom/{slug}).
 */
class PaginaDeCuponsTest extends TestCase
{
    use RefreshDatabase;

    private function criarMarcaAtiva(string $nome, string $slug): Marca
    {
        $adquirente = Adquirente::create(['nome' => "Adquirente {$slug}", 'slug' => "adquirente-{$slug}"]);

        return Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => $nome,
            'slug' => $slug,
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);
    }

    public function test_marca_sem_cupom_vigente_nem_aparece(): void
    {
        $this->criarMarcaAtiva('Sem Cupom Nenhum', 'sem-cupom-nenhum');

        $html = $this->get('/cupons')->assertOk()->getContent();

        $this->assertStringContainsString('Nenhum cupom vigente', $html);
        $this->assertStringNotContainsString('Sem Cupom Nenhum', $html);
    }

    public function test_ordena_por_maior_desconto_em_reais_e_cupom_sem_preco_para_calcular_fica_por_ultimo(): void
    {
        $alfa = $this->criarMarcaAtiva('Alfa', 'alfa');
        Cupom::create([
            'marca_id' => $alfa->id,
            'codigo' => 'ALFA100',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 100,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://exemplo.test/alfa',
            'status' => StatusItem::Ativo,
        ]);

        // Beta: 10% sobre uma adesão de R$ 200 verificada = R$ 20 de economia.
        $beta = $this->criarMarcaAtiva('Beta', 'beta');
        $planoBeta = Plano::create([
            'marca_id' => $beta->id,
            'nome' => 'Único',
            'slug' => 'unico-beta',
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'status' => StatusItem::Ativo,
        ]);
        $equipamentoBeta = Equipamento::create([
            'marca_id' => $beta->id,
            'nome' => 'Maquininha Beta',
            'slug' => 'maquininha-beta',
            'tipo' => 'smart',
            'tem_chip_gratis' => true,
            'aceita_nfc' => true,
            'imprime_comprovante' => true,
            'status' => StatusItem::Ativo,
        ]);
        $planoBeta->equipamentos()->attach($equipamentoBeta->id, [
            'preco_adesao' => 200,
            'status' => StatusItem::Ativo->value,
        ]);
        Cupom::create([
            'marca_id' => $beta->id,
            'codigo' => 'BETA10',
            'tipo_desconto' => TipoDesconto::Percentual,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 10,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://exemplo.test/beta',
            'status' => StatusItem::Ativo,
        ]);

        // Gama: percentual, mas a marca não tem preço de adesão verificado em
        // lugar nenhum — não dá para saber quanto isso vale em reais.
        $gama = $this->criarMarcaAtiva('Gama', 'gama');
        Cupom::create([
            'marca_id' => $gama->id,
            'codigo' => 'GAMA5',
            'tipo_desconto' => TipoDesconto::Percentual,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 5,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://exemplo.test/gama',
            'status' => StatusItem::Ativo,
        ]);

        $html = $this->get('/cupons')->assertOk()->getContent();

        $posAlfa = mb_strpos($html, 'ALFA100');
        $posBeta = mb_strpos($html, 'BETA10');
        $posGama = mb_strpos($html, 'GAMA5');

        $this->assertNotFalse($posAlfa);
        $this->assertNotFalse($posBeta);
        $this->assertNotFalse($posGama);
        $this->assertTrue($posAlfa < $posBeta, 'R$ 100 deveria vir antes de R$ 20.');
        $this->assertTrue($posBeta < $posGama, 'Cupom sem preço para calcular deveria ficar por último.');
    }

    public function test_cupom_vencido_some_da_listagem_e_a_pagina_da_marca_da_404(): void
    {
        $marca = $this->criarMarcaAtiva('Marca Vencida', 'marca-vencida');
        Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'VENCIDO',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 30,
            'valido_de' => Carbon::today()->subDays(60),
            'valido_ate' => Carbon::today()->subDay(),
            'link_afiliado' => 'https://exemplo.test/vencido',
            'status' => StatusItem::Ativo,
        ]);

        $this->get('/cupons')->assertOk()->assertDontSee('VENCIDO')->assertDontSee('Marca Vencida');
        $this->get('/cupom/marca-vencida')->assertNotFound();
    }

    public function test_marca_sem_cupom_e_marca_inexistente_dao_404_na_pagina_do_cupom(): void
    {
        $this->criarMarcaAtiva('Marca Zero Cupom', 'marca-zero-cupom');

        $this->get('/cupom/marca-zero-cupom')->assertNotFound();
        $this->get('/cupom/marca-que-nao-existe')->assertNotFound();
    }

    public function test_pagina_do_cupom_traz_o_passo_a_passo_o_link_para_a_marca_e_o_aviso_das_taxas(): void
    {
        $marca = $this->criarMarcaAtiva('Marca Passo', 'marca-passo');
        Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'PASSO20',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 20,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://exemplo.test/passo',
            'termos' => 'Válido só na primeira adesão.',
            'status' => StatusItem::Ativo,
        ]);

        $html = $this->get('/cupom/marca-passo')->assertOk()->getContent();

        $this->assertStringContainsString('Cupom Marca Passo', $html);
        $this->assertStringContainsString('Como usar', $html);
        $this->assertStringContainsString('PASSO20', $html);
        $this->assertStringContainsString('Válido só na primeira adesão.', $html);
        $this->assertStringContainsString(route('maquininhas.show', 'marca-passo'), $html);

        // Pedido explícito: a frase precisa sair exatamente assim.
        $this->assertStringContainsString(
            'As taxas exibidas aqui são exatamente as mesmas do site oficial de cada marca. '
            .'Nosso link não altera sua taxa — só acrescenta desconto na adesão.',
            $html
        );

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/cupom/marca-passo').'">', $html);
        $this->assertStringContainsString('"@type":"Offer"', $html);
    }

    public function test_listagem_traz_o_aviso_das_taxas_e_dados_estruturados(): void
    {
        $marca = $this->criarMarcaAtiva('Marca Listada', 'marca-listada');
        Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'LISTA10',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 10,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://exemplo.test/lista',
            'status' => StatusItem::Ativo,
        ]);

        $html = $this->get('/cupons')->assertOk()->getContent();

        $this->assertStringContainsString(
            'As taxas exibidas aqui são exatamente as mesmas do site oficial de cada marca. '
            .'Nosso link não altera sua taxa — só acrescenta desconto na adesão.',
            $html
        );
        $this->assertStringContainsString('"@type":"ItemList"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/cupons').'">', $html);
    }

    public function test_sitemap_so_lista_cupom_de_marca_com_cupom_vigente(): void
    {
        $comCupom = $this->criarMarcaAtiva('Com Cupom', 'com-cupom');
        Cupom::create([
            'marca_id' => $comCupom->id,
            'codigo' => 'SITEMAP10',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 10,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://exemplo.test/sitemap',
            'status' => StatusItem::Ativo,
        ]);
        $this->criarMarcaAtiva('Sem Cupom', 'sem-cupom');

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/cupons').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/cupom/com-cupom').'</loc>', $xml);
        $this->assertStringNotContainsString('/cupom/sem-cupom', $xml);
    }

    public function test_o_menu_liga_para_cupons(): void
    {
        $this->get('/cupons')->assertOk()->assertSee('Comparador')->assertSee('Marcas');
        $this->get('/')->assertOk()->assertSee('Cupons')->assertSee(route('cupons.index'), escape: false);
    }
}

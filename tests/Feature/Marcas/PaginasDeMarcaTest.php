<?php

namespace Tests\Feature\Marcas;

use App\Enums\FonteTipo;
use App\Enums\IncideSobre;
use App\Enums\StatusItem;
use App\Enums\StatusMarca;
use App\Enums\StatusPublicacao;
use App\Enums\TipoDesconto;
use App\Enums\TipoOperacao;
use App\Models\Adquirente;
use App\Models\Cupom;
use App\Models\Equipamento;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DimensoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A listagem (/maquininhas) e a página individual (/maquininha/{slug}) da
 * etapa 08 — o que elas não podem quebrar em silêncio.
 *
 * Usa duas fontes de dado: a carga real via DatabaseSeeder (para conferir o
 * estado honesto de hoje — tudo em rascunho, regra 10) e uma marca sintética
 * própria, com taxa publicada, cupom e nota do Reclame Aqui, para cobrir o
 * caminho feliz que a carga real ainda não sustenta.
 */
class PaginasDeMarcaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Revisão de 17/09/2026: a trava de aprovação (etapa 19) deixou de abrir
     * exceção para "sem dado publicado" — nenhuma marca aparece em lugar
     * nenhum sem `aprovada_em`, mesmo a que só tem rascunho. A carga real via
     * `DatabaseSeeder` não aprova ninguém (regra 10: aprovação é ato humano,
     * não efeito colateral de rodar seeder), então a listagem tem de mostrar
     * o estado vazio até alguém aprovar pelo painel.
     */
    public function test_a_listagem_sem_nenhuma_marca_aprovada_mostra_o_estado_vazio(): void
    {
        $this->seed(DatabaseSeeder::class);

        $html = $this->get('/maquininhas')
            ->assertOk()
            ->assertSee('Maquininhas de cartão, marca por marca')
            ->assertDontSee('PagBank')
            ->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/maquininhas').'">', $html);
        $this->assertStringContainsString('Nenhuma marca ativa no momento.', $html);
    }

    /**
     * Uma vez aprovada — mesmo com taxa ainda em rascunho, que é o estado
     * real da carga hoje — a marca aparece, e regra 10 continua valendo: sem
     * dado publicado, a tela diz isso em vez de inventar um número.
     */
    public function test_a_listagem_mostra_marca_aprovada_mesmo_com_taxa_em_rascunho(): void
    {
        $this->seed(DatabaseSeeder::class);
        Marca::where('slug', 'pagbank')->update(['aprovada_em' => now()]);

        $html = $this->get('/maquininhas')
            ->assertOk()
            ->assertSee('PagBank')
            ->assertSee('data-marca-checkbox', escape: false)
            ->assertSee('value="pagbank"', escape: false)
            ->getContent();

        $this->assertStringContainsString('"@type":"ItemList"', $html);

        // Regra 10: a taxa dela ainda está em rascunho, então não há
        // mensalidade nem taxa publicada para mostrar — a tela precisa dizer
        // isso, não inventar um número.
        $this->assertStringContainsString('Sem dado publicado', $html);
        $this->assertStringContainsString('Não informado', $html);
    }

    public function test_a_pagina_individual_traz_as_oito_secoes_na_ordem(): void
    {
        $this->seed(DatabaseSeeder::class);
        Marca::where('slug', 'pagbank')->update(['aprovada_em' => now()]);

        $html = $this->get('/maquininha/pagbank')->assertOk()->getContent();

        $posicoes = [];

        foreach ([
            'Sobre a PagBank',
            'Tabela de taxas',
            'Modelos de maquininha',
            'Bandeiras aceitas',
            'Vantagens e características',
            'Pronto para contratar',
        ] as $secao) {
            $posicao = mb_strpos($html, $secao);
            $this->assertNotFalse($posicao, "A seção \"{$secao}\" sumiu da página.");
            $posicoes[] = $posicao;
        }

        $ordenadas = $posicoes;
        sort($ordenadas);
        $this->assertSame($ordenadas, $posicoes, 'As seções saíram fora de ordem.');

        // Sem nota manual cadastrada, regra 8 exige silêncio — nunca um "0/10".
        $this->assertStringNotContainsString('Reclame Aqui', $html);
    }

    public function test_marca_sem_slug_correspondente_da_404(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/maquininha/marca-que-nao-existe')->assertNotFound();
    }

    public function test_marca_pausada_nao_tem_pagina_publica(): void
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente X', 'slug' => 'adquirente-x']);
        Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Pausada Ltda',
            'slug' => 'pausada-ltda',
            'publica_tabela' => true,
            'status' => StatusMarca::Pausada,
        ]);

        $this->get('/maquininha/pausada-ltda')->assertNotFound();
        $this->get('/maquininhas')->assertOk()->assertDontSee('Pausada Ltda');
    }

    /**
     * O caminho feliz que a carga real (etapa 04) ainda não sustenta:
     * cupom vigente, nota do Reclame Aqui, taxa publicada com selo de
     * frescor por linha, e vídeo do canal.
     */
    public function test_pagina_completa_com_cupom_nota_e_taxa_publicada(): void
    {
        $this->seed(DimensoesSeeder::class);

        $adquirente = Adquirente::create(['nome' => 'Adquirente Amostra', 'slug' => 'adquirente-amostra']);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Completa',
            'slug' => 'marca-completa',
            'site_url' => 'https://exemplo.test',
            'descricao' => 'Descrição de teste da marca completa.',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'reclame_aqui_nota' => 7.8,
            'reclame_aqui_url' => 'https://reclameaqui.test/marca-completa',
            'reclame_aqui_consultado_em' => Carbon::create(2026, 8, 1),
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
            // Etapa 19: a trava de aprovacao so deixa a pagina publica no ar
            // com isto preenchido - o proprio nome do teste ("marca
            // completa") ja diz que ela deveria passar.
            'aprovada_em' => now(),
        ]);

        $plano = Plano::create([
            'marca_id' => $marca->id,
            'nome' => 'Plano Único',
            'slug' => 'plano-unico',
            'tipo_enquadramento' => 'automatico',
            'mensalidade' => 0,
            'status' => StatusItem::Ativo,
        ]);

        $equipamento = Equipamento::create([
            'marca_id' => $marca->id,
            'nome' => 'Maquininha X',
            'slug' => 'maquininha-x',
            'tipo' => 'smart',
            'tem_chip_gratis' => true,
            'aceita_nfc' => true,
            'imprime_comprovante' => true,
            'status' => StatusItem::Ativo,
        ]);

        $plano->equipamentos()->attach($equipamento->id, [
            'preco_adesao' => 200.00,
            'parcelas_adesao' => 12,
            'status' => StatusItem::Ativo->value,
        ]);

        TaxaDivulgada::create([
            'plano_id' => $plano->id,
            'grupo_bandeira_id' => GrupoBandeira::where('codigo', GrupoBandeira::VISA_MASTER)->value('id'),
            'prazo_recebimento_id' => PrazoRecebimento::where('codigo', PrazoRecebimento::D30)->value('id'),
            'tipo_operacao' => TipoOperacao::CreditoAvista,
            'parcelas' => 1,
            'percentual' => 3.15,
            'url_fonte' => 'https://exemplo.test/taxas',
            'fonte_tipo' => FonteTipo::SiteOficial,
            'data_verificacao' => Carbon::today()->subDays(5),
            'status' => StatusPublicacao::Publicado,
        ]);

        $cupom = Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'COMPLETA10',
            'tipo_desconto' => TipoDesconto::Percentual,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 10,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(30),
            'link_afiliado' => 'https://exemplo.test/afiliado',
            'status' => StatusItem::Ativo,
        ]);

        $html = $this->get('/maquininha/marca-completa')->assertOk()->getContent();

        // Regra 11: nota com uma casa, data em pt-BR.
        $this->assertStringContainsString('7,8', $html);
        $this->assertStringContainsString('01/08/2026', $html);
        $this->assertStringContainsString('reclameaqui.test/marca-completa', $html);

        // Taxa publicada, com o selo de frescor colado na própria linha.
        $this->assertStringContainsString('3,15%', $html);
        $this->assertStringContainsString(Carbon::today()->subDays(5)->format('d/m/Y'), $html);

        // Cupom em destaque, com a economia em reais calculada sobre a adesão
        // verificada (10% de R$ 200,00 = R$ 20,00) — nunca um percentual solto.
        $this->assertStringContainsString('COMPLETA10', $html);
        $this->assertStringContainsString('R$ 20,00', $html);
        $this->assertStringContainsString('Maquininha X', $html);

        // Vídeo embutido só quando o campo está preenchido.
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);

        // Dados estruturados: Product com Review, porque a nota existe.
        $this->assertStringContainsString('"@type":"Product"', $html);
        $this->assertStringContainsString('"@type":"Review"', $html);
        $this->assertStringContainsString('"ratingValue":7.8', $html);

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/maquininha/marca-completa').'">', $html);
    }

    public function test_cupom_vencido_nao_aparece_e_cta_cai_para_o_site_oficial(): void
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Y', 'slug' => 'adquirente-y']);
        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Sem Cupom',
            'slug' => 'marca-sem-cupom',
            'site_url' => 'https://exemplo.test/oficial',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
            'aprovada_em' => now(),
        ]);

        Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'VENCIDO',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 30,
            'valido_de' => Carbon::today()->subDays(60),
            'valido_ate' => Carbon::today()->subDay(),
            'link_afiliado' => 'https://exemplo.test/afiliado-vencido',
            'status' => StatusItem::Ativo,
        ]);

        $html = $this->get('/maquininha/marca-sem-cupom')->assertOk()->getContent();

        $this->assertStringNotContainsString('VENCIDO', $html);
        $this->assertStringContainsString('Não há cupom vigente', $html);
        $this->assertStringContainsString('https://exemplo.test/oficial', $html);
    }

    public function test_sitemap_lista_home_listagem_e_so_marcas_ativas(): void
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Z', 'slug' => 'adquirente-z']);
        Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Ativa',
            'slug' => 'marca-ativa',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
            'aprovada_em' => now(),
        ]);
        Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Descontinuada',
            'slug' => 'marca-descontinuada',
            'publica_tabela' => true,
            'status' => StatusMarca::Descontinuada,
            'aprovada_em' => now(),
        ]);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/maquininhas').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/maquininha/marca-ativa').'</loc>', $xml);
        $this->assertStringNotContainsString('marca-descontinuada', $xml);
    }

    public function test_o_menu_liga_comparador_e_marcas_nos_dois_sentidos(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()->assertSee('Marcas')->assertSee(route('maquininhas.index'), escape: false);
        $this->get('/maquininhas')->assertOk()->assertSee('Comparador')->assertSee(route('comparador'), escape: false);
    }
}

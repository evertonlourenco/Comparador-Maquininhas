<?php

namespace Tests\Feature\ImagensExternas;

use App\Support\ImagensExternas\BuscaDeImagemExterna;
use App\Support\Uploads\ImagemSeguraWebp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Etapa 15: fim a fim da busca de imagem por URL — o servidor buscando um
 * endereco que o admin digitou. Todo host de teste usa um resolvedor de DNS
 * injetado (nunca o real): as respostas de rede vem de Http::fake(), nunca
 * da internet de verdade.
 */
class BuscaDeImagemExternaTest extends TestCase
{
    /** IP de documentacao (RFC 5737) — nao e privado nem reservado, serve de "IP publico" estavel nos testes. */
    private const IP_PUBLICO_DE_TESTE = '203.0.113.10';

    private function buscador(): BuscaDeImagemExterna
    {
        return new BuscaDeImagemExterna(fn (string $host): array => [self::IP_PUBLICO_DE_TESTE]);
    }

    private function pngValido(): string
    {
        $imagem = imagecreatetruecolor(4, 4);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 200, 50, 10));
        ob_start();
        imagepng($imagem);
        $bytes = ob_get_clean();
        imagedestroy($imagem);

        return $bytes;
    }

    public function test_esquema_diferente_de_http_https_e_recusado_sem_fazer_nenhuma_requisicao(): void
    {
        Http::fake();

        $resultado = $this->buscador()->buscar('ftp://cdn.exemplo.test/logo.png');

        $this->assertFalse($resultado->sucesso);
        Http::assertNothingSent();
    }

    public function test_host_literal_em_ip_privado_e_recusado_sem_fazer_nenhuma_requisicao(): void
    {
        Http::fake();

        $resultado = $this->buscador()->buscar('http://127.0.0.1/logo.png');

        $this->assertFalse($resultado->sucesso);
        $this->assertStringContainsString('bloqueado por segurança', $resultado->erro);
        Http::assertNothingSent();
    }

    public function test_host_literal_no_metadado_de_nuvem_e_recusado(): void
    {
        Http::fake();

        $resultado = $this->buscador()->buscar('http://169.254.169.254/latest/meta-data/');

        $this->assertFalse($resultado->sucesso);
        Http::assertNothingSent();
    }

    public function test_hostname_cujo_dns_aponta_para_rede_privada_e_recusado_sem_requisicao(): void
    {
        Http::fake();

        $buscador = new BuscaDeImagemExterna(fn (string $host): array => ['10.0.0.5']);
        $resultado = $buscador->buscar('http://interno.exemplo.test/logo.png');

        $this->assertFalse($resultado->sucesso);
        Http::assertNothingSent();
    }

    public function test_redirecionamento_para_ip_privado_e_bloqueado_no_segundo_salto(): void
    {
        Http::fake([
            'cdn.exemplo.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);

        $resultado = $this->buscador()->buscar('http://cdn.exemplo.test/vai-redirecionar');

        $this->assertFalse($resultado->sucesso);
    }

    public function test_url_que_ja_e_imagem_direta_e_aceita_e_convertida(): void
    {
        Http::fake([
            'cdn.exemplo.test/*' => Http::response($this->pngValido(), 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = $this->buscador()->buscar('https://cdn.exemplo.test/logo.png');

        $this->assertTrue($resultado->sucesso, $resultado->erro ?? '');
        $this->assertSame('https://cdn.exemplo.test/logo.png', $resultado->encontradaEm);
        $this->assertSame('image/webp', ImagemSeguraWebp::tipoRealDosBytes(base64_decode($resultado->webpBase64)));
    }

    public function test_content_type_de_imagem_mas_bytes_que_nao_decodificam_e_recusado(): void
    {
        Http::fake([
            'cdn.exemplo.test/*' => Http::response('isto diz que e imagem mas não é', 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = $this->buscador()->buscar('https://cdn.exemplo.test/logo-falso.png');

        $this->assertFalse($resultado->sucesso);
        $this->assertStringContainsString('formato aceito', $resultado->erro);
    }

    public function test_pagina_html_sem_og_image_nem_icone_nao_encontra_candidata(): void
    {
        Http::fake([
            'exemplo.test/*' => Http::response('<html><head><title>Sem imagem</title></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $resultado = $this->buscador()->buscar('https://exemplo.test/imprensa');

        $this->assertFalse($resultado->sucesso);
        $this->assertStringContainsString('Não encontramos', $resultado->erro);
    }

    public function test_pagina_html_com_conteudo_que_nao_e_imagem_nem_html_e_recusada(): void
    {
        Http::fake([
            'exemplo.test/*' => Http::response('%PDF-1.4 conteúdo', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $resultado = $this->buscador()->buscar('https://exemplo.test/kit.pdf');

        $this->assertFalse($resultado->sucesso);
    }

    public function test_og_image_e_encontrada_baixada_e_convertida(): void
    {
        Http::fake([
            'exemplo.test/imprensa' => Http::response(
                '<html><head><meta property="og:image" content="https://cdn.exemplo.test/marca.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'cdn.exemplo.test/*' => Http::response($this->pngValido(), 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = $this->buscador()->buscar('https://exemplo.test/imprensa');

        $this->assertTrue($resultado->sucesso, $resultado->erro ?? '');
        $this->assertSame('https://cdn.exemplo.test/marca.png', $resultado->encontradaEm);
    }

    public function test_og_image_relativa_e_resolvida_contra_a_pagina(): void
    {
        Http::fake([
            'exemplo.test/imprensa' => Http::response(
                '<html><head><meta property="og:image" content="/assets/marca.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'exemplo.test/assets/*' => Http::response($this->pngValido(), 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = $this->buscador()->buscar('https://exemplo.test/imprensa');

        $this->assertTrue($resultado->sucesso, $resultado->erro ?? '');
        $this->assertSame('https://exemplo.test/assets/marca.png', $resultado->encontradaEm);
    }

    public function test_apple_touch_icon_e_usado_quando_nao_ha_og_image(): void
    {
        Http::fake([
            'exemplo.test/imprensa' => Http::response(
                '<html><head>'
                .'<link rel="apple-touch-icon" sizes="57x57" href="https://cdn.exemplo.test/icone-57.png">'
                .'<link rel="apple-touch-icon" sizes="180x180" href="https://cdn.exemplo.test/icone-180.png">'
                .'</head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'cdn.exemplo.test/*' => Http::response($this->pngValido(), 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = $this->buscador()->buscar('https://exemplo.test/imprensa');

        $this->assertTrue($resultado->sucesso, $resultado->erro ?? '');
        // O maior apple-touch-icon (pelo atributo sizes) ganha dos menores.
        $this->assertSame('https://cdn.exemplo.test/icone-180.png', $resultado->encontradaEm);
    }

    public function test_link_icon_e_o_ultimo_recurso(): void
    {
        Http::fake([
            'exemplo.test/imprensa' => Http::response(
                '<html><head><link rel="icon" href="https://cdn.exemplo.test/favicon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'cdn.exemplo.test/*' => Http::response($this->pngValido(), 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = $this->buscador()->buscar('https://exemplo.test/imprensa');

        $this->assertTrue($resultado->sucesso, $resultado->erro ?? '');
        $this->assertSame('https://cdn.exemplo.test/favicon.png', $resultado->encontradaEm);
    }

    public function test_arquivo_maior_que_o_limite_e_recusado(): void
    {
        $bytesGrandes = str_repeat('a', 6 * 1024 * 1024);

        Http::fake([
            'cdn.exemplo.test/*' => Http::response($bytesGrandes, 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = $this->buscador()->buscar('https://cdn.exemplo.test/gigante.png');

        $this->assertFalse($resultado->sucesso);
        $this->assertStringContainsString('5 MB', $resultado->erro);
    }

    public function test_resposta_de_erro_http_e_recusada(): void
    {
        Http::fake([
            'cdn.exemplo.test/*' => Http::response('não encontrado', 404),
        ]);

        $resultado = $this->buscador()->buscar('https://cdn.exemplo.test/nao-existe.png');

        $this->assertFalse($resultado->sucesso);
    }

    public function test_redirecionamentos_demais_param_por_seguranca(): void
    {
        Http::fake([
            'passo1.exemplo.test/*' => Http::response('', 302, ['Location' => 'https://passo2.exemplo.test/x']),
            'passo2.exemplo.test/*' => Http::response('', 302, ['Location' => 'https://passo3.exemplo.test/x']),
            'passo3.exemplo.test/*' => Http::response('', 302, ['Location' => 'https://passo4.exemplo.test/x']),
            'passo4.exemplo.test/*' => Http::response('', 302, ['Location' => 'https://passo5.exemplo.test/x']),
            'passo5.exemplo.test/*' => Http::response($this->pngValido(), 200, ['Content-Type' => 'image/png']),
        ]);

        $resultado = $this->buscador()->buscar('https://passo1.exemplo.test/x');

        $this->assertFalse($resultado->sucesso);
        $this->assertStringContainsString('demais', $resultado->erro);
    }
}

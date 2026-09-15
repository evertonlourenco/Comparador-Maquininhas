<?php

namespace Tests\Unit\Support\ImagensExternas;

use App\Support\ImagensExternas\UrlExternaSegura;
use PHPUnit\Framework\TestCase;

/**
 * Etapa 15: a busca de imagem por URL e uma requisicao do servidor para um
 * endereco que o admin digita — risco de SSRF classico. Este teste cobre so
 * a decisao "esse IP/URL pode ser buscado?", sem rede nenhuma: e por isso
 * que ele estende o TestCase puro do PHPUnit, nao o da aplicacao.
 */
class UrlExternaSeguraTest extends TestCase
{
    public function test_so_http_e_https_sao_permitidos(): void
    {
        $this->assertTrue(UrlExternaSegura::esquemaPermitido('http://exemplo.com/logo.png'));
        $this->assertTrue(UrlExternaSegura::esquemaPermitido('https://exemplo.com/logo.png'));

        $this->assertFalse(UrlExternaSegura::esquemaPermitido('ftp://exemplo.com/logo.png'));
        $this->assertFalse(UrlExternaSegura::esquemaPermitido('file:///etc/passwd'));
        $this->assertFalse(UrlExternaSegura::esquemaPermitido('data:image/png;base64,abc'));
        $this->assertFalse(UrlExternaSegura::esquemaPermitido('gopher://exemplo.com'));
        $this->assertFalse(UrlExternaSegura::esquemaPermitido('não é uma url'));
    }

    /** @return array<string, array{0: string}> */
    public static function ipsBloqueados(): array
    {
        return [
            'loopback v4' => ['127.0.0.1'],
            'rfc1918 classe A' => ['10.0.0.5'],
            'rfc1918 classe B' => ['172.16.0.5'],
            'rfc1918 classe B, topo' => ['172.31.255.255'],
            'rfc1918 classe C' => ['192.168.1.1'],
            'link-local v4 (metadado de nuvem)' => ['169.254.169.254'],
            'endereco nao especificado' => ['0.0.0.0'],
            'loopback v6' => ['::1'],
            'ula v6' => ['fc00::1'],
            'link-local v6' => ['fe80::1'],
            'cgnat (rfc 6598)' => ['100.64.0.1'],
            'multicast' => ['224.0.0.1'],
            'broadcast limitado' => ['255.255.255.255'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ipsBloqueados')]
    public function test_ips_privados_reservados_e_de_infraestrutura_sao_bloqueados(string $ip): void
    {
        $this->assertTrue(UrlExternaSegura::ipBloqueado($ip), "Esperava {$ip} bloqueado.");
    }

    /** @return array<string, array{0: string}> */
    public static function ipsPermitidos(): array
    {
        return [
            'dns publico' => ['8.8.8.8'],
            'outro dns publico' => ['1.1.1.1'],
            'faixa de documentacao (nao e privada nem reservada)' => ['203.0.113.10'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ipsPermitidos')]
    public function test_ips_publicos_nao_sao_bloqueados(string $ip): void
    {
        $this->assertFalse(UrlExternaSegura::ipBloqueado($ip), "Esperava {$ip} permitido.");
    }

    public function test_host_literal_em_ip_privado_e_recusado_sem_precisar_de_dns(): void
    {
        $this->assertNull(UrlExternaSegura::resolverIpPermitido('127.0.0.1'));
        $this->assertNull(UrlExternaSegura::resolverIpPermitido('169.254.169.254'));
        // parse_url() devolve o host de IPv6 sem colchetes — e assim que
        // BuscaDeImagemExterna passa o host adiante.
        $this->assertNull(UrlExternaSegura::resolverIpPermitido('::1'));
    }

    public function test_host_literal_em_ip_publico_e_aceito(): void
    {
        $this->assertSame('203.0.113.10', UrlExternaSegura::resolverIpPermitido('203.0.113.10'));
    }

    public function test_hostname_que_resolve_para_ip_privado_e_bloqueado_mesmo_via_dns(): void
    {
        $resolvedorMalicioso = fn (string $host): array => ['127.0.0.1'];

        $this->assertNull(UrlExternaSegura::resolverIpPermitido('evil.exemplo.test', $resolvedorMalicioso));
    }

    public function test_hostname_cujo_dns_devolve_qualquer_ip_bloqueado_entre_varios_e_recusado(): void
    {
        // DNS com respostas mistas: um IP publico e um privado. Aceitar so o
        // publico abriria a porta para um servidor escolher qual IP usar na
        // hora da conexao (rebinding). A regra e recusar se qualquer um dos
        // resolvidos for bloqueado.
        $resolvedorMisto = fn (string $host): array => ['203.0.113.10', '10.0.0.5'];

        $this->assertNull(UrlExternaSegura::resolverIpPermitido('misto.exemplo.test', $resolvedorMisto));
    }

    public function test_hostname_que_resolve_para_ip_publico_e_aceito_e_pinavel(): void
    {
        $resolvedorSeguro = fn (string $host): array => ['203.0.113.10'];

        $this->assertSame('203.0.113.10', UrlExternaSegura::resolverIpPermitido('cdn.exemplo.test', $resolvedorSeguro));
    }

    public function test_hostname_sem_resposta_de_dns_e_recusado(): void
    {
        $resolvedorVazio = fn (string $host): array => [];

        $this->assertNull(UrlExternaSegura::resolverIpPermitido('nao-existe.exemplo.test', $resolvedorVazio));
    }
}

<?php

namespace App\Support\ImagensExternas;

use Closure;

/**
 * Etapa 15: a busca de imagem por URL e uma requisicao do servidor para um
 * endereco digitado pelo admin — risco de SSRF classico. Esta classe so
 * decide "esse endereco pode ser buscado?"; quem busca de fato e
 * BuscaDeImagemExterna.
 *
 * A checagem de IP usa FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE do
 * proprio PHP — cobre RFC 1918, loopback (127.0.0.0/8 e ::1), link-local
 * (169.254.0.0/16, o range do metadado de nuvem, e fe80::/10) e ULA IPv6
 * (fc00::/7) sem reinventar tabela de faixa de IP. Os CIDRs extras cobrem o
 * que esses dois flags deixam passar: CGNAT (RFC 6598, usado por metadado de
 * nuvem em algumas plataformas) e multicast.
 */
final class UrlExternaSegura
{
    private const CIDRS_EXTRAS_V4 = [
        '100.64.0.0/10',
        '224.0.0.0/4',
        '255.255.255.255/32',
    ];

    public static function esquemaPermitido(string $url): bool
    {
        $esquema = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

        return in_array($esquema, ['http', 'https'], true);
    }

    public static function ipBloqueado(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        foreach (self::CIDRS_EXTRAS_V4 as $cidr) {
            if (self::ipDentroDoCidrV4($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private static function ipDentroDoCidrV4(string $ip, string $cidr): bool
    {
        [$sub, $bits] = explode('/', $cidr);
        $mascara = $bits === '0' ? 0 : (~0 << (32 - (int) $bits));

        return (ip2long($ip) & $mascara) === (ip2long($sub) & $mascara);
    }

    /**
     * Resolve o host para um IP permitido e pinavel na requisicao real —
     * assim a conexao usa exatamente o IP que foi checado aqui, e nao um
     * outro que o DNS passe a responder entre a checagem e a conexao
     * (rebinding). Um host literal em IP nao passa por DNS nenhum.
     *
     * @param  Closure(string): list<string>|null  $resolvedor  So para teste:
     *         recebe o host e devolve a lista de IPs, no lugar do DNS real.
     */
    public static function resolverIpPermitido(string $host, ?Closure $resolvedor = null): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::ipBloqueado($host) ? null : $host;
        }

        $ips = $resolvedor !== null ? $resolvedor($host) : self::resolverViaDns($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (self::ipBloqueado($ip)) {
                return null;
            }
        }

        return $ips[0];
    }

    /** @return list<string> */
    private static function resolverViaDns(string $host): array
    {
        $registros = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($registros === false) {
            return [];
        }

        $ips = [];

        foreach ($registros as $registro) {
            $ip = $registro['ip'] ?? $registro['ipv6'] ?? null;

            if ($ip !== null) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }
}

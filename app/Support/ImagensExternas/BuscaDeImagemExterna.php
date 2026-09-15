<?php

namespace App\Support\ImagensExternas;

use App\Support\Uploads\ImagemSeguraWebp;
use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Etapa 15: busca uma imagem a partir de uma URL colada pelo admin — kit de
 * midia, pagina de imprensa, ou a propria pagina do produto. So devolve
 * bytes em memoria (ResultadoDaBusca), nunca grava nada: quem decide se o
 * candidato vira logo/foto e o admin, na tela de aprovacao (regra 10).
 *
 * Cada hop (a URL de entrada, um redirecionamento, ou a imagem extraida de
 * um og:image) passa pela mesma validacao de SSRF — resolver o alvo do
 * redirecionamento e nao revalida-lo seria abrir a porta que o proprio
 * bloqueio fecha.
 */
final class BuscaDeImagemExterna
{
    private const TAMANHO_MAXIMO_BYTES = 5 * 1024 * 1024;

    private const TIMEOUT_SEGUNDOS = 6;

    private const CONEXAO_TIMEOUT_SEGUNDOS = 3;

    private const HOPS_MAXIMOS = 3;

    /** @param  Closure(string): list<string>|null  $resolvedorDns  So para teste — ver UrlExternaSegura::resolverIpPermitido(). */
    public function __construct(private readonly ?Closure $resolvedorDns = null) {}

    public function buscar(string $url): ResultadoDaBusca
    {
        return $this->buscarComProfundidade($url, 0);
    }

    private function buscarComProfundidade(string $url, int $profundidade): ResultadoDaBusca
    {
        if ($profundidade > self::HOPS_MAXIMOS) {
            return ResultadoDaBusca::comErro('A busca seguiu redirecionamentos ou links demais e parou por segurança.');
        }

        $requisicao = $this->prepararRequisicaoSegura($url);

        if ($requisicao instanceof ResultadoDaBusca) {
            return $requisicao;
        }

        [$host, $porta, $ip] = $requisicao;

        try {
            $resposta = Http::withOptions([
                'curl' => [CURLOPT_RESOLVE => ["{$host}:{$porta}:{$ip}"]],
            ])
                ->connectTimeout(self::CONEXAO_TIMEOUT_SEGUNDOS)
                ->timeout(self::TIMEOUT_SEGUNDOS)
                ->withoutRedirecting()
                ->withUserAgent('MaquinaCertaBot/1.0 (+https://maquinacerta.com.br)')
                ->get($url);
        } catch (Throwable) {
            return ResultadoDaBusca::comErro('Não foi possível buscar essa URL: a conexão falhou ou expirou.');
        }

        if (in_array($resposta->status(), [301, 302, 303, 307, 308], true)) {
            $local = $resposta->header('Location');

            if (! $local) {
                return ResultadoDaBusca::comErro('A URL redirecionou sem indicar para onde.');
            }

            return $this->buscarComProfundidade(self::resolverUrlAbsoluta($url, $local), $profundidade + 1);
        }

        if (! $resposta->successful()) {
            return ResultadoDaBusca::comErro("A URL respondeu com erro ({$resposta->status()}).");
        }

        $corpo = $resposta->body();

        if (strlen($corpo) > self::TAMANHO_MAXIMO_BYTES) {
            return ResultadoDaBusca::comErro('O arquivo é maior que o limite de 5 MB.');
        }

        $tipoDeConteudo = strtolower(trim(explode(';', $resposta->header('Content-Type') ?? '')[0]));

        if (str_starts_with($tipoDeConteudo, 'image/')) {
            $webp = ImagemSeguraWebp::converterParaWebp($corpo);

            if ($webp === null) {
                return ResultadoDaBusca::comErro('O conteúdo não é uma imagem em formato aceito (JPEG, PNG, GIF ou WebP).');
            }

            return ResultadoDaBusca::comSucesso(base64_encode($webp), $url);
        }

        if (str_starts_with($tipoDeConteudo, 'text/html')) {
            $candidata = self::extrairCandidata($corpo, $url);

            if ($candidata === null) {
                return ResultadoDaBusca::comErro('Não encontramos og:image, ícone ou imagem nessa página.');
            }

            return $this->buscarComProfundidade($candidata, $profundidade + 1);
        }

        return ResultadoDaBusca::comErro('A URL não retornou uma imagem nem uma página HTML.');
    }

    /** @return array{0: string, 1: int, 2: string}|ResultadoDaBusca [host, porta, ip] prontos para a requisicao pinada, ou o erro. */
    private function prepararRequisicaoSegura(string $url): array|ResultadoDaBusca
    {
        if (! UrlExternaSegura::esquemaPermitido($url)) {
            return ResultadoDaBusca::comErro('Só URLs http:// ou https:// são aceitas.');
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return ResultadoDaBusca::comErro('URL inválida.');
        }

        $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $porta = parse_url($url, PHP_URL_PORT) ?? ($esquema === 'https' ? 443 : 80);

        $ip = UrlExternaSegura::resolverIpPermitido($host, $this->resolvedorDns);

        if ($ip === null) {
            return ResultadoDaBusca::comErro('Este endereço aponta para uma rede privada, reservada ou não pôde ser resolvido — bloqueado por segurança.');
        }

        return [$host, $porta, $ip];
    }

    public static function resolverUrlAbsoluta(string $base, string $alvo): string
    {
        if (parse_url($alvo, PHP_URL_SCHEME) !== null) {
            return $alvo;
        }

        $partesBase = parse_url($base) ?: [];
        $esquema = $partesBase['scheme'] ?? 'https';
        $host = $partesBase['host'] ?? '';
        $porta = isset($partesBase['port']) ? ':'.$partesBase['port'] : '';

        if (str_starts_with($alvo, '//')) {
            return "{$esquema}:{$alvo}";
        }

        if (str_starts_with($alvo, '/')) {
            return "{$esquema}://{$host}{$porta}{$alvo}";
        }

        $caminhoBase = $partesBase['path'] ?? '/';
        $ultimaBarra = strrpos($caminhoBase, '/');
        $diretorio = $ultimaBarra === false ? '/' : substr($caminhoBase, 0, $ultimaBarra + 1);

        return "{$esquema}://{$host}{$porta}{$diretorio}{$alvo}";
    }

    /**
     * Ordem da etapa 15: og:image, apple-touch-icon (o maior, pelo atributo
     * sizes), icone do <link>, nessa ordem. A propria URL ja em formato de
     * imagem nem chega aqui — e tratada antes, pelo Content-Type.
     */
    private static function extrairCandidata(string $html, string $urlBase): ?string
    {
        if (trim($html) === '') {
            return null;
        }

        $erroAnterior = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($erroAnterior);

        $xpath = new DOMXPath($dom);

        foreach (['og:image:secure_url', 'og:image'] as $propriedade) {
            $nos = $xpath->query("//meta[@property='{$propriedade}']/@content");

            if ($nos !== false && $nos->length > 0) {
                $valor = trim((string) $nos->item(0)->nodeValue);

                if ($valor !== '') {
                    return self::resolverUrlAbsoluta($urlBase, $valor);
                }
            }
        }

        $links = $xpath->query('//link[@rel]');
        $melhorIconeApple = null;
        $maiorTamanho = -1;

        if ($links !== false) {
            foreach ($links as $link) {
                $rel = strtolower(trim($link->getAttribute('rel')));
                $href = trim($link->getAttribute('href'));

                if ($href === '' || ! str_contains($rel, 'apple-touch-icon')) {
                    continue;
                }

                $tamanho = self::tamanhoDoAtributoSizes($link->getAttribute('sizes'));

                if ($tamanho > $maiorTamanho) {
                    $maiorTamanho = $tamanho;
                    $melhorIconeApple = $href;
                }
            }
        }

        if ($melhorIconeApple !== null) {
            return self::resolverUrlAbsoluta($urlBase, $melhorIconeApple);
        }

        if ($links !== false) {
            foreach ($links as $link) {
                $rel = strtolower(trim($link->getAttribute('rel')));
                $href = trim($link->getAttribute('href'));

                if ($href !== '' && in_array($rel, ['icon', 'shortcut icon'], true)) {
                    return self::resolverUrlAbsoluta($urlBase, $href);
                }
            }
        }

        return null;
    }

    private static function tamanhoDoAtributoSizes(string $sizes): int
    {
        if (preg_match('/(\d+)x(\d+)/i', $sizes, $m)) {
            return max((int) $m[1], (int) $m[2]);
        }

        return 0;
    }
}

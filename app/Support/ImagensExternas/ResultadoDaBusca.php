<?php

namespace App\Support\ImagensExternas;

/**
 * Etapa 15: o que BuscaDeImagemExterna devolve. Sucesso carrega o WebP em
 * base64 — nunca um caminho em disco, porque nada e gravado antes da
 * aprovacao humana (regra 10).
 */
final readonly class ResultadoDaBusca
{
    private function __construct(
        public bool $sucesso,
        public ?string $erro,
        public ?string $webpBase64,
        public ?string $encontradaEm,
    ) {}

    public static function comErro(string $mensagem): self
    {
        return new self(sucesso: false, erro: $mensagem, webpBase64: null, encontradaEm: null);
    }

    public static function comSucesso(string $webpBase64, string $encontradaEm): self
    {
        return new self(sucesso: true, erro: null, webpBase64: $webpBase64, encontradaEm: $encontradaEm);
    }
}

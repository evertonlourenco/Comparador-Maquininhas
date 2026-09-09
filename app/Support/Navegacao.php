<?php

namespace App\Support;

/**
 * O menu do cabeçalho. A etapa 06 promete que "link morto é pior que
 * cabeçalho sem link" — por isso só entra aqui a seção que já existe. Cupons
 * (etapa 09) e metodologia (etapa 10) entram quando as páginas delas existirem.
 */
final class Navegacao
{
    /** @return list<array{rotulo: string, href: string, atual: bool}> */
    public static function principal(string $secaoAtual): array
    {
        return [
            ['rotulo' => 'Comparador', 'href' => route('comparador'), 'atual' => $secaoAtual === 'comparador'],
            ['rotulo' => 'Marcas', 'href' => route('maquininhas.index'), 'atual' => $secaoAtual === 'marcas'],
        ];
    }
}

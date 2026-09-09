<?php

namespace App\Support;

/**
 * O menu do cabeçalho e os links do rodapé. A etapa 06 promete que "link
 * morto é pior que cabeçalho sem link" — por isso só entra aqui a seção que
 * já existe.
 */
final class Navegacao
{
    /** @return list<array{rotulo: string, href: string, atual: bool}> */
    public static function principal(string $secaoAtual): array
    {
        return [
            ['rotulo' => 'Comparador', 'href' => route('comparador'), 'atual' => $secaoAtual === 'comparador'],
            ['rotulo' => 'Marcas', 'href' => route('maquininhas.index'), 'atual' => $secaoAtual === 'marcas'],
            ['rotulo' => 'Cupons', 'href' => route('cupons.index'), 'atual' => $secaoAtual === 'cupons'],
            ['rotulo' => 'Metodologia', 'href' => route('metodologia'), 'atual' => $secaoAtual === 'metodologia'],
        ];
    }

    /**
     * Etapa 10: o padrão de site.blade.php quando a página não traz o
     * próprio `linksRodape` — assim as oito páginas de antes ganham os
     * links novos sem precisar editar cada uma.
     *
     * @return list<array{rotulo: string, href: string}>
     */
    public static function rodape(): array
    {
        return [
            ['rotulo' => 'Metodologia', 'href' => route('metodologia')],
            ['rotulo' => 'Enviar proposta recebida', 'href' => route('propostas.create')],
            ['rotulo' => 'Política de privacidade', 'href' => route('privacidade')],
            ['rotulo' => 'Termos de uso', 'href' => route('termos')],
            // JavaScript intercepta o clique (data-gerenciar-cookies em
            // resources/js/app.js) e reabre o banner sem navegar — o href
            // "#" e so o piso para quando o JS nao roda.
            ['rotulo' => 'Gerenciar cookies', 'href' => '#', 'atributo' => 'data-gerenciar-cookies'],
        ];
    }
}

@props([
    // Sem href vira <button>; com href vira <a>.
    'href' => null,
    // principal: a acao da tela, em verde — uma por vez (manual de marca).
    // marca: acao forte que nao e a principal, em navy ("Ver oferta" num
    // cartao que nao e o destaque). secundaria: alternativa, com contorno.
    // discreta: link com area de toque.
    'variante' => 'principal',
    'tamanho' => 'normal',
    'tipo' => 'button',
    // Abre em outra aba, com o aviso lido por leitor de tela.
    'externo' => false,
    // Regra 5: link de afiliado carrega rel="sponsored nofollow", sempre.
    'afiliado' => false,
    'largo' => false,
])

@php
    // min-h-12 = 48px, a altura minima do manual no celular. A maioria do
    // publico chega pelo celular, entao isso vale para o botao discreto tambem.
    $base = 'inline-flex min-h-12 items-center justify-center gap-2 rounded-botao border-[1.5px] '
        .'font-titulo font-semibold leading-tight '
        // Etapa 20, bloco E: 150ms em cor e sombra. `transition-opacity` e
        // `transition-colors` juntos se anulavam (a ultima classe vence).
        .'transition-[color,background-color,border-color,box-shadow] duration-150 ease-out '
        // Desativado do manual: fundo cinza, texto apagado — nunca o verde
        // lavado, que ainda pareceria a acao principal.
        .'disabled:cursor-not-allowed disabled:border-superficie-forte disabled:bg-superficie-forte disabled:text-tinta-suave disabled:no-underline '
        .'aria-disabled:cursor-not-allowed aria-disabled:border-superficie-forte aria-disabled:bg-superficie-forte aria-disabled:text-tinta-suave aria-disabled:no-underline';

    $variantes = [
        'principal' => 'border-acao bg-acao text-sobre-acao shadow-botao hover:border-acao-forte hover:bg-acao-forte active:shadow-none',
        'marca' => 'border-marca bg-marca text-sobre-marca shadow-botao hover:opacity-90 active:shadow-none',
        'secundaria' => 'border-tinta bg-transparent text-tinta hover:bg-superficie',
        'discreta' => 'border-transparent bg-transparent font-sans text-link underline underline-offset-4 hover:bg-superficie',
    ];

    // Saira 600 15px, padding 14px 26px no tamanho grande (manual de marca).
    $tamanhos = [
        'normal' => 'px-5 py-2.5 text-[0.9375rem]',
        'grande' => 'px-[1.625rem] py-3.5 text-base',
    ];

    $classes = trim(implode(' ', [
        $base,
        $variantes[$variante] ?? $variantes['principal'],
        $tamanhos[$tamanho] ?? $tamanhos['normal'],
        $largo ? 'w-full' : '',
    ]));

    $abreFora = $externo || $afiliado;

    $rel = collect(['noopener', 'noreferrer'])
        ->when($afiliado, fn ($r) => $r->push('sponsored', 'nofollow'))
        ->implode(' ');
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        @if ($abreFora) target="_blank" rel="{{ $rel }}" @endif
        {{ $attributes->class($classes) }}
    >
        {{ $slot }}
        @if ($abreFora)
            <svg class="size-3.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path d="M6 3H3.5A1.5 1.5 0 0 0 2 4.5v8A1.5 1.5 0 0 0 3.5 14h8a1.5 1.5 0 0 0 1.5-1.5V10" stroke-linecap="round"/>
                <path d="M9.5 2.5H14V7M14 2.5 7.5 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="sr-only">(abre em nova aba)</span>
        @endif
    </a>
@else
    <button type="{{ $tipo }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif

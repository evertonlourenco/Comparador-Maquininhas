@props([
    // Sem href vira <button>; com href vira <a>.
    'href' => null,
    // principal: a acao da pagina. secundaria: alternativa. discreta: link com area de toque.
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
    // min-h-11 = 44px, o alvo de toque minimo. A maioria do publico chega pelo
    // celular, entao isso vale para o botao discreto tambem.
    $base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-selo border '
        .'font-sans font-semibold leading-tight transition-opacity transition-colors '
        .'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:cursor-not-allowed aria-disabled:opacity-50';

    $variantes = [
        'principal' => 'border-aferido bg-aferido text-papel hover:opacity-90 active:opacity-100',
        'secundaria' => 'border-contorno bg-transparent text-tinta hover:bg-superficie',
        'discreta' => 'border-transparent bg-transparent text-link underline underline-offset-4 hover:bg-superficie',
    ];

    $tamanhos = [
        'normal' => 'px-4 py-2 text-sm',
        'grande' => 'px-5 py-3 text-base',
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

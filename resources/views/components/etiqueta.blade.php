@props([
    // Dois canais que nao se misturam (etapa 14):
    //   estado do dado — aferido = publicado pela marca e dentro do prazo;
    //     reportado = faixa, promocao ou condicao; vencido = fora da validade;
    //     apagado = sem dado.
    //   marca — economia = menor custo ou desconto (verde claro do manual);
    //     parceiro = "desconto parceiro", obrigatorio quando ha comissao.
    'tom' => 'neutro',
    'variante' => 'contorno',
])

@php
    $contorno = [
        'neutro' => 'border-regua bg-superficie text-tinta-suave',
        'aferido' => 'border-aferido bg-aferido-fundo text-aferido',
        'reportado' => 'border-dashed border-reportado bg-reportado-fundo text-reportado',
        'vencido' => 'border-vencido bg-vencido-fundo text-vencido',
        'apagado' => 'border-regua-forte bg-superficie text-tinta-suave',
        'economia' => 'border-acao-fundo bg-acao-fundo text-acao',
        'parceiro' => 'border-marca bg-marca text-sobre-marca',
    ];

    $solida = [
        'neutro' => 'border-tinta bg-tinta text-papel',
        'aferido' => 'border-aferido bg-aferido text-papel',
        'reportado' => 'border-reportado bg-reportado text-papel',
        'vencido' => 'border-vencido bg-vencido text-papel',
        'apagado' => 'border-tinta-suave bg-tinta-suave text-papel',
        'economia' => 'border-acao bg-acao text-sobre-acao',
        'parceiro' => 'border-marca bg-marca text-sobre-marca',
    ];

    $mapa = $variante === 'solida' ? $solida : $contorno;
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-selo border px-2 py-1',
    'font-sans text-selo font-semibold uppercase',
    $mapa[$tom] ?? $mapa['neutro'],
]) }}>{{ $slot }}</span>

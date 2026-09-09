@props([
    // Os tons nomeiam estado do dado, nunca humor: aferido = publicado pela
    // marca e dentro do prazo; reportado = faixa, promocao ou condicao;
    // vencido = fora da validade; apagado = sem dado.
    'tom' => 'neutro',
    'variante' => 'contorno',
])

@php
    $contorno = [
        'neutro' => 'border-contorno bg-transparent text-tinta-suave',
        'aferido' => 'border-aferido bg-aferido-fundo text-aferido',
        'reportado' => 'border-reportado bg-reportado-fundo text-reportado',
        'vencido' => 'border-vencido bg-vencido-fundo text-vencido',
        'apagado' => 'border-regua-forte bg-superficie text-tinta-suave',
    ];

    $solida = [
        'neutro' => 'border-tinta bg-tinta text-papel',
        'aferido' => 'border-aferido bg-aferido text-papel',
        'reportado' => 'border-reportado bg-reportado text-papel',
        'vencido' => 'border-vencido bg-vencido text-papel',
        'apagado' => 'border-tinta-suave bg-tinta-suave text-papel',
    ];

    $mapa = $variante === 'solida' ? $solida : $contorno;
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-selo border px-1.5 py-0.5',
    'font-sans text-etiqueta font-semibold uppercase',
    $mapa[$tom] ?? $mapa['neutro'],
]) }}>{{ $slot }}</span>

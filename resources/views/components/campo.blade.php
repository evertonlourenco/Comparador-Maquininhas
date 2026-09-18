@props([
    // O rotulo e obrigatorio de proposito: nao existe caminho neste componente
    // que produza um campo sem <label for>. Placeholder nao e rotulo.
    'rotulo',
    'nome',
    'id' => null,
    'elemento' => 'input',
    'tipo' => 'text',
    'valor' => null,
    'opcoes' => [],
    'ajuda' => null,
    'erro' => null,
    'obrigatorio' => false,
    // Regra 11: o usuario digita "10.000,00", nao "10000.00". Por isso prefixo
    // "R$" e inputmode decimal em vez de type="number".
    'prefixo' => null,
    'sufixo' => null,
    'linhas' => 3,
])

@php
    $id ??= 'campo-'.Str::slug($nome);
    $idAjuda = $id.'-ajuda';
    $idErro = $id.'-erro';

    $descritores = collect([$ajuda ? $idAjuda : null, $erro ? $idErro : null])->filter()->implode(' ');

    $controle = trim(implode(' ', [
        // Etapa 20 (bloco D): fundo em superficie, nao papel — o campo vive
        // dentro de um cartao branco (bg-papel), e um campo da mesma cor que
        // o cartao so tinha a borda para se distinguir. Combinacao ja validada
        // em scripts/verifica-contraste.mjs ("contorno de campo na superficie").
        'block w-full min-h-12 rounded-botao border-2 bg-superficie px-3 py-2 text-base text-tinta',
        'placeholder:text-tinta-suave',
        $erro ? 'border-vencido' : 'border-contorno',
        $prefixo ? 'ps-10' : '',
        $sufixo ? 'pe-10' : '',
    ]));
@endphp

<div {{ $attributes->only('class')->class('space-y-1.5') }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-tinta">
        {{ $rotulo }}
        @if ($obrigatorio)
            <span class="text-vencido" aria-hidden="true">*</span>
            <span class="sr-only">(obrigatório)</span>
        @endif
    </label>

    <div class="relative">
        @if ($prefixo)
            <span class="numero pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-tinta-suave" aria-hidden="true">{{ $prefixo }}</span>
        @endif

        @if ($elemento === 'select')
            <select
                id="{{ $id }}"
                name="{{ $nome }}"
                @if ($obrigatorio) required @endif
                @if ($erro) aria-invalid="true" @endif
                @if ($descritores) aria-describedby="{{ $descritores }}" @endif
                {{ $attributes->except('class')->class($controle) }}
            >
                @foreach ($opcoes as $chave => $texto)
                    <option value="{{ $chave }}" @selected((string) $chave === (string) $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        @elseif ($elemento === 'textarea')
            <textarea
                id="{{ $id }}"
                name="{{ $nome }}"
                rows="{{ $linhas }}"
                @if ($obrigatorio) required @endif
                @if ($erro) aria-invalid="true" @endif
                @if ($descritores) aria-describedby="{{ $descritores }}" @endif
                {{ $attributes->except('class')->class($controle) }}
            >{{ $valor }}</textarea>
        @else
            <input
                type="{{ $tipo }}"
                id="{{ $id }}"
                name="{{ $nome }}"
                value="{{ $valor }}"
                @if ($obrigatorio) required @endif
                @if ($erro) aria-invalid="true" @endif
                @if ($descritores) aria-describedby="{{ $descritores }}" @endif
                {{ $attributes->except('class')->class($controle) }}
            >
        @endif

        @if ($sufixo)
            <span class="numero pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-tinta-suave" aria-hidden="true">{{ $sufixo }}</span>
        @endif
    </div>

    @if ($ajuda)
        <p id="{{ $idAjuda }}" class="text-miudo text-tinta-suave">{{ $ajuda }}</p>
    @endif

    @if ($erro)
        <p id="{{ $idErro }}" class="text-miudo text-vencido">{{ $erro }}</p>
    @endif
</div>

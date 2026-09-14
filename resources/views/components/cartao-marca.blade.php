@props([
    'nome',
    'logo' => null,
    // Regra 7: adquirente e transparencia, nunca deduplicacao. Aparece como
    // nota de rodape do cartao, nao como classificacao.
    'adquirente' => null,
    'nota' => null,
    'notaData' => null,
    'notaUrl' => null,
    'href' => null,
    'posicao' => null,
    // App\Motor\EstadoDoResultado, ou a string do enum.
    'estado' => null,
    'destaque' => false,
])

@php
    use App\Motor\EstadoDoResultado;
    use App\Support\Dinheiro;

    $estadoEnum = $estado instanceof EstadoDoResultado
        ? $estado
        : ($estado ? EstadoDoResultado::tryFrom($estado) : null);

    // So `calculado` e ranqueavel; os outros precisam sair com cor e motivo a
    // vista para nunca parecerem disputar a mesma corrida.
    $tomDoEstado = match ($estadoEnum) {
        EstadoDoResultado::Calculado => 'aferido',
        EstadoDoResultado::Promocional, EstadoDoResultado::FaixaReportada => 'reportado',
        EstadoDoResultado::Incompleto, EstadoDoResultado::SemDadoPublicado => 'apagado',
        default => 'neutro',
    };
@endphp

{{-- Cartao de comparacao do manual: raio 10px, borda 1px no padrao, 1,5px
     verde no destaque. --}}
<article {{ $attributes->class([
    'overflow-hidden rounded-bloco bg-papel',
    $destaque ? 'border-[1.5px] border-acao' : 'border border-regua',
]) }}>
    <div class="flex items-start gap-3 p-4">
        @if ($posicao !== null)
            <span class="numero-destaque shrink-0 pt-0.5 text-3xl leading-none text-tinta-suave" aria-hidden="true">{{ $posicao }}</span>
            <span class="sr-only">{{ $posicao }}º lugar.</span>
        @endif

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1.5">
                <h3 class="min-w-0 text-cartao">
                    @if ($href)
                        <a href="{{ $href }}" class="hover:text-link hover:underline underline-offset-4">{{ $nome }}</a>
                    @else
                        {{ $nome }}
                    @endif
                </h3>

                @if ($estadoEnum)
                    <x-etiqueta :tom="$tomDoEstado">{{ $estadoEnum->rotulo() }}</x-etiqueta>
                @endif
            </div>

            @if ($adquirente)
                <p class="mt-1 text-miudo text-tinta-suave">
                    <span class="sr-only">Adquirente que processa por trás:</span>
                    <span aria-hidden="true">Processa com</span> <span class="font-medium text-tinta">{{ $adquirente }}</span>
                </p>
            @endif

            @if ($nota !== null)
                <p class="mt-1 text-miudo text-tinta-suave">
                    Reclame Aqui
                    <span class="numero font-medium text-tinta">{{ Dinheiro::numero((float) $nota, 1) }}</span><span class="numero">/10</span>
                    @if ($notaData)
                        · consultado em <span class="numero">{{ Dinheiro::data($notaData) }}</span>
                    @endif
                    @if ($notaUrl)
                        · <a href="{{ $notaUrl }}" target="_blank" rel="noopener noreferrer nofollow"
                             class="text-link underline underline-offset-2 hover:no-underline">ver página<span class="sr-only"> no Reclame Aqui (abre em nova aba)</span></a>
                    @endif
                </p>
            @endif
        </div>

        {{-- O quadrado do logo fica a direita, como no manual. Sem logo, a
             inicial — nunca uma imagem generica (etapa 15). --}}
        <div class="flex size-14 shrink-0 items-center justify-center rounded-botao border border-regua bg-superficie">
            @if ($logo)
                {{-- alt vazio de proposito: o nome da marca esta ao lado, e repetir
                     duplicaria a leitura em leitor de tela. --}}
                <img src="{{ $logo }}" alt="" class="max-h-10 max-w-10 object-contain" loading="lazy" decoding="async">
            @else
                <span class="font-titulo text-xl font-semibold text-tinta-suave" aria-hidden="true">{{ mb_substr($nome, 0, 1) }}</span>
            @endif
        </div>
    </div>

    @if (trim($slot) !== '')
        <div class="border-t border-regua px-4 py-4">
            {{ $slot }}
        </div>
    @endif

    @isset($acoes)
        <div class="flex flex-wrap gap-2 border-t border-regua bg-superficie px-4 py-3">
            {{ $acoes }}
        </div>
    @endisset
</article>

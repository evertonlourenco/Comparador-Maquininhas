@props([
    'codigo',
    'marca' => null,
    // Um dos dois: `descricao` pronta, ou valor + tipo para o componente formatar.
    'descricao' => null,
    'valor' => null,
    'tipoDesconto' => 'valor',
    // App\Enums\IncideSobre: adesao | equipamento. Regra 5 — nunca sobre a taxa.
    'incideSobre' => 'adesao',
    'validoAte',
    'url' => null,
    'condicao' => null,
    // Regra 5: cupom vencido some sozinho. Desligue so no guia visual, para
    // mostrar como ele fica.
    'ocultarVencido' => true,
])

@php
    use App\Enums\IncideSobre;
    use App\Enums\TipoDesconto;
    use App\Support\Dinheiro;
    use Illuminate\Support\Carbon;

    $limite = $validoAte instanceof Carbon ? $validoAte->copy() : Carbon::parse($validoAte);
    $diasRestantes = (int) Carbon::today()->diffInDays($limite->copy()->startOfDay(), false);
    $vencido = $diasRestantes < 0;
    // Mesmo limite que o Painel Inicial usa para avisar no admin.
    $vencendo = ! $vencido && $diasRestantes <= 7;

    $incide = $incideSobre instanceof IncideSobre ? $incideSobre : IncideSobre::tryFrom((string) $incideSobre);
    $sobre = $incide === IncideSobre::Equipamento ? 'no valor do aparelho' : 'na adesão';

    $tipo = $tipoDesconto instanceof TipoDesconto ? $tipoDesconto : TipoDesconto::tryFrom((string) $tipoDesconto);

    $desconto = $descricao ?: match (true) {
        $valor === null => null,
        $tipo === TipoDesconto::Percentual => Dinheiro::percentual((float) $valor).' de desconto '.$sobre,
        default => Dinheiro::real((float) $valor).' de desconto '.$sobre,
    };

    $tom = $vencido ? 'vencido' : ($vencendo ? 'reportado' : 'aferido');
@endphp

@if ($vencido && $ocultarVencido)
    {{-- Regra 5: some sozinho ao vencer, sem depender de alguem lembrar. --}}
@else
    <section {{ $attributes->class([
        'rounded-bloco border bg-papel',
        $vencido ? 'border-vencido' : ($vencendo ? 'border-reportado' : 'border-aferido'),
    ]) }} aria-labelledby="cupom-{{ Str::slug($codigo) }}">
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-b border-regua px-4 py-3">
            <h3 id="cupom-{{ Str::slug($codigo) }}" class="text-subtitulo">
                Cupom{{ $marca ? ' '.$marca : '' }}
            </h3>
            <x-etiqueta :tom="$tom">
                @if ($vencido)
                    Vencido
                @elseif ($vencendo)
                    Vence em {{ $diasRestantes }} {{ $diasRestantes === 1 ? 'dia' : 'dias' }}
                @else
                    Vigente
                @endif
            </x-etiqueta>
        </div>

        <div class="space-y-3 px-4 py-4">
            @if ($desconto)
                <p class="text-titulo font-titulo">{{ $desconto }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                <code class="numero rounded-selo border border-dashed border-contorno bg-superficie px-3 py-2 text-base font-medium tracking-wider {{ $vencido ? 'line-through text-tinta-suave' : '' }}">{{ $codigo }}</code>

                @unless ($vencido)
                    <x-botao
                        variante="secundaria"
                        data-copiar="{{ $codigo }}"
                        class="text-miudo"
                    >Copiar código</x-botao>
                @endunless
            </div>

            <p class="text-miudo {{ $vencido ? 'text-vencido' : 'text-tinta-suave' }}">
                @if ($vencido)
                    Valeu até
                @else
                    Válido até
                @endif
                <time datetime="{{ $limite->toDateString() }}" class="numero font-medium">{{ Dinheiro::data($limite) }}</time>.
            </p>

            @if ($condicao)
                <p class="text-miudo text-reportado">{{ $condicao }}</p>
            @endif

            {{-- Regra 5 dita, literalmente, o texto abaixo: a taxa pelo link e a
                 mesma do site oficial, e a vantagem e so o desconto na adesao.
                 Escondermos isso seria vender o que nao existe. --}}
            <p class="border-t border-regua pt-3 text-miudo text-tinta-suave">
                Link de afiliado. <strong class="font-medium text-tinta">A taxa pelo nosso link é a mesma do site oficial</strong> —
                o cupom desconta apenas {{ $sobre === 'na adesão' ? 'a adesão' : 'o valor do aparelho' }}.
            </p>
        </div>

        @unless ($vencido)
            @if ($url)
                <div class="border-t border-regua bg-superficie px-4 py-3">
                    <x-botao :href="$url" afiliado largo tamanho="grande">
                        Abrir {{ $marca ? 'o site da '.$marca : 'o site oficial' }} com o cupom
                    </x-botao>
                </div>
            @endif
        @endunless
    </section>
@endif

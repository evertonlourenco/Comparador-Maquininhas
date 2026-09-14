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
    // Etapa 09: com os dois presentes, "copiar código" e "usar cupom" ganham
    // os atributos que resources/js/app.js le para registrar o evento. Sem
    // eles (o guia visual, por exemplo), o bloco funciona igual e so nao
    // rastreia nada — nao existe cupom de amostra para reconciliar.
    'marcaSlug' => null,
    'origem' => null,
    // Manual de marca: verde so para a acao principal da tela, uma por vez.
    // Numa grade de cupons, ou quando a pagina ja tem o seu CTA verde, o
    // botao do bloco sai em navy (`marca`).
    'varianteBotao' => 'principal',
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

    // Vigente nao ganha cor de estado: o verde do selo de economia ja diz que
    // ha desconto. So o que degradou (a vencer, vencido) chama atencao.
    $tom = $vencido ? 'vencido' : ($vencendo ? 'reportado' : 'neutro');

    $rastreia = $marcaSlug && $origem;
@endphp

@if ($vencido && $ocultarVencido)
    {{-- Regra 5: some sozinho ao vencer, sem depender de alguem lembrar. --}}
@else
    <section {{ $attributes->class([
        'overflow-hidden rounded-bloco bg-papel',
        $vencido ? 'border border-vencido' : ($vencendo ? 'border border-dashed border-reportado' : 'border border-regua'),
    ]) }} aria-labelledby="cupom-{{ Str::slug($codigo) }}">
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-b border-regua px-4 py-3">
            <h3 id="cupom-{{ Str::slug($codigo) }}" class="text-cartao">
                Cupom{{ $marca ? ' '.$marca : '' }}
            </h3>
            <div class="flex flex-wrap items-center gap-1.5">
                {{-- Manual de marca: "desconto parceiro" e obrigatorio sempre que
                     ha comissao — e todo cupom daqui sai por link de afiliado. --}}
                <x-etiqueta tom="parceiro">Desconto parceiro</x-etiqueta>
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
        </div>

        <div class="space-y-3 px-4 py-4">
            @if ($desconto)
                <p class="font-titulo text-numero font-bold {{ $vencido ? 'text-tinta-suave' : 'text-acao' }}">{{ $desconto }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                <code class="numero rounded-botao border border-dashed border-contorno bg-superficie px-3 py-2.5 text-base font-semibold tracking-wider {{ $vencido ? 'line-through text-tinta-suave' : '' }}">{{ $codigo }}</code>

                @unless ($vencido)
                    {{-- Blade nao aceita @if dentro da tag de um componente: um
                         atributo dinamico nulo e o jeito certo de tornar o
                         data-* condicional — ComponentAttributeBag omite
                         null/false sozinho. --}}
                    <x-botao
                        variante="secundaria"
                        data-copiar="{{ $codigo }}"
                        :data-marca="$rastreia ? $marcaSlug : null"
                        :data-cupom="$rastreia ? $codigo : null"
                        :data-origem="$rastreia ? $origem : null"
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
                Link de afiliado. <strong class="font-semibold text-tinta">A taxa pelo nosso link é a mesma do site oficial</strong> —
                o cupom desconta apenas {{ $sobre === 'na adesão' ? 'a adesão' : 'o valor do aparelho' }}.
            </p>
        </div>

        @unless ($vencido)
            @if ($url)
                <div class="border-t border-regua bg-superficie px-4 py-3">
                    <x-botao
                        :href="$url"
                        :variante="$varianteBotao"
                        afiliado
                        largo
                        tamanho="grande"
                        :data-usar-cupom="$rastreia"
                        :data-marca="$rastreia ? $marcaSlug : null"
                        :data-cupom="$rastreia ? $codigo : null"
                        :data-origem="$rastreia ? $origem : null"
                    >
                        Abrir {{ $marca ? 'o site da '.$marca : 'o site oficial' }} com o cupom
                    </x-botao>
                </div>
            @endif
        @endunless
    </section>
@endif

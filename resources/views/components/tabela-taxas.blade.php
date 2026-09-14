@props([
    // Regra 4: as duas classes de dado nunca se misturam, e por isso a mesma
    // tabela nao renderiza as duas. `reportada` nunca imprime numero exato —
    // sai faixa, com mediana rotulada e o numero de relatos ao lado.
    'classe' => 'divulgada',

    // divulgada: ['rotulo', 'percentual', 'fixo', 'prazo', 'condicao']
    // reportada: ['rotulo', 'minimo', 'mediana', 'maximo', 'relatos']
    'linhas' => [],

    'titulo' => null,
    'colunaRotulo' => 'Parcelas',
    'fonte' => null,
    'fonteRotulo' => 'Ver fonte',
    'dataVerificacao' => null,
    'nivelFrescor' => null,
    'diasFrescor' => null,
    'periodoInicio' => null,
    'periodoFim' => null,

    // Etapa 10: sem marcaSlug o botão "reportar taxa errada" não aparece —
    // é o caso do guia-visual, que só mostra dado de amostra.
    'marcaSlug' => null,
    'marcaNome' => null,
])

@php
    use App\Support\Dinheiro;

    $linhas = collect($linhas);
    $reportada = $classe === 'reportada';

    // A coluna de valor fixo so existe quando alguma linha tem valor fixo:
    // coluna vazia em tabela estreita de celular e espaco roubado.
    $temFixo = $linhas->contains(fn ($l) => ($l['fixo'] ?? null) !== null);
    $temPrazo = $linhas->contains(fn ($l) => ($l['prazo'] ?? null) !== null);
    // Uma tabela com selo por linha ja diz a data de cada numero; repetir o
    // selo agregado no rodape so faria sentido se alguem passasse a data da
    // tabela inteira de proposito (como faz o guia visual, com dado de amostra).
    $temFrescorPorLinha = $linhas->contains(fn ($l) => array_key_exists('data_verificacao', $l));

    $cabecalho = 'px-3 py-2 text-etiqueta font-semibold uppercase text-tinta-suave';
    $celula = 'px-3 py-2.5 align-top';

    // Mobile-first: a tabela so ganha largura minima (e rola) quando tem
    // coluna demais para caber em 375px. Rotulo + taxa cabem sem rolar.
    $colunas = $reportada ? 4 : 2 + (int) $temFixo + (int) $temPrazo;
    $larguraMinima = match (true) {
        $colunas >= 4 => 'min-w-[32rem]',
        $colunas === 3 => 'min-w-[24rem]',
        default => '',
    };

    $descricao = $reportada
        ? 'Faixa de valores reportados por lojistas, por linha de venda.'
        : 'Taxas publicadas pela marca, por linha de venda.';
@endphp

<figure {{ $attributes->class('overflow-hidden rounded-bloco border border-regua bg-papel') }}>
    <figcaption class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-b border-regua px-4 py-3">
        <span class="font-titulo text-cartao font-semibold">{{ $titulo }}</span>
        @if ($reportada)
            <x-etiqueta tom="reportado">Faixa reportada</x-etiqueta>
        @else
            <x-etiqueta tom="aferido">Taxa divulgada</x-etiqueta>
        @endif
    </figcaption>

    {{-- Regiao rolavel precisa ser alcancavel pelo teclado (WCAG 2.1.1): dai o
         tabindex e o rotulo. No celular a tabela rola em vez de espremer. --}}
    <div
        class="overflow-x-auto"
        tabindex="0"
        role="region"
        aria-label="{{ $titulo ? $titulo.' — tabela rolável horizontalmente' : 'Tabela rolável horizontalmente' }}"
    >
        <table class="w-full {{ $larguraMinima }} border-collapse text-left text-sm">
            <caption class="sr-only">{{ $titulo }}. {{ $descricao }}</caption>

            <thead>
                <tr class="border-b border-regua-forte bg-superficie-forte">
                    <th scope="col" class="{{ $cabecalho }} sticky left-0 bg-superficie-forte">{{ $colunaRotulo }}</th>

                    @if ($reportada)
                        <th scope="col" class="{{ $cabecalho }}">Faixa relatada</th>
                        <th scope="col" class="{{ $cabecalho }}">Mediana</th>
                        <th scope="col" class="{{ $cabecalho }} text-right">Relatos</th>
                    @else
                        <th scope="col" class="{{ $cabecalho }}">Taxa</th>
                        @if ($temFixo)
                            <th scope="col" class="{{ $cabecalho }}">Valor fixo</th>
                        @endif
                        @if ($temPrazo)
                            <th scope="col" class="{{ $cabecalho }}">Prazo</th>
                        @endif
                    @endif
                </tr>
            </thead>

            <tbody>
                @foreach ($linhas as $linha)
                    <tr class="border-b border-regua last:border-b-0 odd:bg-papel even:bg-superficie">
                        {{-- bg-inherit faz a coluna fixa herdar a zebra da linha. --}}
                        <th scope="row" class="{{ $celula }} sticky left-0 bg-inherit numero font-medium whitespace-nowrap">
                            {{ $linha['rotulo'] }}
                        </th>

                        @if ($reportada)
                            <td class="{{ $celula }} numero whitespace-nowrap">
                                {{ Dinheiro::percentual((float) $linha['minimo']) }}
                                <span class="text-tinta-suave" aria-hidden="true">–</span><span class="sr-only">a</span>
                                {{ Dinheiro::percentual((float) $linha['maximo']) }}
                            </td>
                            <td class="{{ $celula }} numero whitespace-nowrap font-medium text-reportado">
                                {{ Dinheiro::percentual((float) $linha['mediana']) }}
                                {{-- Uma tabela "completa" cruza varias linhas verificadas em datas
                                     diferentes; o selo do rodape sozinho nao da conta disso. --}}
                                @if (array_key_exists('data_verificacao', $linha))
                                    <x-selo-frescor compacto class="mt-0.5 whitespace-normal font-normal" :data="$linha['data_verificacao']" :fonte="$linha['url_fonte'] ?? null" />
                                @endif
                            </td>
                            <td class="{{ $celula }} numero text-right whitespace-nowrap">{{ $linha['relatos'] }}</td>
                        @else
                            <td class="{{ $celula }} whitespace-nowrap">
                                @if (($linha['percentual'] ?? null) === null)
                                    <span class="text-tinta-suave">não publicada</span>
                                @else
                                    <span class="numero font-medium">{{ Dinheiro::percentual((float) $linha['percentual']) }}</span>
                                @endif

                                {{-- Taxa condicionada: a condicao sai colada no numero, sempre. --}}
                                @if ($linha['condicao'] ?? null)
                                    <span class="mt-0.5 block text-miudo whitespace-normal text-reportado">{{ $linha['condicao'] }}</span>
                                @endif

                                {{-- Selo por linha (opcional): so a linha, e nao a tabela inteira,
                                     sabe quando aquele numero foi conferido. --}}
                                @if (array_key_exists('data_verificacao', $linha))
                                    <x-selo-frescor compacto class="mt-0.5 whitespace-normal" :data="$linha['data_verificacao']" :fonte="$linha['url_fonte'] ?? null" />
                                @endif
                            </td>

                            @if ($temFixo)
                                <td class="{{ $celula }} numero whitespace-nowrap">
                                    @if (($linha['fixo'] ?? null) === null)
                                        <span class="font-sans text-tinta-suave">—</span>
                                    @else
                                        {{ Dinheiro::real((float) $linha['fixo']) }}
                                    @endif
                                </td>
                            @endif

                            @if ($temPrazo)
                                <td class="{{ $celula }} whitespace-nowrap text-tinta-suave">{{ $linha['prazo'] ?? '—' }}</td>
                            @endif
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="space-y-2 border-t border-regua px-4 py-3">
        @if ($reportada)
            <p class="text-miudo text-tinta-suave">
                Mediana de relatos de lojistas, não tabela publicada pela marca.
                @if ($periodoInicio && $periodoFim)
                    Período apurado de <span class="numero">{{ Dinheiro::data($periodoInicio) }}</span>
                    a <span class="numero">{{ Dinheiro::data($periodoFim) }}</span>.
                @endif
            </p>
        @endif

        @if ($dataVerificacao || ! $temFrescorPorLinha)
            <x-selo-frescor
                :data="$dataVerificacao"
                :nivel="$nivelFrescor"
                :dias="$diasFrescor"
                :fonte="$fonte"
                :fonte-rotulo="$fonteRotulo"
            />
        @elseif ($fonte)
            <p class="text-miudo">
                <a href="{{ $fonte }}" target="_blank" rel="noopener noreferrer nofollow"
                   class="text-link underline underline-offset-2 hover:no-underline">{{ $fonteRotulo }}<span class="sr-only"> (abre em nova aba)</span></a>
            </p>
        @endif

        {{ $slot }}

        <x-formulario-taxa-incorreta :marca-slug="$marcaSlug" :contexto="trim(($marcaNome ?? '').' — '.($titulo ?? ''), ' —')" />
    </div>
</figure>

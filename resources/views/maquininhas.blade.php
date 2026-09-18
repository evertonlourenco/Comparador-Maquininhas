{{--
    Listagem de marcas (etapa 08): /maquininhas.

    Grade de cartoes, cada um com o que da para decidir sem abrir a pagina —
    mensalidade, prazo mais rapido e a faixa de taxa publicada — e uma caixa
    para marcar e levar a selecao direto para o comparador. Regra 4 aqui
    tambem: a faixa de quem nao publica tabela sai etiquetada como "faixa
    reportada", nunca como o mesmo numero de quem publica.
--}}
<x-layouts.site
    titulo="Compare taxas de maquininha de cartão por marca"
    descricao="Mensalidade, prazo de recebimento e a faixa de taxa de cada maquininha, com a fonte e a data em que conferimos. Marque as marcas e leve a seleção direto para o comparador."
    :navegacao="$navegacao"
    :canonical="$canonical"
    :schema="$schema"
>
    <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6" data-selecao-marcas>
        <header class="max-w-3xl space-y-3">
            <h1 class="text-manchete">Maquininhas de cartão, marca por marca</h1>
            <p class="text-subtitulo text-tinta-suave">
                O que está publicado hoje sobre mensalidade, prazo de recebimento e taxa —
                sempre com a fonte e a data em que conferimos. Quer o custo mensal exato para
                o seu negócio? Use o <a href="{{ route('comparador') }}" class="text-link underline underline-offset-4 hover:no-underline">comparador</a>.
            </p>
        </header>

        @if ($marcas->isEmpty())
            <p class="mt-8 rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta-suave">
                Nenhuma marca ativa no momento.
            </p>
        @else
            {{-- No celular fica grudada no topo enquanto a pessoa marca os
                 cartoes: o botao de comparar nao some ao rolar a grade. --}}
            <div class="sticky top-0 z-10 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-bloco border border-regua bg-papel px-4 py-3 shadow-cartao">
                <p class="text-sm text-tinta-suave">
                    <span class="numero font-semibold text-tinta" data-contagem-selecionadas>0</span>
                    de <span class="numero">{{ $marcas->count() }}</span> marcas selecionadas.
                </p>
                <x-botao href="{{ route('comparador') }}" data-ir-comparar aria-disabled="true" class="pointer-events-none w-full sm:w-auto">
                    Comparar selecionadas
                </x-botao>
            </div>

            <ul class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($marcas as $marca)
                    @php($resumo = $resumos[$marca->getKey()])
                    <li>
                        <x-cartao-marca
                            :nome="$marca->nome"
                            :logo="$marca->logo_url"
                            :href="route('maquininhas.show', $marca->slug)"
                        >
                            {{-- A grade de numeros do cartao do manual (rotulo em caixa
                                 alta embaixo do valor). A grade debito/credito/12x do
                                 manual nao entra: escolher plano, prazo e grupo por
                                 marca para montar esses tres seria decisao de dado
                                 (regra 1), nao de estilo. --}}
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-4">
                                <div class="flex flex-col-reverse">
                                    <dt class="mt-1 text-etiqueta font-semibold uppercase text-tinta-suave">Mensalidade</dt>
                                    <dd class="numero text-base font-semibold">{{ $resumo['mensalidade']['formatado'] ?? 'Não informado' }}</dd>
                                </div>
                                <div class="flex flex-col-reverse">
                                    <dt class="mt-1 text-etiqueta font-semibold uppercase text-tinta-suave">Prazo mais rápido</dt>
                                    <dd class="text-base font-semibold">{{ $resumo['prazo'] ?? 'Não informado' }}</dd>
                                </div>
                                <div class="col-span-2 flex flex-col-reverse">
                                    <dt class="mt-1 flex flex-wrap items-center gap-1.5 text-etiqueta font-semibold uppercase text-tinta-suave">
                                        Faixa de taxa
                                        @if (($resumo['faixa_taxa']['classe'] ?? null) === 'reportada')
                                            <x-etiqueta tom="reportado">Faixa reportada</x-etiqueta>
                                        @endif
                                    </dt>
                                    @if ($resumo['faixa_taxa'] ?? null)
                                        <dd class="numero-destaque text-numero">{{ $resumo['faixa_taxa']['formatado'] }}</dd>
                                    @else
                                        <dd class="text-base font-semibold text-tinta-suave">Sem dado publicado</dd>
                                    @endif
                                </div>
                            </dl>

                            <x-slot:acoes>
                                <label class="flex min-h-12 flex-1 cursor-pointer items-center gap-3 text-sm font-medium text-tinta">
                                    <input
                                        type="checkbox"
                                        data-marca-checkbox
                                        value="{{ $marca->slug }}"
                                        class="size-5 shrink-0 accent-[var(--cor-tinta)]"
                                    >
                                    Selecionar para comparar
                                </label>
                            </x-slot:acoes>
                        </x-cartao-marca>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.site>

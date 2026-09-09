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
            <p class="mt-8 rounded-bloco border border-regua bg-superficie px-4 py-3 text-sm text-tinta-suave">
                Nenhuma marca ativa no momento.
            </p>
        @else
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-bloco border border-regua bg-superficie px-4 py-3">
                <p class="text-sm text-tinta-suave">
                    <span class="numero font-medium text-tinta" data-contagem-selecionadas>0</span>
                    de <span class="numero">{{ $marcas->count() }}</span> marcas selecionadas.
                </p>
                <x-botao href="{{ route('comparador') }}" data-ir-comparar aria-disabled="true" class="pointer-events-none">
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
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                                <div>
                                    <dt class="text-miudo text-tinta-suave">Mensalidade</dt>
                                    <dd class="numero text-sm font-medium">{{ $resumo['mensalidade']['formatado'] ?? 'Não informado' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-miudo text-tinta-suave">Prazo mais rápido</dt>
                                    <dd class="text-sm font-medium">{{ $resumo['prazo'] ?? 'Não informado' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="flex flex-wrap items-center gap-1.5 text-miudo text-tinta-suave">
                                        Faixa de taxa
                                        @if (($resumo['faixa_taxa']['classe'] ?? null) === 'reportada')
                                            <x-etiqueta tom="reportado">Faixa reportada</x-etiqueta>
                                        @endif
                                    </dt>
                                    <dd class="numero text-sm font-medium">{{ $resumo['faixa_taxa']['formatado'] ?? 'Sem dado publicado' }}</dd>
                                </div>
                            </dl>

                            <x-slot:acoes>
                                <label class="flex min-h-11 flex-1 cursor-pointer items-center gap-2 text-sm text-tinta">
                                    <input
                                        type="checkbox"
                                        data-marca-checkbox
                                        value="{{ $marca->slug }}"
                                        class="size-5 shrink-0 accent-[var(--cor-aferido)]"
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

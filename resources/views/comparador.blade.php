{{--
    O comparador (etapa 07).

    A tela faz quatro perguntas, nesta ordem, e a ordem e a decisao mais
    importante do arquivo:

      1. Faturamento mensal — o unico numero que todo lojista sabe de cabeca.
      2. Mix de vendas — o que de fato decide o resultado, entregue por botao
         de segmento para nao virar um formulario de quatro percentuais.
      3. Prazo de recebimento — a quinta dimensao da chave da regra 1.
      4. Marcas, ou "Escolha por mim" para quem nao quer escolher.

    Regra 9: nada disto consulta o banco. O catalogo e o JSON estatico servido
    de /dados/comparador.json e a conta roda no navegador, nos gemeos em
    JavaScript do motor da etapa 05.
--}}
<x-layouts.site
    titulo="Comparador de taxas de maquininhas"
    descricao="Compare o custo real das maquininhas de cartão para o seu faturamento e o seu mix de vendas. Taxas com a fonte e a data em que foram conferidas."
    :navegacao="$navegacao ?? []"
    :atualizado-em="$atualizadoEm"
    :scripts="['resources/js/comparador.js']"
>
    <div x-data="comparador('{{ $caminhoDoJson }}')" class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
        <header class="max-w-3xl space-y-3">
            <h1 class="text-manchete">Quanto a maquininha custa para o seu negócio</h1>
            <p class="text-subtitulo text-tinta-suave">
                Responda quatro perguntas e veja o custo mensal de cada marca para o
                <em>seu</em> faturamento e o <em>seu</em> mix de vendas — não para a média de ninguém.
                Toda taxa vem com a página de origem e a data em que foi conferida.
            </p>
        </header>

        @unless ($jsonExiste)
            {{-- Estado normal de um clone novo: /public/dados esta fora do Git. --}}
            <p class="mt-6 rounded-bloco border border-vencido bg-vencido-fundo px-4 py-3 text-sm text-vencido">
                O arquivo de dados ainda não foi gerado neste ambiente. Rode
                <code class="numero">php artisan comparador:gerar-json</code> para criar
                <code class="numero">public{{ $caminhoDoJson }}</code>.
            </p>
        @endunless

        <template x-if="erroDeCarga">
            <p class="mt-6 rounded-bloco border border-vencido bg-vencido-fundo px-4 py-3 text-sm text-vencido">
                Não foi possível carregar a tabela de taxas. <span x-text="erroDeCarga"></span>
            </p>
        </template>

        <form class="mt-8 space-y-8" x-on:submit.prevent>
            {{-- 1 ----------------------------------------------------------- --}}
            <section aria-labelledby="passo-faturamento" class="rounded-bloco border border-regua bg-papel">
                <div class="border-b border-regua px-4 py-3">
                    <h2 id="passo-faturamento" class="text-titulo">
                        <span class="numero me-1 text-tinta-suave">1.</span> Quanto você fatura por mês
                    </h2>
                </div>

                <div class="grid gap-4 px-4 py-4 sm:grid-cols-2">
                    <x-campo
                        rotulo="Faturamento mensal estimado"
                        nome="faturamento"
                        id="campo-faturamento"
                        prefixo="R$"
                        inputmode="decimal"
                        ajuda="Tudo que entra no mês, incluindo o que você recebe em dinheiro."
                        x-model="faturamentoTexto"
                        x-on:blur="formatarCampos()"
                    />

                    <div class="self-end text-miudo text-tinta-suave">
                        <p>
                            Passa na maquininha:
                            <span class="numero font-medium text-tinta" x-text="resultado ? resultado.resumo.formatado.volume_vendido : '—'"></span>
                        </p>
                        <p>
                            Em dinheiro, sem taxa nenhuma:
                            <span class="numero font-medium text-tinta" x-text="resultado ? resultado.resumo.formatado.fora_da_maquininha : '—'"></span>
                        </p>
                    </div>
                </div>
            </section>

            {{-- 2 ----------------------------------------------------------- --}}
            <section aria-labelledby="passo-mix" class="rounded-bloco border border-regua bg-papel">
                <div class="border-b border-regua px-4 py-3">
                    <h2 id="passo-mix" class="text-titulo">
                        <span class="numero me-1 text-tinta-suave">2.</span> Como seus clientes pagam
                    </h2>
                    <p class="mt-1 text-miudo text-tinta-suave">
                        É o que mais mexe no resultado: a mesma marca ganha ou perde conforme você
                        venda mais em débito ou mais em parcelado.
                    </p>
                </div>

                <div class="space-y-4 px-4 py-4">
                    <div role="group" aria-labelledby="rotulo-segmento" class="space-y-2">
                        <p id="rotulo-segmento" class="text-sm font-medium text-tinta">Escolha o mais parecido com o seu negócio</p>

                        <div class="flex flex-wrap gap-2">
                            <template x-for="s in segmentos" :key="s.chave">
                                <button
                                    type="button"
                                    x-on:click="escolherSegmento(s.chave)"
                                    :aria-pressed="segmento === s.chave"
                                    class="inline-flex min-h-11 items-center rounded-selo border px-3 py-2 text-sm font-medium"
                                    :class="segmento === s.chave
                                        ? 'border-aferido bg-aferido text-papel'
                                        : 'border-contorno bg-transparent text-tinta hover:bg-superficie'"
                                    x-text="s.rotulo"
                                ></button>
                            </template>
                        </div>

                        {{-- O que estes numeros sao, dito na tela e nao so no
                             codigo: palpite editavel, nao dado verificado. A
                             regra 6 vale para taxa, que e afirmacao nossa sobre
                             a marca; o mix e o lojista descrevendo a propria
                             loja. --}}
                        <p class="text-miudo text-tinta-suave">
                            O segmento carrega um mix típico — um ponto de partida, não uma medição.
                            <span x-show="! mixIgualAoSegmento" x-cloak class="text-reportado">Você já ajustou os controles abaixo.</span>
                        </p>
                    </div>

                    <details class="rounded-bloco border border-regua bg-superficie" x-bind:open="! mixIgualAoSegmento">
                        <summary class="inline-flex min-h-11 cursor-pointer items-center px-4 text-sm text-link underline underline-offset-4 hover:no-underline">
                            Ajustar o mix, o ticket médio e as bandeiras
                        </summary>

                        <div class="space-y-4 border-t border-regua px-4 py-4">
                            @foreach ([
                                ['debito', 'Débito', 'Percentual do faturamento em débito'],
                                ['credito_avista', 'Crédito à vista', 'Percentual do faturamento em crédito à vista'],
                                ['credito_parcelado', 'Crédito parcelado', 'Percentual do faturamento em crédito parcelado'],
                                ['pix', 'Pix', 'Percentual do faturamento em Pix'],
                            ] as [$chave, $rotulo, $descricao])
                                <div class="grid grid-cols-[1fr_auto] items-center gap-x-4">
                                    <label for="mix-{{ $chave }}" class="text-sm font-medium text-tinta">{{ $rotulo }}</label>
                                    <output for="mix-{{ $chave }}" class="numero text-sm text-tinta" x-text="mix.{{ $chave }} + '%'"></output>
                                    <input
                                        id="mix-{{ $chave }}"
                                        type="range"
                                        min="0"
                                        max="100"
                                        step="1"
                                        class="col-span-2 mt-1 w-full accent-[var(--cor-aferido)]"
                                        aria-describedby="mix-{{ $chave }}-ajuda"
                                        :value="mix.{{ $chave }}"
                                        x-on:input="ajustarMix('{{ $chave }}', $event.target.value)"
                                    >
                                    <span id="mix-{{ $chave }}-ajuda" class="sr-only">{{ $descricao }}.</span>
                                </div>
                            @endforeach

                            <p class="border-t border-regua pt-3 text-miudo" :class="mixExcedido ? 'text-vencido' : 'text-tinta-suave'">
                                <template x-if="! mixExcedido">
                                    <span>
                                        O que sobra —
                                        <span class="numero font-medium" x-text="percentualEmDinheiro + '%'"></span>
                                        — é dinheiro em espécie, e não passa na maquininha.
                                    </span>
                                </template>
                                <template x-if="mixExcedido">
                                    <span>A soma passou de 100%. Reduza alguma faixa para o resultado voltar.</span>
                                </template>
                            </p>

                            <div class="grid gap-4 border-t border-regua pt-4 sm:grid-cols-2">
                                <x-campo
                                    rotulo="Ticket médio"
                                    nome="ticket"
                                    id="campo-ticket"
                                    prefixo="R$"
                                    inputmode="decimal"
                                    ajuda="Quanto vale uma venda típica. É daqui que sai o número de transações do mês, que algumas taxas cobram por unidade."
                                    x-model="ticketTexto"
                                    x-on:blur="formatarCampos()"
                                />

                                <div class="space-y-1.5">
                                    <label for="campo-parcelas" class="block text-sm font-medium text-tinta">Parcelas mais comuns</label>
                                    <select
                                        id="campo-parcelas"
                                        class="block min-h-11 w-full rounded-selo border border-contorno bg-papel px-3 py-2 text-base text-tinta"
                                        aria-describedby="campo-parcelas-ajuda"
                                        x-model.number="parcelas"
                                    >
                                        {{-- Regra 2: parcelas sao inteiro, de 1 a 21. Aqui
                                             comeca em 2 porque 1x e credito a vista, que ja
                                             tem faixa propria no mix. --}}
                                        @for ($i = 2; $i <= 21; $i++)
                                            <option value="{{ $i }}">{{ $i }}x</option>
                                        @endfor
                                    </select>
                                    <p id="campo-parcelas-ajuda" class="text-miudo text-tinta-suave">
                                        O parcelamento típico das suas vendas a prazo.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-[1fr_auto] items-center gap-x-4 border-t border-regua pt-4">
                                <label for="campo-visa-master" class="text-sm font-medium text-tinta">Visa e Mastercard nas vendas com cartão</label>
                                <output for="campo-visa-master" class="numero text-sm text-tinta" x-text="visaMaster + '%'"></output>
                                <input
                                    id="campo-visa-master"
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="5"
                                    class="col-span-2 mt-1 w-full accent-[var(--cor-aferido)]"
                                    aria-describedby="campo-visa-master-ajuda"
                                    x-model.number="visaMaster"
                                >
                                <p id="campo-visa-master-ajuda" class="col-span-2 mt-1 text-miudo text-tinta-suave">
                                    O resto vai para Elo, Amex e as demais. Várias marcas cobram diferente
                                    fora de Visa e Mastercard, e a SumUp não publica taxa para elas — então
                                    baixar isto pode mandar alguma marca para o bloco “falta dado”.
                                </p>
                            </div>
                        </div>
                    </details>
                </div>
            </section>

            {{-- 3 ----------------------------------------------------------- --}}
            <section aria-labelledby="passo-prazo" class="rounded-bloco border border-regua bg-papel">
                <div class="border-b border-regua px-4 py-3">
                    <h2 id="passo-prazo" class="text-titulo">
                        <span class="numero me-1 text-tinta-suave">3.</span> Quando você quer o dinheiro
                    </h2>
                    <p class="mt-1 text-miudo text-tinta-suave">
                        Receber na hora custa mais caro: o adiantamento já está dentro do percentual.
                    </p>
                </div>

                <div class="space-y-1.5 px-4 py-4">
                    <label for="campo-prazo" class="block text-sm font-medium text-tinta">Prazo de recebimento desejado</label>
                    <select
                        id="campo-prazo"
                        class="block min-h-11 w-full max-w-md rounded-selo border border-contorno bg-papel px-3 py-2 text-base text-tinta"
                        aria-describedby="campo-prazo-ajuda"
                        x-model="prazo"
                    >
                        <option value="">Tanto faz — use o mais barato de cada plano</option>
                        <template x-for="p in listaDePrazos" :key="p.codigo">
                            <option :value="p.codigo" x-text="p.nome"></option>
                        </template>
                    </select>
                    <p id="campo-prazo-ajuda" class="text-miudo text-tinta-suave">
                        Escolhendo um prazo, é aquele ou nada: se a marca não vende naquele prazo,
                        o resultado diz que falta em vez de trocar por outro.
                    </p>
                </div>
            </section>

            {{-- 4 ----------------------------------------------------------- --}}
            <section aria-labelledby="passo-marcas" class="rounded-bloco border border-regua bg-papel">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-regua px-4 py-3">
                    <h2 id="passo-marcas" class="text-titulo">
                        <span class="numero me-1 text-tinta-suave">4.</span> Quais marcas comparar
                    </h2>
                    <x-botao x-on:click="escolhaPorMim()">Escolha por mim</x-botao>
                </div>

                <div class="px-4 py-4">
                    <p class="mb-3 text-miudo text-tinta-suave">
                        <span class="numero" x-text="quantasMarcas"></span> de
                        <span class="numero" x-text="todasAsMarcas.length"></span> marcas selecionadas.
                        “Escolha por mim” compara todas e aponta a mais barata.
                    </p>

                    <fieldset>
                        <legend class="sr-only">Marcas a comparar</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-2 md:grid-cols-3">
                            <template x-for="marca in todasAsMarcas" :key="marca.slug">
                                <div class="flex min-h-11 items-center gap-2">
                                    <input
                                        type="checkbox"
                                        :id="'marca-' + marca.slug"
                                        class="size-5 shrink-0 accent-[var(--cor-aferido)]"
                                        :checked="marcaEscolhida(marca.slug)"
                                        x-on:change="alternarMarca(marca.slug)"
                                    >
                                    <label :for="'marca-' + marca.slug" class="text-sm text-tinta">
                                        <span x-text="marca.nome"></span>
                                        {{-- Regra 4 antes do resultado: quem nao publica
                                             tabela nunca vai ter numero exato aqui. --}}
                                        <template x-if="! marca.publica_tabela">
                                            <span class="block text-miudo text-reportado">não publica tabela</span>
                                        </template>
                                    </label>
                                </div>
                            </template>
                        </div>
                    </fieldset>
                </div>
            </section>

            {{-- Controles finos que quase ninguem mexe, mas que mudam a conta
                 para quem mexe. --}}
            <details class="rounded-bloco border border-regua bg-papel">
                <summary class="inline-flex min-h-11 cursor-pointer items-center px-4 text-sm text-link underline underline-offset-4 hover:no-underline">
                    Detalhes da conta: antecipação, tarifas e horizonte
                </summary>

                <div class="grid gap-4 border-t border-regua px-4 py-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['saques', 'Saques no mês', 'Quantos saques você faz por mês.'],
                        ['teds', 'TEDs no mês', 'Quantas transferências por TED você faz por mês.'],
                        ['pixEnvios', 'Pix enviados no mês', 'Quantos Pix você envia por mês.'],
                    ] as [$modelo, $rotulo, $ajuda])
                        <div class="space-y-1.5">
                            <label for="campo-{{ Str::slug($modelo) }}" class="block text-sm font-medium text-tinta">{{ $rotulo }}</label>
                            <input
                                id="campo-{{ Str::slug($modelo) }}"
                                type="text"
                                inputmode="numeric"
                                class="block min-h-11 w-full rounded-selo border border-contorno bg-papel px-3 py-2 text-base text-tinta"
                                aria-describedby="campo-{{ Str::slug($modelo) }}-ajuda"
                                x-model.number="{{ $modelo }}"
                            >
                            <p id="campo-{{ Str::slug($modelo) }}-ajuda" class="text-miudo text-tinta-suave">{{ $ajuda }}</p>
                        </div>
                    @endforeach

                    <div class="space-y-1.5">
                        <label for="campo-horizonte" class="block text-sm font-medium text-tinta">Diluir a adesão em quantos meses</label>
                        <select
                            id="campo-horizonte"
                            class="block min-h-11 w-full rounded-selo border border-contorno bg-papel px-3 py-2 text-base text-tinta"
                            aria-describedby="campo-horizonte-ajuda"
                            x-model.number="horizonte"
                        >
                            @foreach ([6, 12, 18, 24, 36] as $meses)
                                <option value="{{ $meses }}">{{ $meses }} meses</option>
                            @endforeach
                        </select>
                        <p id="campo-horizonte-ajuda" class="text-miudo text-tinta-suave">
                            A adesão é custo único e a comparação é mensal. Doze meses é o parcelamento
                            que as próprias marcas oferecem.
                        </p>
                    </div>

                    <div class="flex items-start gap-2 sm:col-span-2 lg:col-span-1">
                        <input type="checkbox" id="campo-antecipacao" class="mt-1 size-5 shrink-0 accent-[var(--cor-aferido)]" x-model="antecipacao">
                        <label for="campo-antecipacao" class="text-sm text-tinta">
                            Antecipar os recebíveis
                            <span class="block text-miudo text-tinta-suave">
                                Só cobra onde o prazo ainda não embute o adiantamento — nunca duas vezes.
                            </span>
                        </label>
                    </div>

                    <div class="flex items-start gap-2 sm:col-span-2 lg:col-span-1">
                        <input type="checkbox" id="campo-cupom" class="mt-1 size-5 shrink-0 accent-[var(--cor-aferido)]" x-model="aplicarCupom">
                        <label for="campo-cupom" class="text-sm text-tinta">
                            Considerar cupons de desconto
                            <span class="block text-miudo text-tinta-suave">
                                O cupom desconta a adesão. A taxa pelo nosso link é a mesma do site oficial.
                            </span>
                        </label>
                    </div>
                </div>
            </details>
        </form>

        {{-- Resultado --------------------------------------------------------- --}}
        <section id="resultado" tabindex="-1" aria-labelledby="titulo-resultado" class="mt-12 scroll-mt-4 space-y-8 focus:outline-none">
            <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2 border-b-2 border-regua-forte pb-3">
                <h2 id="titulo-resultado" class="text-titulo">O resultado</h2>

                <div class="flex items-center gap-3">
                    <span class="text-miudo text-tinta-suave" x-show="copiado" x-cloak>Link copiado.</span>
                    <x-botao variante="secundaria" x-on:click="copiarLink()">Copiar link deste resultado</x-botao>
                </div>
            </div>

            <p class="text-sm text-tinta-suave" x-show="carregando">Carregando a tabela de taxas…</p>

            {{-- O anuncio curto para leitor de tela. A tela inteira nao pode ser
                 aria-live: recalcular a cada arrasto de slider viraria ruido. --}}
            <p role="status" aria-live="polite" class="sr-only" x-text="resultado ? (resultado.resumo.melhor
                ? 'Melhor custo: ' + resultado.resumo.melhor.marca + ', ' + resultado.resumo.melhor.formatado.custo_mensal_total + ' por mês.'
                : 'Nenhuma marca fecha a conta com os dados publicados hoje.') : ''"></p>

            <template x-if="! carregando && ! erroDeCarga && ! resultado">
                <p class="rounded-bloco border border-reportado bg-reportado-fundo px-4 py-3 text-sm text-reportado">
                    Ajuste o mix acima: com tudo em dinheiro (ou com a soma passando de 100%)
                    não há venda nenhuma para comparar.
                </p>
            </template>

            {{-- O veredito, quando ha mais de uma marca com numero fechado. --}}
            <template x-if="resultado && resultado.resumo.melhor && resultado.resumo.pior">
                <div class="grid gap-px rounded-bloco border border-regua-forte bg-regua-forte md:grid-cols-3">
                    <div class="bg-aferido-fundo px-4 py-4">
                        <p class="text-etiqueta font-semibold uppercase text-aferido">Melhor custo</p>
                        <p class="mt-1 text-titulo" x-text="resultado.resumo.melhor.marca"></p>
                        <p class="numero text-subtitulo text-aferido" x-text="resultado.resumo.melhor.formatado.custo_mensal_total + ' por mês'"></p>
                        <p class="mt-1 text-miudo text-tinta-suave" x-text="resultado.resumo.melhor.plano"></p>
                    </div>

                    <div class="bg-papel px-4 py-4">
                        <p class="text-etiqueta font-semibold uppercase text-tinta-suave">Mais caro</p>
                        <p class="mt-1 text-titulo" x-text="resultado.resumo.pior.marca"></p>
                        <p class="numero text-subtitulo" x-text="resultado.resumo.pior.formatado.custo_mensal_total + ' por mês'"></p>
                        <p class="mt-1 text-miudo text-tinta-suave" x-text="resultado.resumo.pior.plano"></p>
                    </div>

                    <div class="bg-papel px-4 py-4">
                        <p class="text-etiqueta font-semibold uppercase text-tinta-suave">A diferença</p>
                        <p class="numero mt-1 text-titulo" x-text="resultado.resumo.formatado.diferenca_mensal"></p>
                        <p class="text-miudo text-tinta-suave">
                            por mês —
                            <span class="numero" x-text="resultado.resumo.formatado.diferenca_no_horizonte"></span>
                            em <span class="numero" x-text="resultado.resumo.horizonte_meses"></span> meses.
                        </p>
                    </div>
                </div>
            </template>

            {{-- Regra 4 no layout: so o bloco calculado e ranqueado por preco.
                 Os outros vem depois, em blocos proprios, com o motivo a vista —
                 assim uma mediana de relatos ou um preco de 30 dias nunca
                 disputa a primeira posicao com um numero publicado. --}}
            <x-resultado-comparado
                estado="calculado"
                tom="aferido"
                titulo="Taxa publicada pela marca"
                descricao="Ordenado pelo custo mensal com a adesão diluída. Todo número aqui vem da tabela que a própria marca publica."
                :ranqueado="true"
            />

            <x-resultado-comparado
                estado="promocional"
                tom="reportado"
                titulo="Tabela de entrada, por tempo limitado"
                descricao="Preço verdadeiro, mas com prazo para acabar. Não disputa posição com preço permanente — inclusive porque quase sempre ganharia."
            />

            {{-- Faixa reportada tem bloco proprio, e nao o cartao dos outros: ela
                 nunca tem um numero unico para imprimir. --}}
            <section x-cloak x-show="itensNoEstado('faixa_reportada').length > 0" class="space-y-4">
                <div class="space-y-1">
                    <h3 class="text-titulo">
                        Faixa relatada por lojistas
                        <span class="numero text-tinta-suave" x-text="'(' + itensNoEstado('faixa_reportada').length + ')'"></span>
                    </h3>
                    <p class="max-w-3xl text-miudo text-reportado">
                        Estas marcas não publicam tabela de taxas. O que aparece aqui é o intervalo
                        relatado por lojistas — não é preço de tabela, e o que você vai pagar depende
                        da negociação. Por isso elas não entram na ordenação acima.
                    </p>
                </div>

                <ol class="space-y-4">
                    <template x-for="item in itensNoEstado('faixa_reportada')" :key="item.marca.slug + '-' + (item.plano ? item.plano.id : 0)">
                        <li>
                            <article class="rounded-bloco border-2 border-dashed border-reportado bg-reportado-fundo">
                                <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2 border-b border-reportado px-4 py-3">
                                    <div class="min-w-0">
                                        <h4 class="text-titulo" x-text="item.marca.nome"></h4>
                                        <p class="mt-0.5 text-miudo text-tinta-suave" x-text="item.plano ? item.plano.nome : ''"></p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <template x-if="relatosDe(item)">
                                            <x-etiqueta tom="reportado"><span x-text="relatosDe(item)"></span></x-etiqueta>
                                        </template>
                                        <x-etiqueta tom="reportado" variante="solida">Faixa reportada</x-etiqueta>
                                    </div>
                                </div>

                                {{-- Tres pontas, sempre. Nao existe aqui a chave de um
                                     valor unico, nem no motor nem nesta tela. --}}
                                <dl class="grid grid-cols-1 gap-px border-b border-reportado bg-reportado sm:grid-cols-3">
                                    <div class="bg-reportado-fundo px-4 py-3">
                                        <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Custo mensal — mínimo relatado</dt>
                                        <dd class="numero mt-1 text-subtitulo" x-text="item.comparacao.formatado.custo_mensal_recorrente_minimo"></dd>
                                        <p class="numero text-miudo text-tinta-suave" x-text="item.comparacao.formatado.taxa_efetiva_combinada_minima ?? '—'"></p>
                                    </div>
                                    <div class="bg-reportado-fundo px-4 py-3">
                                        <dt class="text-etiqueta font-semibold uppercase text-reportado">Mediana dos relatos</dt>
                                        <dd class="numero mt-1 text-titulo text-reportado" x-text="item.comparacao.formatado.custo_mensal_recorrente_mediana"></dd>
                                        <p class="numero text-miudo text-tinta-suave" x-text="item.comparacao.formatado.taxa_efetiva_combinada_mediana ?? '—'"></p>
                                    </div>
                                    <div class="bg-reportado-fundo px-4 py-3">
                                        <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Custo mensal — máximo relatado</dt>
                                        <dd class="numero mt-1 text-subtitulo" x-text="item.comparacao.formatado.custo_mensal_recorrente_maximo"></dd>
                                        <p class="numero text-miudo text-tinta-suave" x-text="item.comparacao.formatado.taxa_efetiva_combinada_maxima ?? '—'"></p>
                                    </div>
                                </dl>

                                <p class="border-b border-reportado px-4 py-3 text-miudo text-reportado" x-text="item.motivo"></p>

                                <template x-if="item.comparacao.custo_inicial">
                                    <p class="border-b border-reportado px-4 py-2 text-miudo text-tinta-suave">
                                        Custo inicial:
                                        <span class="numero" x-text="item.comparacao.custo_inicial.formatado.com_cupom"></span>
                                        <template x-if="item.comparacao.custo_inicial.tem_cupom">
                                            <span>
                                                — sem cupom,
                                                <span class="numero line-through" x-text="item.comparacao.custo_inicial.formatado.sem_cupom"></span>
                                            </span>
                                        </template>
                                    </p>
                                </template>

                                <div class="px-4 py-3">
                                    <x-detalhe-do-resultado />
                                </div>
                            </article>
                        </li>
                    </template>
                </ol>
            </section>

            <x-resultado-comparado
                estado="incompleto"
                tom="apagado"
                titulo="Falta dado para este cenário"
                descricao="O total sai parcial porque alguma peça da conta ainda não foi publicada. Preferimos dizer o que falta a chutar o que falta."
            />

            {{-- Marca sem dado nenhum nao some do resultado (regra 4): ela
                 aparece com o motivo, e sem numero. Zero seria mentira. --}}
            <section x-cloak x-show="itensNoEstado('sem_dado_publicado').length > 0" class="space-y-3">
                <h3 class="text-titulo">
                    Sem dado publicado
                    <span class="numero text-tinta-suave" x-text="'(' + itensNoEstado('sem_dado_publicado').length + ')'"></span>
                </h3>
                <ul class="divide-y divide-regua rounded-bloco border border-regua bg-superficie">
                    <template x-for="item in itensNoEstado('sem_dado_publicado')" :key="item.marca.slug">
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-3">
                            <span class="font-medium" x-text="item.marca.nome"></span>
                            <span class="min-w-0 flex-1 text-miudo text-tinta-suave sm:text-end" x-text="item.motivo"></span>
                        </li>
                    </template>
                </ul>
            </section>

            <template x-if="resultado && resultado.catalogo.contem_rascunhos">
                {{-- Regra 10: arquivo de conferencia nunca passa por definitivo. --}}
                <p class="rounded-bloco border border-vencido bg-vencido-fundo px-4 py-3 text-sm text-vencido">
                    Este arquivo de dados foi gerado <strong>com rascunhos</strong>. Ele serve para
                    conferência em ambiente local e não deve ir ao ar.
                </p>
            </template>
        </section>
    </div>
</x-layouts.site>

{{--
    O comparador (etapa 07).

    A tela faz quatro perguntas, nesta ordem, e a ordem e a decisao mais
    importante do arquivo:

      1. Valor vendido na maquininha por mes — o total que passa no cartao e
         no Pix. Desde 17/09/2026 (decisao do Everton) e sempre 100% desse
         valor: nao se supoe mais dinheiro, boleto ou qualquer coisa fora da
         maquininha.
      2. Mix de vendas — o que de fato decide o resultado, entregue por botao
         de segmento para nao virar um formulario de quatro percentuais. Os
         quatro juntos sempre somam 100 do valor do passo 1.
      3. Prazo de recebimento — a quinta dimensao da chave da regra 1.
      4. Marcas, ou "Escolha por mim" para quem nao quer escolher. Nenhuma vem
         marcada de fabrica.

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
                    <h2 id="passo-faturamento" class="flex items-center gap-3 text-cartao sm:text-2xl">
                        <span class="numero-destaque inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-marca text-base text-sobre-marca">1</span> Quanto você vende (ou venderá) por mês na maquininha?
                    </h2>
                </div>

                <div class="px-4 py-4">
                    <x-campo
                        rotulo="Valor vendido na maquininha por mês"
                        nome="faturamento"
                        id="campo-faturamento"
                        prefixo="R$"
                        inputmode="decimal"
                        ajuda="O total que os clientes pagam na maquininha — cartão e Pix. Não inclua dinheiro, boleto ou qualquer forma de pagamento fora dela."
                        x-model="faturamentoTexto"
                        x-on:blur="formatarCampos()"
                    />
                </div>
            </section>

            {{-- 2 ----------------------------------------------------------- --}}
            <section aria-labelledby="passo-mix" class="rounded-bloco border border-regua bg-papel">
                <div class="border-b border-regua px-4 py-3">
                    <h2 id="passo-mix" class="flex items-center gap-3 text-cartao sm:text-2xl">
                        <span class="numero-destaque inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-marca text-base text-sobre-marca">2</span> Como seus clientes pagam
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
                                    class="inline-flex min-h-12 items-center rounded-botao border-[1.5px] px-4 py-2 font-titulo text-[0.9375rem] font-semibold"
                                    :class="segmento === s.chave
                                        ? 'border-tinta bg-tinta text-papel'
                                        : 'border-contorno bg-papel text-tinta hover:bg-superficie'"
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

                    {{-- Aberta a mao, so fecha se a pessoa clicar de novo aqui - trocar
                         de segmento com a caixa aberta nao pode fecha-la sozinho
                         (pedido do Everton, 17/09/2026). Por isso o :open usa um
                         estado proprio (detalhesMixAbertos) e nao mixIgualAoSegmento,
                         que muda toda vez que um preset e escolhido. --}}
                    <details
                        class="rounded-bloco border border-regua bg-superficie"
                        x-bind:open="detalhesMixAbertos"
                        x-on:toggle="detalhesMixAbertos = $event.target.open"
                    >
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
                                        class="col-span-2 mt-1 w-full accent-[var(--cor-tinta)]"
                                        aria-describedby="mix-{{ $chave }}-ajuda"
                                        :value="mix.{{ $chave }}"
                                        x-on:input="ajustarMix('{{ $chave }}', $event.target.value)"
                                    >
                                    <span id="mix-{{ $chave }}-ajuda" class="sr-only">{{ $descricao }}.</span>
                                </div>
                            @endforeach

                            {{-- Os quatro sempre somam 100 (ajustarMix redistribui as outras
                                 faixas sozinho); nao ha mais estado de "passou de 100%" para
                                 avisar aqui. --}}
                            <p class="border-t border-regua pt-3 text-miudo text-tinta-suave">
                                Os quatro juntos somam sempre 100% do valor vendido na maquininha.
                                Subir uma faixa reduz as outras na mesma proporção.
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
                                        class="block min-h-12 w-full rounded-botao border border-contorno bg-papel px-3 py-2 text-base text-tinta"
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
                                    class="col-span-2 mt-1 w-full accent-[var(--cor-tinta)]"
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
                    <h2 id="passo-prazo" class="flex items-center gap-3 text-cartao sm:text-2xl">
                        <span class="numero-destaque inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-marca text-base text-sobre-marca">3</span> Quando você quer o dinheiro
                    </h2>
                    <p class="mt-1 text-miudo text-tinta-suave">
                        Receber na hora custa mais caro: o adiantamento já está dentro do percentual.
                    </p>
                </div>

                <div class="space-y-1.5 px-4 py-4">
                    <label for="campo-prazo" class="block text-sm font-medium text-tinta">Prazo de recebimento desejado</label>
                    <select
                        id="campo-prazo"
                        class="block min-h-12 w-full max-w-md rounded-botao border border-contorno bg-papel px-3 py-2 text-base text-tinta"
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
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b border-regua px-4 py-3">
                    <h2 id="passo-marcas" class="flex items-center gap-3 text-cartao sm:text-2xl">
                        <span class="numero-destaque inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-marca text-base text-sobre-marca">4</span> Quais marcas comparar
                    </h2>
                    {{-- A acao principal da tela: o unico verde do formulario. --}}
                    <x-botao x-on:click="escolhaPorMim()" class="w-full sm:w-auto">Escolha por mim</x-botao>
                </div>

                <div class="px-4 py-4">
                    <p class="mb-3 text-miudo text-tinta-suave">
                        <span class="numero" x-text="quantasMarcas"></span> de
                        <span class="numero" x-text="todasAsMarcas.length"></span> marcas selecionadas.
                        “Escolha por mim” compara todas e aponta a mais barata.
                    </p>

                    <div class="mb-4 flex items-start gap-2 border-b border-regua pb-4">
                        <input type="checkbox" id="campo-cupom" class="mt-1 size-5 shrink-0 accent-[var(--cor-tinta)]" x-model="aplicarCupom">
                        <label for="campo-cupom" class="text-sm text-tinta">
                            Considerar cupons de desconto
                            <span class="block text-miudo text-tinta-suave">
                                O cupom desconta a adesão. A taxa pelo nosso link é a mesma do site oficial.
                            </span>
                        </label>
                    </div>

                    <fieldset>
                        <legend class="sr-only">Marcas a comparar</legend>
                        <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2 md:grid-cols-3">
                            <template x-for="marca in todasAsMarcas" :key="marca.slug">
                                <div class="flex min-h-11 items-center gap-2" x-data="{ logoComErro: false }">
                                    <input
                                        type="checkbox"
                                        :id="'marca-' + marca.slug"
                                        class="size-5 shrink-0 accent-[var(--cor-tinta)]"
                                        :checked="marcaEscolhida(marca.slug)"
                                        x-on:change="alternarMarca(marca.slug)"
                                    >
                                    {{-- Etapa 19: logo em vez do nome — o nome so aparece se a
                                         marca nao tiver logo cadastrado ou a imagem falhar ao
                                         carregar. O <img alt> mantem o nome para leitor de tela
                                         nos dois casos. --}}
                                    <label :for="'marca-' + marca.slug" class="flex min-w-0 items-center gap-2 text-sm text-tinta">
                                        <span class="flex h-9 min-w-0 shrink-0 items-center rounded-botao border border-regua bg-papel px-2">
                                            <template x-if="marca.logo_url && ! logoComErro">
                                                <img
                                                    :src="marca.logo_url"
                                                    :alt="marca.nome"
                                                    class="max-h-6 max-w-24 object-contain"
                                                    height="24"
                                                    loading="lazy"
                                                    decoding="async"
                                                    x-on:error="logoComErro = true"
                                                >
                                            </template>
                                            <template x-if="! marca.logo_url || logoComErro">
                                                <span class="truncate" x-text="marca.nome"></span>
                                            </template>
                                        </span>
                                        {{-- Regra 4 antes do resultado: quem nao publica
                                             tabela nunca vai ter numero exato aqui. --}}
                                        <template x-if="! marca.publica_tabela">
                                            <span class="text-miudo text-reportado">não publica tabela</span>
                                        </template>
                                    </label>
                                </div>
                            </template>
                        </div>
                    </fieldset>
                </div>
            </section>

        </form>

        {{-- Resultado --------------------------------------------------------- --}}
        <section id="resultado" tabindex="-1" aria-labelledby="titulo-resultado" class="mt-12 scroll-mt-4 space-y-8 focus:outline-none">
            <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-3 border-b border-regua-forte pb-4">
                <h2 id="titulo-resultado" class="text-titulo">O resultado</h2>

                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-miudo text-tinta-suave" x-show="copiado" x-cloak>Link copiado.</span>
                    <span class="text-miudo text-reportado" x-show="erroAoCopiar" x-cloak>
                        Não deu para copiar sozinho — o link está na barra de endereços.
                    </span>
                    <x-botao variante="secundaria" x-on:click="copiarLink()">Copiar link deste resultado</x-botao>
                </div>
            </div>

            <p class="text-sm text-tinta-suave" x-show="carregando">Carregando a tabela de taxas…</p>

            <template x-if="! carregando && ! erroDeCarga && resultado && resultado.itens.length === 0">
                <p class="rounded-bloco border border-reportado bg-reportado-fundo px-4 py-3 text-sm text-reportado">
                    Nenhuma marca selecionada ainda. Marque pelo menos uma acima, ou clique em
                    “Escolha por mim”.
                </p>
            </template>

            {{-- O anuncio curto para leitor de tela. A tela inteira nao pode ser
                 aria-live: recalcular a cada arrasto de slider viraria ruido. --}}
            <p role="status" aria-live="polite" class="sr-only" x-text="resultado ? (resultado.resumo.melhor
                ? 'Menor custo: ' + resultado.resumo.melhor.marca + ', ' + resultado.resumo.melhor.formatado.custo_mensal_recorrente + ' por mês.'
                : 'Nenhuma marca fecha a conta com os dados publicados hoje.') : ''"></p>

            <template x-if="! carregando && ! erroDeCarga && ! resultado">
                <p class="rounded-bloco border border-reportado bg-reportado-fundo px-4 py-3 text-sm text-reportado">
                    Ajuste o mix acima: com tudo em dinheiro (ou com a soma passando de 100%)
                    não há venda nenhuma para comparar.
                </p>
            </template>

            {{-- O veredito, quando ha mais de uma marca com numero fechado. Verde
                 so no menor custo (canal de acao); o mais caro fica neutro. --}}
            <template x-if="resultado && resultado.resumo.melhor && resultado.resumo.pior">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-bloco border-[1.5px] border-acao bg-acao-fundo px-4 py-4">
                        <p class="text-etiqueta font-semibold uppercase text-acao">Menor custo</p>
                        <p class="mt-1 font-titulo text-cartao font-semibold" x-text="resultado.resumo.melhor.marca"></p>
                        <p class="numero-destaque text-numero text-acao" x-text="resultado.resumo.melhor.formatado.custo_mensal_recorrente + ' por mês'"></p>
                        <p class="mt-1 text-miudo text-tinta" x-text="resultado.resumo.melhor.plano"></p>
                    </div>

                    <div class="rounded-bloco border border-regua bg-papel px-4 py-4">
                        <p class="text-etiqueta font-semibold uppercase text-tinta-suave">Mais caro</p>
                        <p class="mt-1 font-titulo text-cartao font-semibold" x-text="resultado.resumo.pior.marca"></p>
                        <p class="numero-destaque text-numero" x-text="resultado.resumo.pior.formatado.custo_mensal_recorrente + ' por mês'"></p>
                        <p class="mt-1 text-miudo text-tinta-suave" x-text="resultado.resumo.pior.plano"></p>
                    </div>

                    <div class="rounded-bloco border border-regua bg-papel px-4 py-4">
                        <p class="text-etiqueta font-semibold uppercase text-tinta-suave">A diferença</p>
                        <p class="numero-destaque mt-1 text-numero" x-text="resultado.resumo.formatado.diferenca_mensal"></p>
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

            {{-- So aparece aqui quando a marca nao tem nenhum plano permanente
                 para o cenario — o normal e a promocao virar selo no cartao do
                 plano permanente dela, nunca um cartao proprio (decisao do
                 Everton, 17/09/2026: nunca favorecer o preco promocional). --}}
            <x-resultado-comparado
                estado="promocional"
                expressao="promocionaisOrfas"
                tom="reportado"
                titulo="Tabela de entrada, por tempo limitado — sem plano permanente cadastrado"
                descricao="Preço verdadeiro, mas com prazo para acabar. Só aparece com destaque porque esta marca ainda não tem nenhum plano permanente cadastrado para este cenário."
            />

            {{-- Faixa reportada tem bloco proprio, e nao o cartao dos outros: ela
                 nunca tem um numero unico para imprimir. --}}
            <section x-cloak x-show="itensNoEstado('faixa_reportada').length > 0" class="space-y-4">
                <div class="space-y-1">
                    <h3 class="text-cartao sm:text-2xl">
                        Faixa relatada por lojistas
                        <span class="numero font-normal text-tinta-suave" x-text="'(' + itensNoEstado('faixa_reportada').length + ')'"></span>
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
                            {{-- A forma diz o estado (etapa 14): borda tracejada em ocre,
                                 nunca a borda cheia do cartao de taxa publicada. --}}
                            <article class="overflow-hidden rounded-bloco border-2 border-dashed border-reportado bg-papel">
                                <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2 border-b border-dashed border-reportado bg-reportado-fundo px-4 py-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        {{-- Etapa 15: mesmo quadrado de logo/inicial dos outros blocos. --}}
                                        <div class="flex size-11 shrink-0 items-center justify-center rounded-botao border border-regua bg-papel">
                                            <template x-if="item.marca.logo_url">
                                                <img :src="item.marca.logo_url" alt="" class="max-h-8 max-w-8 object-contain" width="32" height="32" loading="lazy" decoding="async">
                                            </template>
                                            <template x-if="!item.marca.logo_url">
                                                <span class="font-titulo text-lg font-semibold text-tinta-suave" aria-hidden="true" x-text="item.marca.nome.charAt(0)"></span>
                                            </template>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="text-cartao" x-text="item.marca.nome"></h4>
                                            <p class="mt-0.5 text-miudo text-tinta-suave" x-text="item.plano ? item.plano.nome : ''"></p>
                                        </div>
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
                                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 border-b border-dashed border-reportado px-4 py-4 sm:grid-cols-3">
                                    <div>
                                        <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Custo mensal — mínimo relatado</dt>
                                        <dd class="numero mt-1 text-subtitulo font-medium" x-text="item.comparacao.formatado.custo_mensal_recorrente_minimo"></dd>
                                        <p class="numero text-miudo text-tinta-suave" x-text="item.comparacao.formatado.taxa_efetiva_combinada_minima ?? '—'"></p>
                                    </div>
                                    <div>
                                        <dt class="text-etiqueta font-semibold uppercase text-reportado">Mediana dos relatos</dt>
                                        <dd class="numero-destaque mt-1 text-numero text-reportado" x-text="item.comparacao.formatado.custo_mensal_recorrente_mediana"></dd>
                                        <p class="numero text-miudo text-tinta-suave" x-text="item.comparacao.formatado.taxa_efetiva_combinada_mediana ?? '—'"></p>
                                    </div>
                                    <div>
                                        <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Custo mensal — máximo relatado</dt>
                                        <dd class="numero mt-1 text-subtitulo font-medium" x-text="item.comparacao.formatado.custo_mensal_recorrente_maximo"></dd>
                                        <p class="numero text-miudo text-tinta-suave" x-text="item.comparacao.formatado.taxa_efetiva_combinada_maxima ?? '—'"></p>
                                    </div>
                                </dl>

                                <p class="border-b border-dashed border-reportado px-4 py-3 text-miudo text-reportado" x-text="item.motivo"></p>

                                <template x-if="item.comparacao.custo_inicial">
                                    <p class="border-b border-dashed border-reportado px-4 py-2 text-miudo text-tinta-suave">
                                        Custo inicial:
                                        <span class="numero" x-text="item.comparacao.custo_inicial.formatado.com_cupom + ' à vista'"></span>
                                        ou 12x de <span class="numero" x-text="item.formatado.adesao.por_mes"></span>
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
                <h3 class="text-cartao sm:text-2xl">
                    Sem dado publicado
                    <span class="numero font-normal text-tinta-suave" x-text="'(' + itensNoEstado('sem_dado_publicado').length + ')'"></span>
                </h3>
                <ul class="divide-y divide-regua rounded-bloco border border-regua bg-papel">
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

        {{-- Espaçador: some junto com a barra fixa abaixo (mesma condição),
             pra ela não tampar o rodapé da página no celular. --}}
        <div class="h-20 sm:hidden" x-show="melhorItem" x-cloak></div>

        {{-- CTA fixo no celular (etapa 20, bloco B): o 1º colocado sempre a
             um toque, mesmo depois de rolar o resultado inteiro. Só aparece
             com o bloco ranqueado resolvido — sem isso não há "1º colocado". --}}
        <template x-if="melhorItem">
            <div class="fixed inset-x-0 bottom-0 z-40 border-t border-regua bg-papel px-4 py-3 shadow-lg sm:hidden" x-cloak>
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-miudo text-tinta-suave" x-text="melhorItem.marca.nome"></p>
                        <p class="numero-destaque truncate text-base text-tinta" x-text="campoTexto(melhorItem, 'custo_mensal_recorrente') + '/mês'"></p>
                    </div>
                    <a
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-botao border-[1.5px] border-acao bg-acao px-4 text-center font-titulo text-sm font-semibold leading-tight text-sobre-acao"
                        target="_blank"
                        x-bind:href="hrefContratar(melhorItem)"
                        x-bind:rel="temCupomParaContratar(melhorItem) ? 'sponsored nofollow noopener noreferrer' : 'noopener noreferrer'"
                        x-on:click="registrarCliqueContratar(melhorItem)"
                        x-text="textoContratarCurto(melhorItem)"
                    ></a>
                </div>
            </div>
        </template>

        {{-- O modal da tabela promocional (etapa 19). O selo no cartao do plano
             permanente abre isto; aqui e onde as taxas de entrada, o prazo de
             validade e o plano sucessor aparecem — nunca no cartao principal,
             que e sempre o plano permanente. --}}
        <template x-if="itemDaPromocaoAberta">
            {{-- Etapa 20: fecha no clique do fundo (.self), e nao com
                 click.outside no painel. Num clique de mouse de verdade o
                 Alpine renderiza o modal entre um listener e outro do MESMO
                 evento; o click.outside recem-registrado recebia esse clique de
                 abertura ao subir ate a window e fechava o modal na hora - o
                 botao "parecia" nao clicavel. Por script (el.click()) nao
                 acontecia, porque ai os microtasks so rodam no fim do evento. --}}
            <div
                class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/60 p-0 sm:items-center sm:p-4"
                x-on:keydown.escape.window="fecharModalPromocao()"
                x-on:click.self="fecharModalPromocao()"
            >
                <div
                    class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-t-bloco border border-regua bg-papel shadow-xl sm:rounded-bloco"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="'Tabela de entrada da ' + itemDaPromocaoAberta.marca.nome"
                >
                    <div class="flex items-start justify-between gap-4 border-b border-regua px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-etiqueta font-semibold uppercase text-reportado">Tabela de entrada, por tempo limitado</p>
                            <h3 class="mt-1 text-cartao" x-text="itemDaPromocaoAberta.marca.nome + ' — ' + itemDaPromocaoAberta.plano.nome"></h3>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-botao border border-contorno p-2 text-tinta-suave hover:bg-superficie"
                            x-on:click="fecharModalPromocao()"
                        >
                            <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path d="M3 3l10 10M13 3 3 13" stroke-linecap="round"/>
                            </svg>
                            <span class="sr-only">Fechar</span>
                        </button>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        {{-- O aviso principal: quanto dura e o que vem depois, sempre
                             junto — nunca so o numero bonito da promocao. --}}
                        <p class="rounded-bloco border border-reportado bg-reportado-fundo px-4 py-3 text-sm font-medium text-reportado">
                            <span x-text="itemDaPromocaoAberta.motivo"></span>
                            <template x-if="itemDaPromocaoAberta.promocao && itemDaPromocaoAberta.promocao.sucessor">
                                <span>
                                    Depois do período, as taxas passam para as regulares do plano
                                    <strong x-text="itemDaPromocaoAberta.promocao.sucessor.nome"></strong>.
                                </span>
                            </template>
                        </p>

                        {{-- Etapa 20 (bloco B): regras de elegibilidade — o
                             texto que o motor ja associa ao enquadramento
                             promocional do plano (regra 6: nada alem do que
                             o dado sustenta). --}}
                        <template x-if="itemDaPromocaoAberta.enquadramento && itemDaPromocaoAberta.enquadramento.aviso">
                            <p class="text-sm text-tinta-suave" x-text="itemDaPromocaoAberta.enquadramento.aviso"></p>
                        </template>

                        {{-- Promocional x regular, lado a lado — a mesma linha
                             de venda pareada com a taxa que fica depois do
                             periodo, quando a marca tem plano permanente no
                             cenario. Sem ele (promocao orfa), so a coluna
                             promocional aparece. --}}
                        <div>
                            <p class="text-etiqueta font-semibold uppercase text-tinta-suave">Taxas desta tabela de entrada</p>
                            <div class="mt-2 overflow-x-auto">
                                <table class="w-full min-w-[24rem] border-collapse text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-regua-forte">
                                            <th scope="col" class="py-2 pe-3 text-etiqueta font-semibold uppercase text-tinta-suave">Forma de pagamento</th>
                                            <th scope="col" class="py-2 px-3 text-end text-etiqueta font-semibold uppercase text-reportado">Promocional</th>
                                            <template x-if="itemPermanenteDaMarca(itemDaPromocaoAberta.marca.slug)">
                                                <th scope="col" class="py-2 ps-3 text-end text-etiqueta font-semibold uppercase text-tinta-suave">Regular depois</th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(linha, i) in linhasComparadasDaPromocao(itemDaPromocaoAberta)" :key="i">
                                            <tr class="border-b border-regua">
                                                <th scope="row" class="py-2 pe-3 text-start font-normal" x-text="linha.rotulo"></th>
                                                <td class="numero py-2 px-3 text-end font-medium text-reportado" x-text="linha.promocional"></td>
                                                <template x-if="linha.regular !== null">
                                                    <td class="numero py-2 ps-3 text-end text-tinta" x-text="linha.regular"></td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <template x-if="itemDaPromocaoAberta.comparacao && itemDaPromocaoAberta.comparacao.custo_inicial">
                            <p class="text-sm text-tinta-suave">
                                Custo inicial nesta tabela:
                                <span class="numero font-medium text-tinta" x-text="itemDaPromocaoAberta.comparacao.custo_inicial.formatado.com_cupom + ' à vista'"></span>.
                            </p>
                        </template>
                    </div>

                    <div class="border-t border-regua px-5 py-4">
                        <x-botao variante="secundaria" x-on:click="fecharModalPromocao()">Fechar</x-botao>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.site>

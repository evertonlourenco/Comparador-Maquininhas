@props([
    // O estado do bloco: calculado | promocional | incompleto.
    // Faixa reportada NAO passa por aqui de proposito — ela nunca tem um
    // numero unico para imprimir, e reaproveitar este cartao seria abrir a
    // porta para a mediana de relatos aparecer no lugar de uma taxa publicada
    // (regra 4). O bloco dela e escrito a parte, com as tres pontas.
    'estado',
    'tom' => 'neutro',
    'titulo',
    'descricao' => null,
    // Só o bloco ranqueavel numera e destaca melhor/pior.
    'ranqueado' => false,
])

@php
    $bordas = [
        'aferido' => 'border-regua',
        // A forma diz o estado: reportado e tracejado, como a faixa reportada.
        'reportado' => 'border-dashed border-reportado',
        'apagado' => 'border-regua',
        'neutro' => 'border-regua',
    ];
    $borda = $bordas[$tom] ?? $bordas['neutro'];
@endphp

<section x-cloak x-show="itensNoEstado('{{ $estado }}').length > 0" class="space-y-4">
    <div class="space-y-1">
        <h3 class="text-cartao sm:text-2xl">
            {{ $titulo }}
            <span class="numero font-normal text-tinta-suave" x-text="'(' + itensNoEstado('{{ $estado }}').length + ')'"></span>
        </h3>
        @if ($descricao)
            <p class="max-w-3xl text-miudo text-tinta-suave">{{ $descricao }}</p>
        @endif
    </div>

    <ol class="space-y-4">
        <template x-for="(item, indice) in itensNoEstado('{{ $estado }}')" :key="item.marca.slug + '-' + (item.plano ? item.plano.id : 0)">
            <li>
                {{-- Dois canais de cor que nao se misturam (etapa 14). O menor
                     custo ganha a faixa e a borda verdes do cartao destacado do
                     manual — e so o bloco ranqueavel (estado calculado) chega
                     aqui com isso, entao verde e sempre numero publicado. O mais
                     caro nao ganha cor: vermelho e de vencido. --}}
                <article
                    class="overflow-hidden rounded-bloco bg-papel"
                    @if ($ranqueado)
                        x-bind:class="ehMelhor(item) ? 'border-[1.5px] border-acao' : 'border {{ $borda }}'"
                    @else
                        x-bind:class="'border {{ $borda }}'"
                    @endif
                >
                    @if ($ranqueado)
                        <template x-if="ehMelhor(item)">
                            <p class="bg-acao px-4 py-2 text-etiqueta font-semibold uppercase text-sobre-acao">Menor custo no seu cenário</p>
                        </template>
                    @endif

                    <div class="flex flex-wrap items-start gap-x-3 gap-y-2 border-b border-regua px-4 py-3">
                        @if ($ranqueado)
                            <span class="numero-destaque shrink-0 pt-0.5 text-3xl leading-none text-tinta-suave" aria-hidden="true" x-text="indice + 1"></span>
                            <span class="sr-only" x-text="(indice + 1) + 'º lugar.'"></span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <h4 class="text-cartao" x-text="item.marca.nome"></h4>
                            <p class="mt-0.5 text-miudo text-tinta-suave">
                                <span x-text="item.plano ? item.plano.nome : ''"></span>
                                <template x-if="item.marca.adquirente">
                                    {{-- Regra 7: adquirente e transparencia, nunca deduplicacao. --}}
                                    <span> · processa com <span class="text-tinta" x-text="item.marca.adquirente.nome"></span></span>
                                </template>
                            </p>
                        </div>

                        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            @if ($ranqueado)
                                <template x-if="ehPior(item)">
                                    <x-etiqueta tom="neutro">Mais caro</x-etiqueta>
                                </template>
                            @endif
                            <x-etiqueta :tom="$tom">{{ $titulo }}</x-etiqueta>
                        </div>
                    </div>

                    {{-- Os quatro numeros que a etapa 07 pede, na mesma ordem em
                         todo cartao: o que sai por mes, quanto isso da em
                         percentual do que passa na maquininha, o que sai na
                         adesao e o que sobra. Mobile-first: um numero por linha
                         ate caber dois lado a lado sem quebrar o rotulo em tres. --}}
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 border-b border-regua px-4 py-4 min-[30rem]:grid-cols-2 md:grid-cols-4">
                        <div>
                            <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Custo mensal recorrente<template x-if="ehParcial(item)"><span class="text-reportado"> — parcial</span></template></dt>
                            <dd class="numero-destaque mt-1 text-numero" x-text="campoTexto(item, 'custo_mensal_recorrente')"></dd>
                            <p class="mt-1 text-miudo text-tinta-suave">
                                Sem a adesão. Com ela diluída:
                                <span class="numero" x-text="campoTexto(item, 'custo_mensal_total')"></span>
                            </p>
                        </div>

                        <div>
                            <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Taxa efetiva combinada<template x-if="ehParcial(item)"><span class="text-reportado"> — parcial</span></template></dt>
                            <dd class="numero-destaque mt-1 text-numero" x-text="campoTexto(item, 'taxa_efetiva_combinada') ?? '—'"></dd>
                            <p class="mt-1 text-miudo text-tinta-suave">
                                Só as taxas de venda:
                                <span class="numero" x-text="campoTexto(item, 'taxa_efetiva_das_vendas') ?? '—'"></span>
                            </p>
                        </div>

                        <div>
                            <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Custo inicial</dt>
                            <template x-if="item.comparacao && item.comparacao.custo_inicial">
                                <div>
                                    <dd class="numero-destaque mt-1 text-numero" x-text="item.comparacao.custo_inicial.formatado.com_cupom"></dd>
                                    <p class="mt-1 text-miudo text-tinta-suave">
                                        <template x-if="item.comparacao.custo_inicial.tem_cupom">
                                            {{-- Regra 5: o preco de onde o desconto saiu anda junto. --}}
                                            <span>
                                                Sem cupom:
                                                <span class="numero line-through" x-text="item.comparacao.custo_inicial.formatado.sem_cupom"></span>
                                                · cupom
                                                <a
                                                    class="numero font-semibold text-acao underline underline-offset-2 hover:no-underline"
                                                    :href="'/cupom/' + item.marca.slug"
                                                    x-text="item.comparacao.custo_inicial.cupom"
                                                    @click="registrarCliqueCupom(item)"
                                                ></a>
                                            </span>
                                        </template>
                                        <template x-if="! item.comparacao.custo_inicial.tem_cupom">
                                            <span>Sem cupom disponível hoje.</span>
                                        </template>
                                    </p>
                                    <template x-if="item.comparacao.custo_inicial.formatado.parcela_da_marca">
                                        <p class="text-miudo text-tinta-suave">
                                            A marca parcela em
                                            <span class="numero" x-text="item.comparacao.custo_inicial.formatado.parcela_da_marca"></span>.
                                        </p>
                                    </template>
                                </div>
                            </template>
                            <template x-if="! (item.comparacao && item.comparacao.custo_inicial)">
                                {{-- Preco ausente e ausente: zero aqui seria mentira. --}}
                                <dd class="mt-1 text-miudo text-tinta-suave">A marca não publicou o preço do aparelho neste plano.</dd>
                            </template>
                        </div>

                        <div>
                            <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Sobra no mês<template x-if="ehParcial(item)"><span class="text-reportado"> — parcial</span></template></dt>
                            <dd class="numero-destaque mt-1 text-numero" x-text="campoTexto(item, 'sobra_no_mes')"></dd>
                            <p class="mt-1 text-miudo text-tinta-suave">
                                Do faturamento informado, já descontado tudo acima.
                            </p>
                        </div>
                    </dl>

                    {{-- Promocao: o motivo com os dois limites e o plano em que o
                         lojista cai depois andam colados no numero (etapa 05). --}}
                    <template x-if="item.motivo">
                        <p class="border-b border-regua bg-superficie px-4 py-3 text-miudo" :class="'{{ $tom }}' === 'reportado' ? 'text-reportado' : 'text-tinta-suave'" x-text="item.motivo"></p>
                    </template>

                    <template x-if="item.promocao && item.promocao.sucessor">
                        <p class="border-b border-regua px-4 py-2 text-miudo text-tinta-suave">
                            Quando acabar, a conta passa para o plano
                            <span class="font-medium text-tinta" x-text="item.promocao.sucessor.nome"></span>.
                        </p>
                    </template>

                    {{-- O que falta para o cenario fechar. O motor nunca estima:
                         a lista e a diferenca entre um numero e um palpite. --}}
                    <template x-if="item.faltando.length > 0">
                        <div class="border-b border-regua px-4 py-3">
                            <p class="text-miudo font-medium text-tinta">Falta dado para fechar esta conta:</p>
                            <ul class="mt-1 list-disc space-y-0.5 ps-5 text-miudo text-tinta-suave">
                                <template x-for="falta in item.faltando" :key="falta">
                                    <li x-text="falta"></li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <template x-if="item.avisos.length > 0">
                        <ul class="space-y-1 border-b border-regua px-4 py-3 text-miudo text-reportado">
                            <template x-for="aviso in item.avisos" :key="aviso">
                                <li x-text="aviso"></li>
                            </template>
                        </ul>
                    </template>

                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3">
                        <x-detalhe-do-resultado />

                        {{-- Regra 8: o selo de frescor viaja no resultado e e
                             recalculado contra hoje, nao contra o dia em que o
                             JSON nasceu. --}}
                        <p class="text-miudo" :class="item.frescor.nivel === 'fresca' ? 'font-medium text-aferido' : (item.frescor.nivel === 'desatualizada' ? 'text-reportado' : 'text-tinta-suave')">
                            <template x-if="item.frescor.nivel === 'sem_data'"><span>Sem data de verificação</span></template>
                            <template x-if="item.frescor.nivel !== 'sem_data'">
                                <span>
                                    Verificada em <span class="numero" x-text="item.formatado.frescor.data_verificacao"></span>
                                    <template x-if="item.frescor.nivel === 'desatualizada'">
                                        <span> — há <span class="numero" x-text="item.frescor.dias"></span> dias</span>
                                    </template>
                                </span>
                            </template>
                        </p>
                    </div>
                </article>
            </li>
        </template>
    </ol>
</section>

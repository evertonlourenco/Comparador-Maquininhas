@props([
    // O estado do bloco: calculado | promocional | incompleto.
    // Faixa reportada NAO passa por aqui de proposito — ela nunca tem um
    // numero unico para imprimir, e reaproveitar este cartao seria abrir a
    // porta para a mediana de relatos aparecer no lugar de uma taxa publicada
    // (regra 4). O bloco dela e escrito a parte, com as tres pontas.
    'estado',
    // Expressao Alpine que devolve a lista a exibir, no lugar do padrao
    // itensNoEstado($estado). Usado pelo bloco de promocionais orfas (etapa
    // 19): a maioria dos planos promocionais nao aparece mais como cartao
    // proprio (vira selo no cartao do plano permanente da marca — ver
    // promocaoDaMarca() em comparador.js); so quando a marca nao tem nenhum
    // plano permanente cadastrado a promocao continua aparecendo aqui.
    'expressao' => null,
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
    $itensExpr = $expressao ?? "itensNoEstado('{$estado}')";
@endphp

<section x-cloak x-show="{{ $itensExpr }}.length > 0" class="space-y-4">
    <div class="space-y-1">
        <h3 class="text-cartao sm:text-2xl">
            {{ $titulo }}
            <span class="numero font-normal text-tinta-suave" x-text="'(' + {{ $itensExpr }}.length + ')'"></span>
        </h3>
        @if ($descricao)
            <p class="max-w-3xl text-miudo text-tinta-suave">{{ $descricao }}</p>
        @endif
    </div>

    <ol class="space-y-4">
        <template x-for="(item, indice) in {{ $itensExpr }}" :key="item.marca.slug + '-' + (item.plano ? item.plano.id : 0)">
            <li>
                {{-- Dois canais de cor que nao se misturam (etapa 14). O menor
                     custo ganha a faixa e a borda verdes do cartao destacado do
                     manual — e so o bloco ranqueavel (estado calculado) chega
                     aqui com isso, entao verde e sempre numero publicado. O mais
                     caro nao ganha cor: vermelho e de vencido. --}}
                <article
                    class="elevavel scroll-mt-4 overflow-hidden rounded-bloco bg-papel focus:outline-none"
                    tabindex="-1"
                    x-bind:id="idDoCartaoResultado(item)"
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

                        {{-- O mesmo quadrado do cartao de marca (etapa 15): logo se
                             tiver, senao a inicial — nunca um placeholder generico. --}}
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-botao border border-regua bg-superficie">
                            <template x-if="item.marca.logo_url">
                                <img :src="item.marca.logo_url" alt="" class="max-h-8 max-w-8 object-contain" width="32" height="32" loading="lazy" decoding="async">
                            </template>
                            <template x-if="!item.marca.logo_url">
                                <span class="font-titulo text-lg font-semibold text-tinta-suave" aria-hidden="true" x-text="item.marca.nome.charAt(0)"></span>
                            </template>
                        </div>

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
                            <template x-if="ehParcial(item)">
                                <x-etiqueta tom="apagado">Parcial</x-etiqueta>
                            </template>
                            <x-etiqueta :tom="$tom">{{ $titulo }}</x-etiqueta>
                            {{-- Etapa 19: o plano permanente nunca fica escondido atras do
                                 promocional — e o inverso: aqui so um selo aponta que a marca
                                 tambem tem tabela de entrada, e o numero de cima e sempre o
                                 permanente (pedido do Everton, 17/09/2026: nunca favorecer
                                 promocao, sempre mostrar a taxa que fica). --}}
                            {{-- item.estado !== 'promocional': o proprio cartao promocional
                                 orfao (sem plano permanente) nao ganha selo apontando para
                                 si mesmo. --}}
                            <template x-if="item.estado !== 'promocional' && promocaoDaMarca(item.marca.slug)">
                                <button
                                    type="button"
                                    class="inline-flex min-h-8 items-center rounded-full border-[1.5px] border-reportado bg-reportado-fundo px-3 text-etiqueta font-semibold uppercase text-reportado hover:opacity-80"
                                    x-on:click="abrirModalPromocao(item.marca.slug)"
                                >
                                    Oferece taxas promocionais
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Etapa 20 (bloco B): a taxa por forma de pagamento em
                         chips legiveis, uma linha so — nao mais escondida
                         dentro de "Ver simulação". A condicao (Pix a 0% com a
                         chave ativada, por exemplo) continua no mesmo "?" que
                         a tabela detalhada ja usava. --}}
                    <div class="flex flex-wrap items-center gap-2 border-b border-regua px-4 py-3">
                        <template x-for="(linha, i) in item.vendas.filter((l) => ! l.falta)" :key="i">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-regua bg-superficie px-2.5 py-1 text-miudo text-tinta">
                                <span x-text="rotuloCurtoDaVenda(linha.venda)"></span>
                                <span class="numero font-semibold" x-text="linha.percentual_formatado"></span>
                                <template x-if="linha.condicao">
                                    <span class="relative" x-data="{ aberta: false }" x-on:mouseenter="aberta = true" x-on:mouseleave="aberta = false" x-on:click.outside="aberta = false" x-on:keydown.escape="aberta = false">
                                        <button
                                            type="button"
                                            class="inline-flex size-4 items-center justify-center rounded-full border border-contorno text-[0.65rem] font-semibold leading-none text-tinta-suave hover:border-link hover:text-link"
                                            x-on:click="aberta = true"
                                            x-bind:aria-expanded="aberta"
                                            aria-label="Condição desta taxa"
                                        >?</button>
                                        <span
                                            x-cloak
                                            x-show="aberta"
                                            role="tooltip"
                                            class="absolute bottom-full left-1/2 z-10 mb-2 w-56 -translate-x-1/2 rounded-botao border border-regua bg-papel px-3 py-2 text-start font-sans text-miudo text-tinta shadow-lg"
                                            x-text="linha.condicao"
                                        ></span>
                                    </span>
                                </template>
                            </span>
                        </template>
                    </div>

                    {{-- Dois numeros so (decisao do Everton, 18/09/2026): sem
                         "sobra no mes", sem "taxa efetiva combinada" (mistura
                         adesao e mensalidade no percentual — a confusao que
                         ele apontou). item.formatado.vendas e
                         taxa_efetiva_das_vendas vem da mesma conta
                         (custos.vendas), entao os dois numeros combinam. --}}
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 border-b border-regua px-4 py-4">
                        <div>
                            <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Custo mensal em taxas</dt>
                            <dd class="numero-destaque mt-1 text-numero" x-text="item.formatado.vendas"></dd>
                        </div>

                        <div>
                            <dt class="text-etiqueta font-semibold uppercase text-tinta-suave">Taxa média</dt>
                            <dd class="numero-destaque mt-1 text-numero" x-text="campoTexto(item, 'taxa_efetiva_das_vendas') ?? '—'"></dd>
                        </div>
                    </dl>

                    {{-- Motivo, sucessor, o que falta e avisos — um bloco so, nao
                         quatro barras empilhadas (revisao de layout, etapa 19: o
                         cartao estava denso demais com uma faixa inteira para
                         cada frase). --}}
                    {{-- Etapa 20: item.avisos nao sai mais na tela publica. Eram notas
                         tecnicas do motor ("aparelho sem aluguel", mistura de prazo)
                         que poluiam o cartao e, no caso do aluguel, diziam errado —
                         o aparelho fica em comodato. Continuam no resultado do motor,
                         para teste e diagnostico. --}}
                    <template x-if="item.motivo || (item.promocao && item.promocao.sucessor) || item.faltando.length > 0">
                        <div class="space-y-3 border-b border-regua bg-superficie px-4 py-3">
                            <template x-if="item.motivo">
                                <p class="text-miudo" :class="'{{ $tom }}' === 'reportado' ? 'text-reportado' : 'text-tinta-suave'" x-text="item.motivo"></p>
                            </template>

                            <template x-if="item.promocao && item.promocao.sucessor">
                                <p class="text-miudo text-tinta-suave">
                                    Quando acabar, a conta passa para o plano
                                    <span class="font-medium text-tinta" x-text="item.promocao.sucessor.nome"></span>.
                                </p>
                            </template>

                            {{-- O que falta para o cenario fechar. O motor nunca estima:
                                 a lista e a diferenca entre um numero e um palpite. --}}
                            <template x-if="item.faltando.length > 0">
                                <div>
                                    <p class="text-miudo font-medium text-tinta">Falta dado para fechar esta conta:</p>
                                    <ul class="mt-1 list-disc space-y-0.5 ps-5 text-miudo text-tinta-suave">
                                        <template x-for="falta in item.faltando" :key="falta">
                                            <li x-text="falta"></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                        </div>
                    </template>

                    {{-- Bloco de CTA (etapa 20, bloco B), padrao dos grandes
                         comparadores: contratar primeiro, conhecer a marca
                         depois, cupom e adesao sempre a vista. --}}
                    <div class="space-y-3 border-b border-regua bg-superficie px-4 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row">
                            {{-- Regra 5: sem parceria o CTA vai direto ao
                                 site_url, sem passar pela rota de saida — nao
                                 ha clique de afiliado para rastrear. Com
                                 cupom, /ir/{marca} e quem grava o clique em
                                 eventos_cupom e redireciona. --}}
                            <a
                                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-botao border-[1.5px] border-acao bg-acao px-5 py-2.5 text-center font-titulo text-[0.9375rem] font-semibold leading-tight text-sobre-acao shadow-botao transition-[background-color,border-color,box-shadow] duration-150 ease-out hover:border-acao-forte hover:bg-acao-forte active:shadow-none"
                                target="_blank"
                                x-bind:href="hrefContratar(item)"
                                x-bind:rel="temCupomParaContratar(item) ? 'sponsored nofollow noopener noreferrer' : 'noopener noreferrer'"
                                x-on:click="registrarCliqueContratar(item)"
                            >
                                <span x-text="textoContratar(item)"></span>
                                <span class="sr-only">(abre em nova aba)</span>
                            </a>

                            <a
                                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-botao border-[1.5px] border-tinta bg-transparent px-5 py-2.5 text-center font-titulo text-[0.9375rem] font-semibold leading-tight text-tinta transition-colors hover:bg-superficie-forte"
                                x-bind:href="'/maquininha/' + item.marca.slug"
                            >Conhecer a <span x-text="item.marca.nome"></span></a>
                        </div>

                        <template x-if="temCupomParaContratar(item)">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-miudo text-tinta-suave">Cupom:</span>
                                <code class="numero rounded-botao border border-dashed border-contorno bg-papel px-2.5 py-1.5 text-sm font-semibold tracking-wider" x-text="item.cupom.codigo"></code>
                                <button
                                    type="button"
                                    class="inline-flex min-h-8 items-center rounded-botao border border-tinta px-3 text-etiqueta font-semibold uppercase text-tinta hover:bg-superficie-forte"
                                    x-bind:data-copiar="item.cupom.codigo"
                                    x-bind:data-marca="item.marca.slug"
                                    x-bind:data-cupom="item.cupom.codigo"
                                    data-origem="comparador"
                                >Copiar código</button>
                            </div>
                        </template>

                        <template x-if="item.comparacao && item.comparacao.custo_inicial">
                            <p class="text-miudo text-tinta-suave">
                                Adesão a partir de
                                <span class="numero font-medium text-tinta" x-text="item.comparacao.custo_inicial.formatado.com_cupom"></span>
                                <template x-if="temCupomParaContratar(item)">
                                    <span> — sem cupom, <span class="numero line-through" x-text="item.comparacao.custo_inicial.formatado.sem_cupom"></span></span>
                                </template>
                            </p>
                        </template>
                        <template x-if="! (item.comparacao && item.comparacao.custo_inicial)">
                            {{-- Preco ausente e ausente: zero aqui seria mentira. --}}
                            <p class="text-miudo text-tinta-suave">A marca não publicou o preço do aparelho neste plano.</p>
                        </template>

                        <template x-if="temCupomParaContratar(item)">
                            <p class="text-miudo text-tinta-suave">
                                Link de parceiro. <strong class="font-semibold text-tinta">A taxa é a mesma do site oficial.</strong>
                            </p>
                        </template>
                        <template x-if="! temCupomParaContratar(item)">
                            <p class="text-miudo text-tinta-suave">Sem parceria com esta marca — link direto para o site oficial.</p>
                        </template>
                    </div>

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

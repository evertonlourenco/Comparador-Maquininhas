{{--
    A conta aberta de um item do resultado, dentro de um <details>.

    Ela fica fechada porque a tela responde primeiro a "quanto custa" — mas
    fica a um clique porque um comparador que nao mostra a conta e um
    comparador em que se acredita, e a regra 6 existe justamente para o
    contrario disso.

    Serve as duas classes de dado (regra 4): a linha imprime percentual unico
    quando ele foi publicado pela marca, e as tres pontas com o numero de
    relatos quando veio de faixa reportada. Quem decide e a presenca de
    `percentual_mediana` na linha — a mesma defesa que faz faixas_reportadas
    nao ter coluna chamada `percentual`.

    Espera `item` no escopo do Alpine (esta sempre dentro de um x-for).
--}}
<details class="min-w-0 flex-1">
    <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm text-link underline underline-offset-4 hover:no-underline">
        Ver a conta aberta
    </summary>

    <div class="mt-3 space-y-4">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Linhas de venda do cenário — tabela rolável horizontalmente">
            <table class="w-full min-w-[34rem] border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-regua-forte">
                        <th scope="col" class="px-3 py-2 text-etiqueta font-semibold uppercase text-tinta-suave">Linha de venda</th>
                        <th scope="col" class="px-3 py-2 text-etiqueta font-semibold uppercase text-tinta-suave">Prazo</th>
                        <th scope="col" class="px-3 py-2 text-end text-etiqueta font-semibold uppercase text-tinta-suave">Taxa</th>
                        <th scope="col" class="px-3 py-2 text-end text-etiqueta font-semibold uppercase text-tinta-suave">Custo no mês</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(linha, i) in item.vendas" :key="i">
                        <tr class="border-b border-regua even:bg-superficie">
                            <th scope="row" class="px-3 py-2 text-start font-normal">
                                <span x-text="rotuloDaVenda(linha.venda)"></span>
                                <span class="numero block text-miudo text-tinta-suave" x-text="real(linha.venda.valor_mensal)"></span>
                            </th>
                            <td class="px-3 py-2 text-miudo text-tinta-suave" x-text="linha.prazo ? nomeDoPrazo(linha.prazo) : '—'"></td>
                            <td class="numero px-3 py-2 text-end">
                                {{-- Classe A: o percentual publicado. --}}
                                <template x-if="linha.percentual_formatado">
                                    <span class="inline-flex items-center justify-end gap-1.5">
                                        <span x-text="linha.percentual_formatado"></span>
                                        {{-- Etapa 20: a condicao da taxa (o Pix a 0% com a chave
                                             cadastrada no app, por exemplo) anda colada no numero,
                                             num "?" que abre no toque e no mouse - nunca como frase
                                             solta no cartao. Vale para qualquer marca: o dado e
                                             taxas_divulgadas.condicao. --}}
                                        <template x-if="linha.condicao">
                                            <span class="relative" x-data="{ aberta: false }" x-on:mouseenter="aberta = true" x-on:mouseleave="aberta = false" x-on:click.outside="aberta = false" x-on:keydown.escape="aberta = false">
                                                <button
                                                    type="button"
                                                    class="inline-flex size-5 items-center justify-center rounded-full border border-contorno text-[0.7rem] font-semibold leading-none text-tinta-suave hover:border-link hover:text-link"
                                                    {{-- So abre: no mouse o hover ja abriu, e alternar aqui
                                                         fecharia no mesmo gesto. Fecha ao sair, ao tocar fora
                                                         ou com Esc. --}}
                                                    x-on:click="aberta = true"
                                                    x-bind:aria-expanded="aberta"
                                                    aria-label="Condição desta taxa"
                                                >?</button>
                                                <span
                                                    x-cloak
                                                    x-show="aberta"
                                                    role="tooltip"
                                                    class="absolute bottom-full right-0 z-10 mb-2 w-60 rounded-botao border border-regua bg-papel px-3 py-2 text-start font-sans text-miudo text-tinta shadow-lg"
                                                    x-text="linha.condicao"
                                                ></span>
                                            </span>
                                        </template>
                                    </span>
                                </template>

                                {{-- Classe B: intervalo com a mediana rotulada e o
                                     numero de relatos. Nunca um numero isolado. --}}
                                <template x-if="linha.percentual_mediana_formatado">
                                    <span class="block leading-tight">
                                        <span x-text="linha.percentual_minimo_formatado"></span> a
                                        <span x-text="linha.percentual_maximo_formatado"></span>
                                        <span class="block text-miudo text-reportado">
                                            mediana <span x-text="linha.percentual_mediana_formatado"></span>
                                            · <span x-text="linha.n_relatos"></span> relatos
                                        </span>
                                    </span>
                                </template>

                                <template x-if="linha.falta">
                                    <span class="text-miudo text-tinta-suave">não publicada</span>
                                </template>
                            </td>
                            <td class="numero px-3 py-2 text-end">
                                <template x-if="linha.custo_formatado">
                                    <span x-text="linha.custo_formatado"></span>
                                </template>
                                <template x-if="! linha.custo_formatado && linha.custo_mediana !== undefined">
                                    <span class="block leading-tight">
                                        <span x-text="real(linha.custo_minimo)"></span> a
                                        <span x-text="real(linha.custo_maximo)"></span>
                                        <span class="block text-miudo text-reportado">mediana <span x-text="real(linha.custo_mediana)"></span></span>
                                    </span>
                                </template>
                                <template x-if="! linha.custo_formatado && linha.custo_mediana === undefined">
                                    <span class="text-miudo text-tinta-suave">—</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Custo da conta: so mensalidade (etapa 19, 17/09/2026 — saque, TED
             e Pix da conta digital saíram do escopo do Máquina Certa, que
             compara só custo direto de operar a maquininha). Tarifa nao
             usada nao vira linha de zero. --}}
        <template x-if="Object.keys(item.conta).length > 0">
            <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <template x-for="[chave, valor] in Object.entries(item.conta)" :key="chave">
                    <div class="flex justify-between gap-3 border-b border-regua py-1">
                        <dt class="text-tinta-suave" x-text="({ mensalidade: 'Mensalidade do plano' })[chave] ?? chave"></dt>
                        <dd class="numero" x-text="real(valor)"></dd>
                    </div>
                </template>
            </dl>
        </template>

        {{-- A adesao cheia anda junto da amortizada porque 12 x R$ 16,58 nao da
             R$ 199,00: o arredondamento ao centavo nao pode esconder a conta. --}}
        <template x-if="item.adesao && item.adesao.vigente !== null">
            <p class="text-miudo text-tinta-suave">
                Aparelho <span class="font-medium text-tinta" x-text="item.equipamento ? item.equipamento.nome : ''"></span>:
                adesão de <span class="numero" x-text="item.formatado.adesao.valor_final"></span>,
                diluída em <span class="numero" x-text="item.horizonte_meses"></span> meses
                = <span class="numero" x-text="item.formatado.adesao.por_mes"></span> por mês.
                <template x-if="item.equipamento && item.equipamento.aluguel_mensal > 0">
                    <span>Aluguel de <span class="numero" x-text="real(item.equipamento.aluguel_mensal)"></span> por mês.</span>
                </template>
            </p>
        </template>

    </div>
</details>

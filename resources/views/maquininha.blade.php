{{--
    Página individual de marca (etapa 08): /maquininha/{slug}.

    A ordem das oito seções é decisão de produto, não acaso: primeiro o que
    decide (nota, cupom), depois o que explica (sobre, taxas, aparelhos,
    bandeiras, vantagens), por fim o vídeo e o convite para contratar. Regra 4
    percorre a página inteira: a marca é de uma classe de dado só, e a seção 3
    já chega etiquetada como "taxa divulgada" ou "faixa reportada" — nunca as
    duas ao mesmo tempo.
--}}
@php
    use App\Support\Dinheiro;
@endphp

<x-layouts.site
    :titulo="$marca->nome.': taxas, cupom e maquininhas'"
    :descricao="$metaDescricao"
    :navegacao="$navegacao"
    :canonical="$canonical"
    :schema="$schema"
>
    {{-- 1. Cabeçalho: logo, nota do Reclame Aqui e o cupom em destaque -------- --}}
    <header class="border-b border-regua bg-papel">
        <div class="mx-auto grid w-full max-w-5xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_22rem] lg:items-start">
            <div class="min-w-0 space-y-4">
                <nav aria-label="Você está aqui" class="text-miudo text-tinta-suave">
                    <a href="{{ route('maquininhas.index') }}" class="inline-flex min-h-11 items-center text-link underline underline-offset-4 hover:no-underline">Marcas</a>
                    <span aria-hidden="true"> / </span>{{ $marca->nome }}
                </nav>

                <div class="flex items-center gap-4">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-bloco border border-regua bg-superficie">
                        @if ($marca->logo_url)
                            <img src="{{ $marca->logo_url }}" alt="" class="max-h-12 max-w-12 object-contain" width="48" height="48" loading="lazy" decoding="async">
                        @else
                            <span class="font-titulo text-2xl text-tinta-suave" aria-hidden="true">{{ mb_substr($marca->nome, 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-manchete">{{ $marca->nome }}</h1>
                        @if ($marca->site_url)
                            <a href="{{ $marca->site_url }}" target="_blank" rel="noopener noreferrer"
                               class="text-sm text-link underline underline-offset-4 hover:no-underline">
                                Página oficial<span class="sr-only"> (abre em nova aba)</span>
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Regra 8: a nota do Reclame Aqui é campo manual, com data de consulta e link. --}}
                @if ($marca->reclame_aqui_nota !== null)
                    <p class="text-sm text-tinta-suave">
                        Nota no Reclame Aqui
                        <span class="numero font-medium text-tinta">{{ Dinheiro::numero((float) $marca->reclame_aqui_nota, 1) }}</span><span class="numero">/10</span>
                        @if ($marca->reclame_aqui_consultado_em)
                            · consultada em <span class="numero">{{ Dinheiro::data($marca->reclame_aqui_consultado_em) }}</span>
                        @endif
                        @if ($marca->reclame_aqui_url)
                            · <a href="{{ $marca->reclame_aqui_url }}" target="_blank" rel="noopener noreferrer nofollow"
                                 class="text-link underline underline-offset-2 hover:no-underline">ver página<span class="sr-only"> no Reclame Aqui (abre em nova aba)</span></a>
                        @endif
                    </p>
                @endif

                @if ($marca->adquirente)
                    <p class="text-miudo text-tinta-suave">
                        <span class="sr-only">Adquirente que processa por trás:</span>
                        <span aria-hidden="true">Processa com</span> <span class="font-medium text-tinta">{{ $marca->adquirente->nome }}</span>
                    </p>
                @endif
            </div>

            @if ($cupomDestaque)
                <x-bloco-cupom
                    :codigo="$cupomDestaque->codigo"
                    :marca="$marca->nome"
                    :marca-slug="$marca->slug"
                    origem="marca"
                    :descricao="$cupomDestaque->descricao"
                    :valor="$cupomDestaque->valor"
                    :tipo-desconto="$cupomDestaque->tipo_desconto"
                    :incide-sobre="$cupomDestaque->incide_sobre"
                    :valido-ate="$cupomDestaque->valido_ate"
                    :url="$cupomDestaque->link_afiliado"
                    :condicao="$cupomDestaque->termos"
                    {{-- O verde da pagina e o CTA do fim (secao 8): aqui, navy. --}}
                    variante-botao="marca"
                />
            @endif
        </div>
    </header>

    {{-- 2. Sobre a marca, em texto corrido ------------------------------------ --}}
    <section aria-labelledby="s-sobre" class="mx-auto w-full max-w-5xl space-y-3 px-4 py-8 sm:px-6">
        <h2 id="s-sobre" class="text-titulo">Sobre a {{ $marca->nome }}</h2>
        @if ($marca->descricao)
            <p class="max-w-prose text-tinta">{{ $marca->descricao }}</p>
        @else
            <p class="max-w-prose text-tinta-suave">Ainda não temos uma descrição cadastrada para esta marca.</p>
        @endif
    </section>

    {{-- 3. Tabela de taxas completa, por plano e prazo ------------------------ --}}
    <section aria-labelledby="s-taxas" class="mx-auto w-full max-w-5xl space-y-5 border-t border-regua px-4 py-8 sm:px-6">
        <div class="space-y-2">
            <h2 id="s-taxas" class="text-titulo">Tabela de taxas</h2>
            @if ($marca->publica_tabela)
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Taxas publicadas pela própria marca, por plano e por prazo de recebimento.
                    Cada linha traz a data em que conferimos aquele número.
                </p>
            @else
                <p class="max-w-2xl text-sm text-reportado">
                    A {{ $marca->nome }} não publica tabela de taxas. Os números abaixo são a faixa
                    relatada por lojistas — mínimo, mediana e máximo —, nunca um preço de tabela.
                </p>
            @endif
        </div>

        @if (empty($tabelasDeTaxas))
            <p class="rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta-suave">
                Ainda não há {{ $marca->publica_tabela ? 'taxa publicada' : 'faixa reportada' }} cadastrada para esta marca.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($tabelasDeTaxas as $bloco)
                    <div class="space-y-2">
                        <x-tabela-taxas
                            :classe="$bloco['classe']"
                            :titulo="'Plano '.$bloco['plano']->nome"
                            :linhas="$bloco['linhas']"
                            coluna-rotulo="Linha de venda"
                            :marca-slug="$marca->slug"
                            :marca-nome="$marca->nome"
                        />

                        @if ($bloco['plano']->ehPromocional())
                            <p class="text-miudo text-reportado">
                                Tabela de entrada, com prazo para acabar: vale
                                @if ($bloco['plano']->promocional_dias)
                                    por <span class="numero">{{ $bloco['plano']->promocional_dias }}</span> dias
                                @endif
                                @if ($bloco['plano']->promocional_dias && $bloco['plano']->promocional_valor_processado)
                                    ou até
                                @elseif ($bloco['plano']->promocional_valor_processado)
                                    até
                                @endif
                                @if ($bloco['plano']->promocional_valor_processado)
                                    <span class="numero">{{ Dinheiro::real((float) $bloco['plano']->promocional_valor_processado) }}</span> processados
                                @endif
                                — o que vier antes.
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- 4. Modelos de equipamento: foto, preço e especificações --------------- --}}
    @php
        $paresDeEquipamento = $marca->planos->flatMap(
            fn ($plano) => $plano->equipamentos->map(fn ($equipamento) => ['equipamento' => $equipamento, 'plano' => $plano])
        )->groupBy(fn (array $par) => $par['equipamento']->getKey());
    @endphp

    <section aria-labelledby="s-equipamentos" class="mx-auto w-full max-w-5xl space-y-5 border-t border-regua px-4 py-8 sm:px-6">
        <h2 id="s-equipamentos" class="text-titulo">Modelos de maquininha</h2>

        @if ($paresDeEquipamento->isEmpty())
            <p class="rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta-suave">
                Nenhum modelo cadastrado ainda.
            </p>
        @else
            <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($paresDeEquipamento as $grupo)
                    @php
                        $equipamento = $grupo->first()['equipamento'];
                        $precos = $grupo->pluck('equipamento.pivot.preco_adesao_vigente')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
                        $alugueis = $grupo->pluck('equipamento.pivot.aluguel_mensal')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
                    @endphp
                    <li class="flex flex-col overflow-hidden rounded-bloco border border-regua bg-papel shadow-cartao">
                        <div class="flex aspect-4/3 items-center justify-center border-b border-regua bg-superficie">
                            @if ($equipamento->imagem_url)
                                <img src="{{ $equipamento->imagem_url }}" alt="" class="max-h-full max-w-full object-contain p-4" width="400" height="300" loading="lazy" decoding="async">
                            @else
                                <span class="text-miudo text-tinta-suave">Sem foto</span>
                            @endif
                        </div>
                        <div class="flex-1 space-y-2 p-4">
                            <h3 class="text-cartao">{{ $equipamento->nome }}</h3>
                            <p class="text-miudo text-tinta-suave">{{ $equipamento->tipo->getLabel() }}</p>

                            @if ($precos->isNotEmpty())
                                <p class="text-sm text-tinta-suave">
                                    {{ $precos->unique()->count() > 1 ? 'A partir de' : '' }}
                                    <span class="numero-destaque block text-numero text-tinta">{{ Dinheiro::real($precos->min()) }}</span>
                                    na adesão
                                </p>
                            @else
                                <p class="text-sm text-tinta-suave">Preço de adesão não publicado.</p>
                            @endif

                            @if ($alugueis->isNotEmpty() && $alugueis->max() > 0)
                                <p class="numero text-miudo text-tinta-suave">
                                    Aluguel a partir de {{ Dinheiro::real($alugueis->filter(fn ($v) => $v > 0)->min()) }}/mês
                                </p>
                            @endif

                            <ul class="flex flex-wrap gap-1.5 pt-1">
                                @if ($equipamento->tem_chip_gratis)
                                    <li><x-etiqueta tom="neutro">Chip grátis</x-etiqueta></li>
                                @endif
                                @if ($equipamento->aceita_nfc)
                                    <li><x-etiqueta tom="neutro">Aceita NFC</x-etiqueta></li>
                                @endif
                                @if ($equipamento->imprime_comprovante)
                                    <li><x-etiqueta tom="neutro">Imprime comprovante</x-etiqueta></li>
                                @endif
                                @if ($equipamento->exige_celular)
                                    <li><x-etiqueta tom="neutro">Precisa de celular</x-etiqueta></li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 5. Bandeiras aceitas, com os logos ------------------------------------ --}}
    <section aria-labelledby="s-bandeiras" class="mx-auto w-full max-w-5xl space-y-5 border-t border-regua px-4 py-8 sm:px-6">
        <h2 id="s-bandeiras" class="text-titulo">Bandeiras aceitas</h2>

        @if ($marca->bandeiras->isEmpty())
            <p class="rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta-suave">
                Nenhuma bandeira cadastrada ainda.
            </p>
        @else
            <ul class="flex flex-wrap gap-3">
                @foreach ($marca->bandeiras as $bandeira)
                    <li class="flex items-center gap-2 rounded-botao border border-regua bg-papel px-3 py-2">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-selo border border-regua bg-superficie">
                            @if ($bandeira->logo_url)
                                <img src="{{ $bandeira->logo_url }}" alt="" class="max-h-5 max-w-5 object-contain" width="20" height="20" loading="lazy" decoding="async">
                            @else
                                <span class="text-miudo font-medium text-tinta-suave" aria-hidden="true">{{ mb_substr($bandeira->nome, 0, 1) }}</span>
                            @endif
                        </span>
                        <span class="text-sm">{{ $bandeira->nome }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 6. Vantagens e características ----------------------------------------- --}}
    <section aria-labelledby="s-vantagens" class="mx-auto w-full max-w-5xl space-y-5 border-t border-regua px-4 py-8 sm:px-6">
        <h2 id="s-vantagens" class="text-titulo">Vantagens e características</h2>

        @if (empty($vantagens))
            <p class="rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta-suave">
                Nenhuma característica verificada cadastrada ainda.
            </p>
        @else
            <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($vantagens as $vantagem)
                    <li class="flex items-start gap-2 rounded-bloco border border-regua bg-papel px-3 py-2.5 text-sm">
                        <svg class="mt-0.5 size-4 shrink-0 text-aferido" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path d="M3 8.5 6.5 12 13 4.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>{{ $vantagem }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 7. Espaço para o vídeo do canal ----------------------------------------- --}}
    @if ($marca->youtube_video_id)
        <section aria-labelledby="s-video" class="mx-auto w-full max-w-5xl space-y-3 border-t border-regua px-4 py-8 sm:px-6">
            <h2 id="s-video" class="text-titulo">Vídeo</h2>
            <div class="aspect-video overflow-hidden rounded-bloco border border-regua shadow-cartao">
                <iframe
                    class="size-full"
                    src="https://www.youtube-nocookie.com/embed/{{ $marca->youtube_video_id }}"
                    title="Vídeo sobre {{ $marca->nome }}"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                ></iframe>
            </div>
        </section>
    @endif

    {{-- 8. CTA: o cupom e a economia em reais ----------------------------------- --}}
    <section aria-labelledby="s-cta" class="mx-auto w-full max-w-5xl border-t border-regua px-4 py-10 sm:px-6">
        @if ($cupomDestaque)
            {{-- Etapa 20, bloco E: o verde fica so no botao (o que e clicavel);
                 o bloco em volta e cartao branco, como o de sem cupom abaixo. --}}
            <div class="rounded-bloco border border-regua bg-papel px-4 py-8 text-center shadow-cartao sm:px-10">
                <x-etiqueta tom="parceiro">Desconto parceiro</x-etiqueta>
                <h2 id="s-cta" class="mt-3 text-titulo">Pronto para contratar a {{ $marca->nome }}?</h2>
                <p class="mx-auto mt-3 max-w-prose leading-loose text-tinta">
                    @if ($economia)
                        Use o cupom
                        <code class="numero rounded-botao border border-dashed border-contorno bg-papel px-2 py-1 font-semibold tracking-wider">{{ $cupomDestaque->codigo }}</code>
                        e economize <span class="numero-destaque text-tinta">{{ $economia['formatado'] }}</span>{{ $economia['base'] ? ' '.$economia['base'] : '' }}.
                    @else
                        Use o cupom
                        <code class="numero rounded-botao border border-dashed border-contorno bg-papel px-2 py-1 font-semibold tracking-wider">{{ $cupomDestaque->codigo }}</code>
                        na adesão.
                    @endif
                </p>
                <div class="mt-5">
                    <x-botao
                        :href="$cupomDestaque->link_afiliado"
                        afiliado
                        tamanho="grande"
                        class="w-full sm:w-auto"
                        data-usar-cupom
                        data-marca="{{ $marca->slug }}"
                        data-cupom="{{ $cupomDestaque->codigo }}"
                        data-origem="marca"
                    >
                        Abrir o site da {{ $marca->nome }} com o cupom
                    </x-botao>
                </div>
                <p class="mt-3 text-miudo text-tinta-suave">
                    A taxa pelo nosso link é a mesma do site oficial — o cupom desconta
                    {{ $cupomDestaque->incide_sobre?->value === 'equipamento' ? 'o valor do aparelho' : 'a adesão' }}.
                </p>
            </div>
        @else
            <div class="rounded-bloco border border-regua bg-papel px-4 py-8 text-center shadow-cartao sm:px-10">
                <h2 id="s-cta" class="text-titulo">Pronto para contratar a {{ $marca->nome }}?</h2>
                <p class="mt-2 text-tinta-suave">Não há cupom vigente para esta marca no momento.</p>
                @if ($marca->site_url)
                    <div class="mt-5">
                        <x-botao :href="$marca->site_url" externo tamanho="grande">Abrir o site oficial</x-botao>
                    </div>
                @endif
            </div>
        @endif
    </section>
</x-layouts.site>

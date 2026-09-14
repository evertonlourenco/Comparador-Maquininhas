{{--
    Página de cupom por marca (etapa 09): /cupom/{slug}.

    Otimizada para a busca "cupom NOME_DA_MARCA" — por isso existe fora da
    listagem consolidada, com H1, meta description e dado estruturado
    próprios. Só existe quando a marca tem cupom vigente: sem isso,
    CupomController devolve 404 (a mesma regra que tira a marca de /cupons).
--}}
<x-layouts.site
    :titulo="'Cupom '.$marca->nome.': código e desconto vigente'"
    :descricao="$metaDescricao"
    :navegacao="$navegacao"
    :canonical="$canonical"
    :schema="$schema"
>
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
        <nav aria-label="Você está aqui" class="text-miudo text-tinta-suave">
            <a href="{{ route('cupons.index') }}" class="text-link underline underline-offset-4 hover:no-underline">Cupons</a>
            <span aria-hidden="true"> / </span>{{ $marca->nome }}
        </nav>

        <h1 class="mt-2 text-manchete">Cupom {{ $marca->nome }}</h1>
        <p class="mt-2 max-w-prose text-subtitulo text-tinta-suave">
            @if ($economia)
                Economize {{ $economia['formatado'] }}{{ $economia['base'] ? ' '.$economia['base'] : '' }} usando o código abaixo na adesão da {{ $marca->nome }}.
            @else
                Use o código abaixo na adesão da {{ $marca->nome }}.
            @endif
        </p>

        {{-- Pedido explícito: esta frase não pode ficar implícita em lugar nenhum. --}}
        <p class="mt-4 rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta">
            <strong class="font-medium">As taxas exibidas aqui são exatamente as mesmas do site oficial de cada marca. Nosso link não altera sua taxa — só acrescenta desconto na adesão.</strong>
        </p>

        <div class="mt-6">
            <x-bloco-cupom
                :codigo="$cupom->codigo"
                :marca="$marca->nome"
                :marca-slug="$marca->slug"
                origem="cupom_marca"
                :descricao="$cupom->descricao"
                :valor="$cupom->valor"
                :tipo-desconto="$cupom->tipo_desconto"
                :incide-sobre="$cupom->incide_sobre"
                :valido-ate="$cupom->valido_ate"
                :url="$cupom->link_afiliado"
                :condicao="$cupom->termos"
            />
        </div>

        <section aria-labelledby="s-como-usar" class="mt-10 space-y-3">
            <h2 id="s-como-usar" class="text-titulo">Como usar</h2>
            <ol class="list-decimal space-y-3 ps-5 leading-relaxed text-tinta">
                <li>Copie o código <code class="numero rounded-botao border border-dashed border-contorno bg-papel px-2 py-1 text-sm font-semibold tracking-wider">{{ $cupom->codigo }}</code>.</li>
                <li>Clique em "Abrir {{ $marca->nome }} com o cupom" — o link já leva para a página de contratação da marca.</li>
                <li>
                    Complete o cadastro
                    @if ($cupom->incide_sobre?->value === 'equipamento')
                        e informe o código na compra do aparelho, se o site pedir.
                    @else
                        e informe o código na adesão, se o site pedir.
                    @endif
                </li>
                <li>A taxa cobrada é a mesma da tabela pública da {{ $marca->nome }}; o desconto entra separado.</li>
            </ol>

            @if ($cupom->termos)
                <p class="rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta-suave">
                    <strong class="font-medium text-tinta">Condição deste cupom:</strong> {{ $cupom->termos }}
                </p>
            @endif
        </section>

        <div class="mt-10 border-t border-regua pt-6">
            <a href="{{ route('maquininhas.show', $marca->slug) }}" class="text-link underline underline-offset-4 hover:no-underline">
                Ver taxas, aparelhos e tudo mais sobre a {{ $marca->nome }}
            </a>
        </div>
    </div>
</x-layouts.site>

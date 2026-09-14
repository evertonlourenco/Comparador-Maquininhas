{{--
    Listagem consolidada de cupons ativos (etapa 09): /cupons.

    Cupom vencido some sozinho (regra 5, dentro de x-bloco-cupom) e marca sem
    cupom vigente nem chega a este array — CupomController já filtra. Ordenado
    por maior desconto em reais, a única unidade que compara um cupom
    percentual com um cupom em valor fixo.
--}}
<x-layouts.site
    titulo="Cupons de desconto para maquininha de cartão"
    descricao="Todos os cupons de adesão vigentes hoje, por marca, com código, validade e o desconto em reais. A taxa pelo nosso link é sempre a mesma do site oficial."
    :navegacao="$navegacao"
    :canonical="$canonical"
    :schema="$schema"
>
    <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
        <header class="max-w-3xl space-y-3">
            <h1 class="text-manchete">Cupons de desconto</h1>
            <p class="text-subtitulo text-tinta-suave">
                Todo cupom de adesão vigente hoje, por marca, ordenado pelo maior desconto em reais.
            </p>
        </header>

        {{-- Pedido explícito: esta frase não pode ficar implícita em lugar nenhum. --}}
        <p class="mt-6 rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta">
            <strong class="font-medium">As taxas exibidas aqui são exatamente as mesmas do site oficial de cada marca. Nosso link não altera sua taxa — só acrescenta desconto na adesão.</strong>
        </p>

        @if ($cartoes->isEmpty())
            <p class="mt-8 rounded-bloco border border-regua bg-papel px-4 py-3 text-sm text-tinta-suave">
                Nenhum cupom vigente no momento. Volte em breve.
            </p>
        @else
            <ul class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($cartoes as $cartao)
                    @php [$marca, $cupom, $economia] = [$cartao['marca'], $cartao['cupom'], $cartao['economia']]; @endphp
                    <li class="flex flex-col gap-2">
                        <x-bloco-cupom
                            :codigo="$cupom->codigo"
                            :marca="$marca->nome"
                            :marca-slug="$marca->slug"
                            origem="cupons"
                            :descricao="$cupom->descricao"
                            :valor="$cupom->valor"
                            :tipo-desconto="$cupom->tipo_desconto"
                            :incide-sobre="$cupom->incide_sobre"
                            :valido-ate="$cupom->valido_ate"
                            :url="$cupom->link_afiliado"
                            :condicao="$cupom->termos"
                            {{-- Grade de cupons: nenhum e "a" acao principal, entao
                                 nenhum sai em verde (manual de marca). --}}
                            variante-botao="marca"
                        />
                        <div class="flex flex-wrap gap-x-4 px-1 text-miudo">
                            <a href="{{ route('cupons.show', $marca->slug) }}" class="inline-flex min-h-11 items-center text-link underline underline-offset-4 hover:no-underline">
                                Página deste cupom
                            </a>
                            <a href="{{ route('maquininhas.show', $marca->slug) }}" class="inline-flex min-h-11 items-center text-link underline underline-offset-4 hover:no-underline">
                                Ver a {{ $marca->nome }} completa
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.site>

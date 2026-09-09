@php
    use App\Models\TaxaDivulgada;

    $navegacao = \App\Support\Navegacao::principal('metodologia');
@endphp

<x-layouts.site
    titulo="Metodologia"
    descricao="Como coletamos as taxas de maquininha, com que frequência conferimos cada número, e por que algumas marcas aparecem como faixa reportada em vez de taxa exata."
    :navegacao="$navegacao"
    canonical="{{ url('/metodologia') }}"
>
    <div class="mx-auto w-full max-w-2xl space-y-10 px-4 py-8 sm:px-6">
        <header class="space-y-3">
            <h1 class="text-manchete">Como fazemos as contas</h1>
            <p class="text-subtitulo text-tinta-suave">
                O comparador só serve se cada número puder ser conferido. Esta página explica de
                onde vem cada taxa, com que frequência olhamos de novo, e o que significa cada
                selo que você vê nas tabelas.
            </p>
        </header>

        <section aria-labelledby="s-coleta" class="space-y-3">
            <h2 id="s-coleta" class="text-titulo">De onde vêm os números</h2>
            <p class="text-sm text-tinta">
                Toda taxa publicada (<x-etiqueta tom="aferido">Taxa divulgada</x-etiqueta>) vem da
                leitura manual da página oficial da própria marca — nunca de raspagem automática
                nem de terceiros. Cada linha guarda a URL exata de onde o número foi lido e a data
                em que foi conferido. Se a marca não publica aquele número em lugar nenhum, o campo
                fica vazio: um espaço em branco é honesto, um número errado é risco para você.
            </p>
        </section>

        <section aria-labelledby="s-frequencia" class="space-y-3">
            <h2 id="s-frequencia" class="text-titulo">Com que frequência conferimos</h2>
            <p class="text-sm text-tinta">
                Toda taxa é recadastrada quando a página de origem muda. Internamente, alertamos a
                própria equipe quando um número passa 30 dias sem revisão — antes que o selo abaixo
                degrade. Isso não significa que o número mudou: significa que ainda não voltamos a
                olhar a fonte.
            </p>
        </section>

        <section aria-labelledby="s-selo" class="space-y-3">
            <h2 id="s-selo" class="text-titulo">O que o selo de frescor significa</h2>
            <p class="text-sm text-tinta">
                Toda tabela de taxas mostra a data em que aquele número foi conferido pela última
                vez. Até <span class="numero">{{ TaxaDivulgada::DIAS_ATE_DEGRADAR }}</span> dias depois
                dessa data, o selo aparece como <x-etiqueta tom="aferido">conferida</x-etiqueta>.
                Passado esse prazo, ele degrada sozinho para
                <x-etiqueta tom="vencido">a confirmar</x-etiqueta> — o site não espera ninguém
                lembrar de atualizar o texto: a data é que decide.
            </p>
            <div class="max-w-xs">
                <x-selo-frescor :data="now()->subDays(10)" />
            </div>
        </section>

        <section aria-labelledby="s-faixa" class="space-y-3">
            <h2 id="s-faixa" class="text-titulo">Por que Cielo, Rede, GetNet e Stone aparecem como faixa</h2>
            <p class="text-sm text-tinta">
                Essas quatro marcas não publicam uma tabela de taxas aberta ao público — o preço
                sai só numa proposta comercial, negociada caso a caso. Não temos como citar uma
                fonte que não existe, então não fingimos ter um número exato: mostramos a
                <x-etiqueta tom="reportado">faixa reportada</x-etiqueta> — mínimo, mediana e máximo
                do que lojistas de verdade relataram ter recebido, com o número de relatos ao lado.
                Nunca é exibida como se fosse tabela oficial.
            </p>
            <p class="text-sm text-tinta">
                Esse dado só existe porque lojistas como você enviam a proposta que receberam.
                <a href="{{ route('propostas.create') }}" class="text-link underline underline-offset-4 hover:no-underline">Envie a sua em /enviar-proposta</a>
                — é anônimo, e o relato passa por revisão antes de virar número na tela.
            </p>
        </section>

        <section aria-labelledby="s-comissao" class="space-y-3 rounded-bloco border border-regua bg-superficie p-5">
            <h2 id="s-comissao" class="text-titulo">Comissão e independência do número</h2>
            <p class="text-sm text-tinta">
                Ganhamos comissão quando alguém contrata uma maquininha por um link daqui.
                <strong class="font-medium">A taxa mostrada pelo nosso link é exatamente a mesma do
                site oficial da marca</strong> — não existe taxa paralela nem acordo que baixe o
                número exibido. A comissão também não muda a ordem do resultado: o comparador
                ranqueia sempre pelo menor custo mensal calculado, e uma marca que paga comissão
                maior não sobe de posição por isso.
            </p>
        </section>
    </div>
</x-layouts.site>

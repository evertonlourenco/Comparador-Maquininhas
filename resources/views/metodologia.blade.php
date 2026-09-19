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
            <p class="text-tinta">
                Toda taxa publicada (<x-etiqueta tom="aferido">Taxa divulgada</x-etiqueta>) vem da
                leitura manual da página oficial da própria marca — nunca de raspagem automática
                nem de terceiros. Cada linha guarda a URL exata de onde o número foi lido e a data
                em que foi conferido. Se a marca não publica aquele número em lugar nenhum, o campo
                fica vazio: um espaço em branco é honesto, um número errado é risco para você.
            </p>
        </section>

        <section aria-labelledby="s-conta" class="space-y-3">
            <h2 id="s-conta" class="text-titulo">Como calculamos o custo mensal</h2>
            <p class="text-tinta">
                Você escolhe uma forma de pagamento (débito, crédito à vista, crédito parcelado em
                12x ou uma mistura, na simulação avançada), o valor vendido na maquininha por mês e
                o prazo de recebimento. Aplicamos a taxa publicada de cada plano sobre esse valor e
                ordenamos do menor para o maior custo mensal em taxas — a adesão do aparelho não
                entra na ordenação, aparece à parte em cada cartão.
            </p>
            <p class="text-tinta">
                Quem escolhe um prazo de recebimento só vê planos que recebem nesse prazo. Marcas
                sem plano nesse prazo, ou sem a taxa que a sua conta exige, não somem em silêncio:
                aparecem no fim da lista com o motivo. O Pix cai sempre na hora, então ele é
                mostrado igual em qualquer prazo e bandeira.
            </p>
            <p class="text-tinta">
                A adesão exibida é a que você paga entrando pelo nosso link, já com o desconto do
                parceiro quando a marca tem um. Se você desmarcar "Considerar cupons de desconto",
                a comparação passa a usar a adesão sem desconto, mas o botão de contratar continua
                levando ao link de parceiro.
            </p>
            <p class="text-tinta">
                A nota do Reclame Aqui mostrada em cada cartão é conferida à mão, uma vez por mês,
                com a data da consulta ao lado.
            </p>
        </section>

        <section aria-labelledby="s-frequencia" class="space-y-3">
            <h2 id="s-frequencia" class="text-titulo">Com que frequência conferimos</h2>
            <p class="text-tinta">
                Toda taxa é recadastrada quando a página de origem muda. Internamente, alertamos a
                própria equipe quando um número passa 30 dias sem revisão — antes que o selo abaixo
                degrade. Isso não significa que o número mudou: significa que ainda não voltamos a
                olhar a fonte.
            </p>
        </section>

        <section aria-labelledby="s-selo" class="space-y-3">
            <h2 id="s-selo" class="text-titulo">O que o selo de frescor significa</h2>
            <p class="text-tinta">
                Toda tabela de taxas mostra a data em que aquele número foi conferido pela última
                vez. Até <span class="numero">{{ TaxaDivulgada::DIAS_ATE_DEGRADAR }}</span> dias depois
                dessa data, o selo aparece como <x-etiqueta tom="aferido">conferida</x-etiqueta>.
                Passado esse prazo, ele degrada sozinho para
                <x-etiqueta tom="reportado">a confirmar</x-etiqueta> — o site não espera ninguém
                lembrar de atualizar o texto: a data é que decide.
            </p>
            <div class="max-w-xs">
                <x-selo-frescor :data="now()->subDays(10)" />
            </div>
        </section>

        <section aria-labelledby="s-faixa" class="space-y-3">
            <h2 id="s-faixa" class="text-titulo">Por que Cielo, Rede, GetNet e Stone só aparecem como faixa</h2>
            <p class="text-tinta">
                Essas quatro marcas não publicam uma tabela de taxas aberta ao público — o preço
                sai só numa proposta comercial, negociada caso a caso. Não temos como citar uma
                fonte que não existe, então não fingimos ter um número exato: mostramos a
                <x-etiqueta tom="reportado">faixa reportada</x-etiqueta> — mínimo, mediana e máximo
                do que lojistas de verdade relataram ter recebido, com o número de relatos ao lado.
                Nunca é exibida como se fosse tabela oficial. Enquanto uma marca ainda não tem
                relatos suficientes, ela não entra na comparação — só voltamos a listá-la quando
                houver número que possamos sustentar.
            </p>
            <p class="text-tinta">
                Esse dado só existe porque lojistas como você enviam a proposta que receberam.
                <a href="{{ route('propostas.create') }}" class="text-link underline underline-offset-4 hover:no-underline">Envie a sua em /enviar-proposta</a>
                — é anônimo, e o relato passa por revisão antes de virar número na tela.
            </p>
        </section>

        <section aria-labelledby="s-comissao" class="space-y-3 rounded-bloco border border-regua bg-papel p-4 shadow-cartao sm:p-6">
            <h2 id="s-comissao" class="text-titulo">Comissão e independência do número</h2>
            <p class="text-tinta">
                Ganhamos comissão quando alguém contrata uma maquininha por um link daqui.
                <strong class="font-medium">A taxa mostrada pelo nosso link é exatamente a mesma do
                site oficial da marca</strong> — não existe taxa paralela nem acordo que baixe o
                número exibido. A comissão também não muda a ordem do resultado: o comparador
                ranqueia sempre pelo menor custo mensal calculado, e uma marca que paga comissão
                maior não sobe de posição por isso.
            </p>
            <p class="text-tinta">
                O pagamento é sempre feito direto à marca, no site dela: não cobramos nada de você,
                e a entrega do equipamento, o suporte e qualquer cobrança são responsabilidade da
                marca. Quando o código de um cupom vale para qualquer afiliado (e por isso não
                creditaria a nossa comissão se digitado no site oficial), não o exibimos: o
                desconto vale pelo botão de contratar.
            </p>
        </section>
    </div>
</x-layouts.site>

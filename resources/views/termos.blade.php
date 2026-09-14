@php
    $navegacao = [];
    $razaoSocial = config('services.legal.razao_social');
@endphp

<x-layouts.site
    titulo="Termos de Uso"
    descricao="As regras de uso do comparador: caráter informativo, links de afiliado e o formulário de proposta recebida."
    :navegacao="$navegacao"
    canonical="{{ url('/termos') }}"
>
    <div class="mx-auto w-full max-w-2xl space-y-10 px-4 py-8 sm:px-6">
        <header class="space-y-3">
            <h1 class="text-manchete">Termos de Uso</h1>
            <p class="text-subtitulo text-tinta-suave">
                Última atualização em <time datetime="{{ now()->toDateString() }}" class="numero">{{ \App\Support\Dinheiro::data(now()) }}</time>.
            </p>
        </header>

        <section aria-labelledby="s-natureza" class="space-y-3">
            <h2 id="s-natureza" class="text-titulo">O que este site é</h2>
            <p class="text-tinta">
                {{ $razaoSocial ?: 'O responsável por este site' }} mantém este comparador com
                caráter informativo, para ajudar microempreendedores a estimar o custo mensal de
                diferentes maquininhas de cartão. Não somos uma instituição financeira, não
                processamos pagamentos e não representamos nenhuma marca listada.
            </p>
        </section>

        <section aria-labelledby="s-isencao" class="space-y-3">
            <h2 id="s-isencao" class="text-titulo">O comparador não decide por você</h2>
            <p class="text-tinta">
                Os cálculos usam as taxas e condições que cadastramos, com fonte e data de
                verificação — veja a <a href="{{ route('metodologia') }}" class="text-link underline underline-offset-4 hover:no-underline">metodologia</a>.
                Ainda assim, a proposta final de qualquer marca pode variar por negociação,
                faturamento declarado ou mudança de tabela depois da nossa última conferência. A
                decisão de contratar é sempre sua, e recomendamos confirmar os números diretamente
                com a marca antes de assinar qualquer contrato.
            </p>
        </section>

        <section aria-labelledby="s-afiliado" class="space-y-3">
            <h2 id="s-afiliado" class="text-titulo">Links de afiliado</h2>
            <p class="text-tinta">
                Alguns links deste site são de afiliado: se você contratar uma maquininha por eles,
                podemos receber uma comissão da marca, sem custo adicional para você. A taxa
                mostrada pelo nosso link é exatamente a mesma do site oficial da marca — a
                comissão não altera o número exibido nem a ordem do resultado do comparador.
            </p>
        </section>

        <section aria-labelledby="s-proposta" class="space-y-3">
            <h2 id="s-proposta" class="text-titulo">Formulário de proposta recebida</h2>
            <p class="text-tinta">
                Ao enviar uma proposta em <a href="{{ route('propostas.create') }}" class="text-link underline underline-offset-4 hover:no-underline">/enviar-proposta</a>,
                você declara que o relato é verdadeiro e autoriza seu uso agregado e anônimo. O
                envio passa por revisão humana antes de qualquer número entrar no site — não há
                garantia de que um relato específico vire faixa publicada, nem prazo para essa
                revisão.
            </p>
        </section>

        <section aria-labelledby="s-mudancas" class="space-y-3">
            <h2 id="s-mudancas" class="text-titulo">Mudanças nestes termos</h2>
            <p class="text-tinta">
                Podemos atualizar estes termos quando o site mudar de forma relevante. A data no
                topo desta página sempre indica a versão vigente.
            </p>
        </section>
    </div>
</x-layouts.site>

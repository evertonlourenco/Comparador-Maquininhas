{{--
    Perguntas frequentes (etapa 20, bloco F).

    Separada da metodologia de proposito: a metodologia responde "posso
    confiar nos numeros?" (de onde vem cada taxa, com que frequencia
    conferimos). Esta pagina responde "como eu contrato e o que acontece
    depois?" — publico e busca diferentes. As 10 perguntas e o FAQPage de
    schema.org vem de App\Support\PerguntasFrequentes, fonte unica com o
    bloco do fim da home.
--}}
@php
    use App\Support\Navegacao;
    use App\Support\PerguntasFrequentes;

    $navegacao = Navegacao::principal('faq');
    $perguntas = PerguntasFrequentes::todas();
@endphp

<x-layouts.site
    titulo="Perguntas frequentes"
    descricao="Como usar o cupom, o que acontece quando a taxa promocional termina, se o Pix é grátis e outras dúvidas antes de contratar uma maquininha pelo Máquina Certa."
    :navegacao="$navegacao"
    canonical="{{ url('/perguntas-frequentes') }}"
    :schema="PerguntasFrequentes::schema()"
>
    <div class="mx-auto w-full max-w-2xl space-y-8 px-4 py-8 sm:px-6">
        <header class="space-y-3">
            <h1 class="text-manchete">Perguntas frequentes</h1>
            <p class="text-subtitulo text-tinta-suave">
                As dúvidas mais comuns sobre como o comparador funciona e o que esperar depois de
                contratar. Para saber de onde vem cada número, veja
                <a href="{{ route('metodologia') }}" class="text-link underline underline-offset-4 hover:no-underline">a metodologia</a>.
            </p>
        </header>

        <div class="divide-y divide-regua rounded-bloco border border-regua bg-papel shadow-cartao">
            @foreach ($perguntas as $p)
                <details class="px-4 py-4 sm:px-6">
                    <summary class="cursor-pointer font-titulo text-base font-semibold text-tinta">
                        {{ $p['pergunta'] }}
                    </summary>
                    <div class="mt-3 max-w-2xl text-tinta">{!! $p['resposta'] !!}</div>
                </details>
            @endforeach
        </div>
    </div>
</x-layouts.site>

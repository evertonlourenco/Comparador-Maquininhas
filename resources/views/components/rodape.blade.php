@props([
    'links' => [],
    // Data da ultima geracao do JSON do comparador (regra 9).
    'atualizadoEm' => null,
])

@php
    use App\Support\Dinheiro;

    // Sobre o navy o anel de foco padrao sumiria: aqui ele e claro.
    $foco = 'focus-visible:outline-sobre-marca';
@endphp

{{-- Manual de marca: rodape em navy, com o logo negativo. --}}
<footer class="mt-16 bg-marca text-sobre-marca">
    <div class="mx-auto w-full max-w-5xl space-y-8 px-4 py-10 sm:px-6">
        <div class="space-y-3">
            <img
                src="/marca/logo-horizontal-assinatura-negativo.png"
                srcset="/marca/logo-horizontal-assinatura-negativo.png 1x, /marca/logo-horizontal-assinatura-negativo-2x.png 2x"
                width="593" height="192"
                alt="{{ config('app.name') }}"
                class="-ms-1 h-auto w-[10.5rem]"
                loading="lazy" decoding="async"
            >
            @if ($atualizadoEm)
                <p class="text-miudo text-sobre-marca-suave">
                    Dados atualizados em <time datetime="{{ \Illuminate\Support\Carbon::parse($atualizadoEm)->toDateString() }}" class="numero">{{ Dinheiro::data($atualizadoEm) }}</time>.
                </p>
            @endif
        </div>

        @if (count($links))
            <nav aria-label="Rodapé">
                {{-- Uma coluna no celular: cada link com 44px de alvo de toque. --}}
                <ul class="grid gap-x-6 sm:flex sm:flex-wrap">
                    @foreach ($links as $item)
                        <li>
                            <a
                                href="{{ $item['href'] }}"
                                @if ($item['atributo'] ?? null) {{ $item['atributo'] }} @endif
                                class="inline-flex min-h-11 items-center text-sm text-sobre-marca underline underline-offset-4 hover:no-underline {{ $foco }}"
                            >{{ $item['rotulo'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        {{-- Etapa 20 (bloco D): as tres frases de transparencia que moravam
             aqui (comissao, selo de verificacao, faixa reportada) saíram —
             elas ja estao, com mais detalhe, em /metodologia (secoes
             "Comissão e independência do número", "O que o selo de frescor
             significa" e "Por que ... aparecem como faixa"). O rodape fica
             so com logo, links e a data acima, e o copyright abaixo. --}}
        <p class="border-t border-sobre-marca/20 pt-6 text-miudo text-sobre-marca-suave">
            © <span class="numero">{{ now()->year }}</span> {{ config('app.name') }} · by Monetizando.
        </p>
    </div>
</footer>

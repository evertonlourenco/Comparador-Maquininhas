@props([
    // As paginas da etapa 07 em diante entregam a propria navegacao. Vazio, a
    // barra de links nao e renderizada — link morto no cabecalho e pior que
    // cabecalho sem link.
    'navegacao' => [],
    'inicio' => '/',
])

<header class="border-b border-regua bg-papel">
    <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        {{-- Manual de marca: horizontal com assinatura no cabecalho, minimo de
             140px de largura. O PNG ja traz a area de protecao em volta. --}}
        <a href="{{ $inicio }}" class="-ms-1 block shrink-0">
            <img
                src="/marca/logo-horizontal-assinatura.png"
                srcset="/marca/logo-horizontal-assinatura.png 1x, /marca/logo-horizontal-assinatura-2x.png 2x"
                width="593" height="192"
                alt="{{ config('app.name') }} — página inicial"
                class="so-tema-claro h-auto w-[10.5rem] sm:w-[12.5rem]"
            >
            <img
                src="/marca/logo-horizontal-assinatura-negativo.png"
                srcset="/marca/logo-horizontal-assinatura-negativo.png 1x, /marca/logo-horizontal-assinatura-negativo-2x.png 2x"
                width="593" height="192"
                alt="{{ config('app.name') }} — página inicial"
                class="so-tema-escuro h-auto w-[10.5rem] sm:w-[12.5rem]"
            >
        </a>

        <button
            type="button"
            data-alternar-tema
            aria-pressed="false"
            class="inline-flex size-12 shrink-0 items-center justify-center rounded-botao border border-contorno text-tinta hover:bg-superficie"
        >
            {{-- O rotulo e reescrito pelo JavaScript conforme o tema em vigor. --}}
            <span class="sr-only" data-rotulo-tema>Usar tema escuro</span>
            <svg class="size-5 dark:hidden" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <circle cx="10" cy="10" r="3.5" />
                <path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.6 4.6l1.4 1.4M14 14l1.4 1.4M15.4 4.6 14 6M6 14l-1.4 1.4" stroke-linecap="round" />
            </svg>
            <svg class="hidden size-5 dark:block" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path d="M16.5 12.3A7 7 0 0 1 7.7 3.5a7 7 0 1 0 8.8 8.8Z" stroke-linejoin="round" />
            </svg>
        </button>
    </div>

    @if (count($navegacao))
        <nav aria-label="Seções do site" class="border-t border-regua">
            {{-- No celular os quatro links rolam na horizontal em vez de
                 quebrar em duas linhas: o cabecalho fica com altura fixa. --}}
            <ul class="mx-auto flex w-full max-w-5xl gap-1 overflow-x-auto px-2 [scrollbar-width:none] sm:px-4 [&::-webkit-scrollbar]:hidden">
                @foreach ($navegacao as $item)
                    <li class="shrink-0">
                        <a
                            href="{{ $item['href'] }}"
                            @if ($item['atual'] ?? false) aria-current="page" @endif
                            @class([
                                'inline-flex min-h-12 items-center border-b-2 px-3 font-titulo text-[0.9375rem] font-semibold',
                                'border-tinta text-tinta' => $item['atual'] ?? false,
                                'border-transparent text-tinta-suave hover:text-tinta' => ! ($item['atual'] ?? false),
                            ])
                        >{{ $item['rotulo'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</header>

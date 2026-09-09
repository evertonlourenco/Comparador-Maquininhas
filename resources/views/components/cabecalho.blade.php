@props([
    // As paginas da etapa 07 em diante entregam a propria navegacao. Vazio, a
    // barra de links nao e renderizada — link morto no cabecalho e pior que
    // cabecalho sem link.
    'navegacao' => [],
    'inicio' => '/',
])

<header class="border-b border-regua bg-papel">
    <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="{{ $inicio }}" class="font-titulo text-subtitulo leading-tight sm:text-titulo">
            Comparador <span class="text-aferido">de Maquininhas</span>
        </a>

        <button
            type="button"
            data-alternar-tema
            aria-pressed="false"
            class="inline-flex size-11 shrink-0 items-center justify-center rounded-selo border border-contorno text-tinta hover:bg-superficie"
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
            <ul class="mx-auto flex w-full max-w-5xl gap-1 overflow-x-auto px-2 sm:px-4">
                @foreach ($navegacao as $item)
                    <li class="shrink-0">
                        <a
                            href="{{ $item['href'] }}"
                            @if ($item['atual'] ?? false) aria-current="page" @endif
                            @class([
                                'inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-medium',
                                'border-aferido text-aferido' => $item['atual'] ?? false,
                                'border-transparent text-tinta-suave hover:text-tinta' => ! ($item['atual'] ?? false),
                            ])
                        >{{ $item['rotulo'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</header>

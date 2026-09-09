@props([
    'links' => [],
    // Data da ultima geracao do JSON do comparador (regra 9).
    'atualizadoEm' => null,
])

@php
    use App\Support\Dinheiro;
@endphp

<footer class="mt-12 border-t border-regua-forte bg-superficie">
    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-8 sm:px-6">
        <div class="space-y-2">
            <p class="font-titulo text-subtitulo">Comparador de Maquininhas</p>
            @if ($atualizadoEm)
                <p class="text-miudo text-tinta-suave">
                    Dados atualizados em <time datetime="{{ \Illuminate\Support\Carbon::parse($atualizadoEm)->toDateString() }}" class="numero">{{ Dinheiro::data($atualizadoEm) }}</time>.
                </p>
            @endif
        </div>

        @if (count($links))
            <nav aria-label="Rodapé">
                <ul class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach ($links as $item)
                        <li>
                            <a
                                href="{{ $item['href'] }}"
                                @if ($item['atributo'] ?? null) {{ $item['atributo'] }} @endif
                                class="text-sm text-link underline underline-offset-4 hover:no-underline"
                            >{{ $item['rotulo'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        {{-- As tres frases que sustentam o portal (regras 5, 6 e 4). Elas ficam
             no rodape de toda pagina porque valem para toda pagina. --}}
        <div class="space-y-2 border-t border-regua pt-6 text-miudo text-tinta-suave">
            <p>
                Ganhamos comissão quando alguém contrata por um link daqui.
                <strong class="font-medium text-tinta">A taxa pelo nosso link é a mesma do site oficial</strong> — o que muda é o cupom de desconto na adesão, quando existe.
            </p>
            <p>
                Nenhuma taxa é publicada sem a página de origem e a data em que foi conferida. O selo de verificação degrada sozinho depois de
                <span class="numero">{{ \App\Models\TaxaDivulgada::DIAS_ATE_DEGRADAR }}</span> dias.
            </p>
            <p>
                Marcas que não publicam tabela aparecem com faixa de valores relatados por lojistas, sempre identificada como tal — nunca como número exato.
            </p>
            <p class="pt-2">© <span class="numero">{{ now()->year }}</span> Comparador de Maquininhas.</p>
        </div>
    </div>
</footer>

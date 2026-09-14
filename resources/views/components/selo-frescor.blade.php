@props([
    // Passe direto o que o model ja calcula (regra 8, trait TemFrescor):
    //   :data="$taxa->data_verificacao" :nivel="$taxa->nivel_frescor" :dias="$taxa->dias_desde_verificacao"
    // Sem nivel/dias o componente recalcula pela mesma constante de 45 dias.
    'data' => null,
    'nivel' => null,
    'dias' => null,
    'fonte' => null,
    'fonteRotulo' => 'Ver fonte',
    'compacto' => false,
])

@php
    use App\Models\TaxaDivulgada;
    use App\Support\Dinheiro;
    use Illuminate\Support\Carbon;

    $limite = TaxaDivulgada::DIAS_ATE_DEGRADAR;

    $verificacao = $data instanceof Carbon
        ? $data->copy()
        : ($data ? Carbon::parse($data) : null);

    $dias ??= $verificacao ? (int) $verificacao->copy()->startOfDay()->diffInDays(Carbon::today()) : null;

    $nivel ??= $dias === null ? 'sem_data' : ($dias <= $limite ? 'fresca' : 'desatualizada');

    $tons = [
        'fresca' => 'text-aferido',
        'desatualizada' => 'text-reportado',
        'sem_data' => 'text-tinta-suave',
    ];
@endphp

<span {{ $attributes->class(['inline-flex flex-wrap items-center gap-x-2 gap-y-1 text-miudo', $tons[$nivel]]) }}>
    <span class="inline-flex items-center gap-1.5">
        @if ($nivel === 'fresca')
            {{-- O check do simbolo da marca: o dado foi conferido. Na cor do
                 estado (aferido), nunca no verde, que e canal de acao. --}}
            <svg class="size-3.5 shrink-0" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <path d="M2.25 6.4 4.9 9 9.75 3.25" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        @elseif ($nivel === 'desatualizada')
            {{-- Meio circulo: o dado existe, mas o selo ja degradou. --}}
            <svg class="size-3 shrink-0" viewBox="0 0 12 12" aria-hidden="true">
                <circle cx="6" cy="6" r="4.25" fill="none" stroke="currentColor" stroke-width="1.5" />
                <path d="M6 1.75a4.25 4.25 0 0 1 0 8.5z" fill="currentColor" />
            </svg>
        @else
            <svg class="size-3 shrink-0" viewBox="0 0 12 12" aria-hidden="true">
                <path d="M2 6h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
        @endif

        @if ($nivel === 'sem_data')
            <span>Sem data de verificação</span>
        @else
            <span>
                Verificada em
                <time datetime="{{ $verificacao?->toDateString() }}" class="numero">{{ Dinheiro::data($verificacao) }}</time>
                @unless ($compacto)
                    @if ($nivel === 'desatualizada')
                        — há <span class="numero">{{ $dias }}</span> dias, acima dos <span class="numero">{{ $limite }}</span> do selo
                    @endif
                @endunless
            </span>
        @endif
    </span>

    @if ($fonte)
        <a href="{{ $fonte }}" target="_blank" rel="noopener noreferrer nofollow"
           class="text-link underline underline-offset-2 hover:no-underline">{{ $fonteRotulo }}<span class="sr-only"> (abre em nova aba)</span></a>
    @endif
</span>

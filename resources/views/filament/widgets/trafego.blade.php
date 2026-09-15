@php
    $acessos = $this->getAcessos();
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Tráfego — Cloudflare Analytics">
        @if ($acessos['sem_dado'])
            <div class="flex items-start gap-3 text-sm text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-signal-slash" class="h-5 w-5 shrink-0" />
                <div>
                    <p class="font-medium text-gray-700 dark:text-gray-300">Sem dado.</p>
                    <p>{{ $acessos['motivo'] }}</p>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-1 pr-4 font-medium">Dia</th>
                            <th class="py-1 pr-4 font-medium">Requisições</th>
                            <th class="py-1 font-medium">Visitantes únicos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($acessos['dias'] as $dia)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="py-1 pr-4 font-mono">{{ \Illuminate\Support\Carbon::parse($dia['data'])->format('d/m/Y') }}</td>
                                <td class="py-1 pr-4">{{ number_format($dia['requisicoes'], 0, ',', '.') }}</td>
                                <td class="py-1">{{ number_format($dia['visitantes_unicos'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

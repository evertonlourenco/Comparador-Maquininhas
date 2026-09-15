@php
    $tabelas = $this->getTabelas();
    $entidades = $this->getEntidades();
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Banco de dados">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <h3 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                    Tamanho por tabela
                </h3>
                @if ($tabelas->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Sem dado — disponível só em MySQL/MariaDB.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="py-1 pr-4 font-medium">Tabela</th>
                                    <th class="py-1 pr-4 font-medium">Linhas (aprox.)</th>
                                    <th class="py-1 font-medium">Tamanho</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tabelas as $linha)
                                    <tr class="border-t border-gray-100 dark:border-white/5">
                                        <td class="py-1 pr-4 font-mono">{{ $linha['tabela'] }}</td>
                                        <td class="py-1 pr-4">{{ number_format((int) $linha['linhas'], 0, ',', '.') }}</td>
                                        <td class="py-1">{{ number_format((float) $linha['tamanho_mb'], 2, ',', '.') }} MB</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div>
                <h3 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                    Contagem por entidade
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($entidades as $nome => $total)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="py-1 pr-4">{{ $nome }}</td>
                                    <td class="py-1 text-right font-mono">{{ number_format($total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

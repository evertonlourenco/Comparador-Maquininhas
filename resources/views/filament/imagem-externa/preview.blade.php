{{--
    Etapa 15: pre-visualizacao da busca de imagem por URL. base64 vem em
    memoria (nunca em disco — regra 10, aprovacao antes de gravar); o
    admin so grava algo de verdade ao clicar "Aprovar e salvar" no rodape
    do modal, que chama BuscarImagemPorUrlAction::action().
--}}
<div>
    @if ($erro)
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $erro }}</p>
    @elseif ($base64)
        <div class="flex items-center gap-4">
            <div class="flex size-24 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800">
                <img src="data:image/webp;base64,{{ $base64 }}" alt="Pré-visualização da imagem encontrada" class="max-h-20 max-w-20 object-contain">
            </div>
            <div class="min-w-0 text-sm text-gray-600 dark:text-gray-300">
                <p class="font-medium text-gray-950 dark:text-white">Encontrada — confira antes de aprovar.</p>
                @if ($origem)
                    <p class="mt-1 break-all text-xs">{{ $origem }}</p>
                @endif
            </div>
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Cole a URL e clique em "Buscar" para ver a pré-visualização aqui.</p>
    @endif
</div>

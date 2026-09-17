{{--
    Conteudo do modal "Pendências" (etapa 19). Recebe $pendencias (list<string>)
    e $completa (bool) de AprovarMarcaAction/VerPendenciasDaMarcaAction.
--}}
@if ($completa)
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Nenhuma pendência — esta marca fecha conta nas quatro formas de pagamento
        (débito, crédito à vista, crédito parcelado e Pix), com mensalidade,
        tarifa de Pix recebido, equipamento com foto e logo confirmados.
    </p>
@else
    <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">
        O que falta antes desta marca poder ser aprovada:
    </p>
    <ul class="list-disc space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-200">
        @foreach ($pendencias as $pendencia)
            <li>{{ $pendencia }}</li>
        @endforeach
    </ul>
@endif

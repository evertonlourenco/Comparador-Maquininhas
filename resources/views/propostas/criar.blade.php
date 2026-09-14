{{--
    Etapa 10: /enviar-proposta. O lojista relata a proposta que recebeu de
    uma marca que não publica tabela própria (regra 4) — anônimo, com
    consentimento explícito de uso agregado. O relato nunca vira
    faixa_reportada sozinho: entra pendente de revisão humana no painel
    (regra 10). Ver App\Http\Controllers\PropostaController.
--}}
<x-layouts.site
    titulo="Enviar proposta recebida"
    descricao="Recebeu uma proposta de Cielo, Rede, GetNet ou Stone? Ajude outros lojistas relatando as taxas, de forma anônima e agregada."
    :navegacao="$navegacao"
    canonical="{{ url('/enviar-proposta') }}"
>
    <div class="mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
        <header class="space-y-3">
            <h1 class="text-manchete">Recebeu uma proposta? Ajude outros lojistas</h1>
            <p class="text-subtitulo text-tinta-suave">
                Cielo, Rede, GetNet e Stone não publicam tabela de taxas — o que sabemos delas vem
                de propostas que lojistas como você receberam. O relato é anônimo: não pedimos seu
                nome nem e-mail, só a proposta em si.
            </p>
        </header>

        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-bloco border border-vencido bg-papel px-4 py-3 text-sm text-vencido">
                <p class="font-medium">Confira os campos abaixo:</p>
                <ul class="mt-1 list-disc ps-5">
                    @foreach ($errors->all() as $mensagem)
                        <li>{{ $mensagem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Etapa 12: mascara o formulario inteiro na gravacao de sessao do
             Clarity. O relato ja e anonimo por desenho (sem nome, sem
             e-mail), mas faturamento e mensalidade continuam sendo dado de
             negocio que nao precisa aparecer numa gravacao de tela. --}}
        <form
            method="post"
            action="{{ route('propostas.store') }}"
            enctype="multipart/form-data"
            class="mt-8 space-y-8"
            data-clarity-mask="True"
        >
            @csrf

            <x-campo
                elemento="select"
                rotulo="Marca"
                nome="marca_id"
                obrigatorio
                :opcoes="collect(['' => 'Selecione a marca'])->union($marcas->pluck('nome', 'id'))->all()"
                :valor="old('marca_id')"
            />

            <fieldset class="space-y-4">
                <legend class="text-sm font-medium text-tinta">Taxas da proposta</legend>
                <p class="text-miudo text-tinta-suave">
                    Preencha o que a proposta trouxe. Deixe em branco o que não veio na proposta.
                </p>

                <div data-linhas-taxa class="space-y-4">
                    @foreach ($tiposOperacao as $i => $tipo)
                        <div data-linha-taxa class="flex flex-wrap items-end gap-3 border-b border-regua pb-4 last:border-b-0 last:pb-0">
                            <div class="min-w-[10rem]">
                                <span class="block text-sm font-medium text-tinta">{{ $tipo->getLabel() }}</span>
                                <input type="hidden" name="taxas[{{ $i }}][tipo_operacao]" value="{{ $tipo->value }}">
                            </div>

                            @if ($tipo->permiteParcelamento())
                                <x-campo
                                    rotulo="Parcelas"
                                    nome="taxas[{{ $i }}][parcelas]"
                                    id="taxa-{{ $i }}-parcelas"
                                    tipo="number"
                                    :valor="old('taxas.'.$i.'.parcelas', $tipo->parcelaMinima())"
                                    min="{{ $tipo->parcelaMinima() }}"
                                    max="{{ $tipo->parcelaMaxima() }}"
                                    class="w-24"
                                />
                            @endif

                            <x-campo
                                rotulo="Taxa"
                                nome="taxas[{{ $i }}][percentual]"
                                id="taxa-{{ $i }}-percentual"
                                sufixo="%"
                                inputmode="decimal"
                                :valor="old('taxas.'.$i.'.percentual')"
                                class="w-32"
                            />
                        </div>
                    @endforeach
                </div>

                <template data-modelo-linha-taxa>
                    <div data-linha-taxa class="flex flex-wrap items-end gap-3 border-b border-regua pb-4">
                        <x-campo
                            elemento="select"
                            rotulo="Linha de venda"
                            nome="taxas[__INDICE__][tipo_operacao]"
                            id="taxa-__INDICE__-tipo"
                            :opcoes="collect($tiposOperacao)->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()])->all()"
                            class="min-w-[10rem]"
                        />
                        <x-campo rotulo="Parcelas" nome="taxas[__INDICE__][parcelas]" id="taxa-__INDICE__-parcelas" tipo="number" valor="2" class="w-24" />
                        <x-campo rotulo="Taxa" nome="taxas[__INDICE__][percentual]" id="taxa-__INDICE__-percentual" sufixo="%" inputmode="decimal" class="w-32" />
                    </div>
                </template>

                <button type="button" data-adicionar-linha-taxa class="min-h-11 rounded-selo border border-contorno px-4 text-sm font-medium text-tinta hover:bg-superficie">
                    + Adicionar outra linha
                </button>
            </fieldset>

            <x-campo
                elemento="select"
                rotulo="Prazo de recebimento (se a proposta disse)"
                nome="prazo_recebimento_id"
                :opcoes="collect(['' => 'Não sei / não veio na proposta'])->union($prazos->pluck('nome_exibicao', 'id'))->all()"
                :valor="old('prazo_recebimento_id')"
            />

            <x-campo
                rotulo="Mensalidade"
                nome="mensalidade"
                id="mensalidade"
                prefixo="R$"
                inputmode="decimal"
                ajuda="Deixe em branco se a proposta não falava em mensalidade."
                :valor="old('mensalidade')"
            />

            <x-campo
                rotulo="Data da proposta"
                nome="data_proposta"
                id="data-proposta"
                tipo="date"
                obrigatorio
                :valor="old('data_proposta')"
            />

            <x-campo
                elemento="select"
                rotulo="Estado"
                nome="estado"
                obrigatorio
                :opcoes="collect(['' => 'Selecione'])->union(collect($estados)->mapWithKeys(fn ($uf) => [$uf => $uf]))->all()"
                :valor="old('estado')"
            />

            <x-campo
                elemento="select"
                rotulo="Segmento do seu negócio"
                nome="segmento"
                obrigatorio
                :opcoes="collect(['' => 'Selecione'])->union(collect($segmentos)->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()]))->all()"
                :valor="old('segmento')"
            />

            <x-campo
                rotulo="Faturamento mensal aproximado"
                nome="faturamento_aproximado"
                id="faturamento-aproximado"
                prefixo="R$"
                inputmode="decimal"
                ajuda="Uma estimativa já ajuda — não precisa ser exato."
                :valor="old('faturamento_aproximado')"
            />

            <div class="space-y-1.5">
                <label for="anexo" class="block text-sm font-medium text-tinta">Foto ou PDF da proposta (opcional)</label>
                <input
                    type="file"
                    id="anexo"
                    name="anexo"
                    accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,.pdf"
                    class="block w-full text-sm text-tinta file:mr-3 file:min-h-11 file:rounded-selo file:border file:border-contorno file:bg-papel file:px-3 file:py-2 file:text-sm file:font-medium"
                >
                <p class="text-miudo text-tinta-suave">Máximo 8&nbsp;MB. Usamos só para conferir o relato — não publicamos o arquivo.</p>
            </div>

            <div class="flex items-start gap-3">
                <input type="checkbox" id="consentimento" name="consentimento_uso_agregado" value="1" required class="mt-1 size-5 shrink-0 rounded border-contorno">
                <label for="consentimento" class="text-sm text-tinta">
                    Autorizo o uso agregado e anônimo desta informação para melhorar o comparador —
                    sem meu nome, sem meu contato, nunca publicada como número isolado.
                    <span class="text-vencido" aria-hidden="true">*</span>
                </label>
            </div>

            <x-campo-honeypot />

            <button type="submit" class="min-h-11 rounded-selo bg-aferido px-5 py-3 text-base font-semibold text-papel hover:opacity-90">
                Enviar proposta
            </button>
        </form>
    </div>
</x-layouts.site>

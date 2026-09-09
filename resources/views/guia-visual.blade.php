@php
    use App\Enums\IncideSobre;
    use App\Enums\TipoDesconto;
    use App\Motor\EstadoDoResultado;
    use Illuminate\Support\Carbon;

    $hoje = Carbon::today();

    // Tudo nesta pagina e amostra. Nenhum numero aqui saiu do banco, e o aviso
    // no topo diz isso — a pagina existe para conferir o desenho, nao o dado.
    $cores = [
        ['Papel', 'papel', 'bg-papel', 'Fundo da página'],
        ['Superfície', 'superficie', 'bg-superficie', 'Zebra de tabela, rodapé de cartão'],
        ['Superfície forte', 'superficie-forte', 'bg-superficie-forte', 'Cabeçalho de tabela'],
        ['Tinta', 'tinta', 'bg-tinta', 'Texto e botão principal escuro'],
        ['Tinta suave', 'tinta-suave', 'bg-tinta-suave', 'Metadado, nota de rodapé'],
        ['Régua', 'regua', 'bg-regua', 'Fio de 1px (decorativo)'],
        ['Régua forte', 'regua-forte', 'bg-regua-forte', 'Fio de seção (decorativo)'],
        ['Contorno', 'contorno', 'bg-contorno', 'Borda de campo e de botão'],
        ['Aferido', 'aferido', 'bg-aferido', 'Taxa divulgada, dado fresco'],
        ['Aferido, fundo', 'aferido-fundo', 'bg-aferido-fundo', 'Fundo da etiqueta aferida'],
        ['Reportado', 'reportado', 'bg-reportado', 'Faixa, promoção, condição'],
        ['Reportado, fundo', 'reportado-fundo', 'bg-reportado-fundo', 'Fundo da etiqueta reportada'],
        ['Vencido', 'vencido', 'bg-vencido', 'Cupom fora da validade, erro'],
        ['Vencido, fundo', 'vencido-fundo', 'bg-vencido-fundo', 'Fundo da etiqueta vencida'],
        ['Link', 'link', 'bg-link', 'Link em texto corrido'],
        ['Foco', 'foco', 'bg-foco', 'Anel de foco do teclado'],
    ];

    $taxasDivulgadas = [
        ['rotulo' => 'Débito', 'percentual' => 1.37, 'fixo' => null, 'prazo' => 'D+1'],
        ['rotulo' => 'Pix', 'percentual' => 0.0, 'fixo' => null, 'prazo' => 'Na hora', 'condicao' => 'Exige ativar a chave Pix no aplicativo.'],
        ['rotulo' => 'Crédito à vista', 'percentual' => 3.15, 'fixo' => null, 'prazo' => 'D+1'],
        ['rotulo' => 'Crédito 2x', 'percentual' => 4.79, 'fixo' => null, 'prazo' => 'D+30'],
        ['rotulo' => 'Crédito 6x', 'percentual' => 8.32, 'fixo' => null, 'prazo' => 'D+30'],
        ['rotulo' => 'Crédito 12x', 'percentual' => 13.86, 'fixo' => 0.5, 'prazo' => 'D+30'],
        ['rotulo' => 'Crédito 21x', 'percentual' => null, 'fixo' => null, 'prazo' => 'D+30'],
    ];

    $faixasReportadas = [
        ['rotulo' => 'Débito', 'minimo' => 1.29, 'mediana' => 1.99, 'maximo' => 2.6, 'relatos' => 84],
        ['rotulo' => 'Crédito à vista', 'minimo' => 2.9, 'mediana' => 3.85, 'maximo' => 4.99, 'relatos' => 71],
        ['rotulo' => 'Crédito 12x', 'minimo' => 11.4, 'mediana' => 14.2, 'maximo' => 18.05, 'relatos' => 39],
    ];
@endphp

<x-layouts.site
    titulo="Guia visual"
    descricao="Identidade visual e componentes do Comparador de Maquininhas."
    :indexavel="false"
    :navegacao="[
        ['rotulo' => 'Comparador', 'href' => '/'],
        ['rotulo' => 'Marcas', 'href' => '/guia-visual'],
        ['rotulo' => 'Cupons', 'href' => '/guia-visual'],
        ['rotulo' => 'Guia visual', 'href' => '/guia-visual', 'atual' => true],
    ]"
    :links-rodape="[
        ['rotulo' => 'Metodologia', 'href' => '/guia-visual'],
        ['rotulo' => 'Guia visual', 'href' => '/guia-visual'],
    ]"
    :atualizado-em="$hoje"
>
    <div class="mx-auto w-full max-w-5xl space-y-14 px-4 py-8 sm:px-6 sm:py-12">

        <header class="space-y-4 border-b border-regua-forte pb-8">
            <x-etiqueta tom="neutro">Etapa 06</x-etiqueta>
            <h1 class="text-manchete">Guia visual</h1>
            <p class="max-w-2xl text-subtitulo text-tinta-suave">
                Direção “Boletim”: papel, tinta e fio de 1px. A cor não decora — cada acento
                nomeia um estado que o domínio já definiu, e o desenho existe para o número
                andar sempre colado à sua fonte e à sua data.
            </p>
            <p class="rounded-bloco border border-reportado bg-reportado-fundo px-4 py-3 text-miudo text-reportado">
                <strong class="font-semibold">Todos os números desta página são amostra.</strong>
                Nada aqui saiu do banco: a página existe para conferir o desenho, não o dado.
            </p>
        </header>

        {{-- ------------------------------------------------------- paleta -- --}}
        <section aria-labelledby="s-paleta" class="space-y-5">
            <div class="space-y-2">
                <h2 id="s-paleta" class="text-titulo">Paleta</h2>
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Os hexadecimais abaixo são lidos do CSS em tempo real, então acompanham o tema
                    em vigor. Todos os pares de texto passam de <span class="numero">4,5:1</span> e
                    todo contorno de controle passa de <span class="numero">3:1</span>, nos dois temas —
                    conferido por <code class="numero text-tinta">scripts/verifica-contraste.mjs</code>,
                    que lê o próprio <code class="numero text-tinta">app.css</code>.
                </p>
            </div>

            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cores as [$rotulo, $token, $classe, $uso])
                    <li class="flex items-center gap-3 rounded-bloco border border-regua bg-papel p-3">
                        <span class="size-12 shrink-0 rounded-selo border border-contorno {{ $classe }}" aria-hidden="true"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $rotulo }}</span>
                            <span class="block text-miudo text-tinta-suave">{{ $uso }}</span>
                            <code class="numero block text-miudo uppercase text-tinta-suave" data-hex="--cor-{{ $token }}">—</code>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- --------------------------------------------------- tipografia -- --}}
        <section aria-labelledby="s-tipografia" class="space-y-5">
            <div class="space-y-2">
                <h2 id="s-tipografia" class="text-titulo">Tipografia</h2>
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Newsreader nos títulos, IBM Plex Sans no texto, IBM Plex Mono em todo número.
                    O monoespaçado não é enfeite: é o que faz a vírgula alinhar numa coluna de
                    <span class="numero">21</span> parcelas.
                </p>
            </div>

            <div class="space-y-4 rounded-bloco border border-regua bg-papel p-4 sm:p-6">
                <p class="text-manchete">Quanto custa passar no crédito</p>
                <p class="text-titulo">Título de seção em Newsreader</p>
                <p class="text-subtitulo">Subtítulo, usado no cabeçalho de bloco e de tabela</p>
                <p class="max-w-prose">
                    Texto corrido em IBM Plex Sans. A leitura acontece quase toda no celular, então
                    a medida da linha é curta e o corpo não desce abaixo de 16px em lugar nenhum.
                </p>
                <p class="text-miudo text-tinta-suave">Miúdo: metadado, nota de rodapé, selo de frescor.</p>
                <p class="numero text-titulo">R$ 1.234.567,89 · 2,49% · 08/09/2026</p>
                <p class="text-miudo text-tinta-suave">
                    Formatação brasileira em todo número exibido (regra 11) — milhar com ponto,
                    decimal com vírgula, taxa sempre com duas casas.
                </p>
            </div>
        </section>

        {{-- ------------------------------------------------------- botoes -- --}}
        <section aria-labelledby="s-botoes" class="space-y-5">
            <h2 id="s-botoes" class="text-titulo">Botões</h2>

            <div class="space-y-4 rounded-bloco border border-regua bg-papel p-4 sm:p-6">
                <div class="flex flex-wrap items-center gap-3">
                    <x-botao>Comparar agora</x-botao>
                    <x-botao variante="secundaria">Ver todas as taxas</x-botao>
                    <x-botao variante="discreta">Como calculamos</x-botao>
                    <x-botao disabled>Indisponível</x-botao>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-botao tamanho="grande" href="https://exemplo.test" afiliado>Contratar com desconto</x-botao>
                    <x-botao tamanho="grande" variante="secundaria" href="https://exemplo.test" externo>Página oficial da marca</x-botao>
                </div>

                <p class="text-miudo text-tinta-suave">
                    Todo botão tem no mínimo <span class="numero">44px</span> de altura, o alvo de toque
                    do celular. Link de afiliado sai com <code class="numero text-tinta">rel="sponsored nofollow"</code>
                    e o aviso de nova aba é lido por leitor de tela.
                </p>

                <div class="max-w-md">
                    <x-botao largo tamanho="grande">Botão de largura cheia, para o rodapé do cartão</x-botao>
                </div>
            </div>
        </section>

        {{-- ---------------------------------------------------- etiquetas -- --}}
        <section aria-labelledby="s-etiquetas" class="space-y-5">
            <div class="space-y-2">
                <h2 id="s-etiquetas" class="text-titulo">Etiquetas</h2>
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Os tons nomeiam estado do dado, nunca humor.
                </p>
            </div>

            <div class="space-y-4 rounded-bloco border border-regua bg-papel p-4 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <x-etiqueta tom="aferido">Taxa divulgada</x-etiqueta>
                    <x-etiqueta tom="reportado">Faixa reportada</x-etiqueta>
                    <x-etiqueta tom="reportado">Promoção de entrada</x-etiqueta>
                    <x-etiqueta tom="vencido">Cupom vencido</x-etiqueta>
                    <x-etiqueta tom="apagado">Sem dado publicado</x-etiqueta>
                    <x-etiqueta tom="neutro">Sem mensalidade</x-etiqueta>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-etiqueta tom="aferido" variante="solida">Melhor preço</x-etiqueta>
                    <x-etiqueta tom="reportado" variante="solida">Vence em 3 dias</x-etiqueta>
                    <x-etiqueta tom="vencido" variante="solida">Expirado</x-etiqueta>
                    <x-etiqueta tom="apagado" variante="solida">Rascunho</x-etiqueta>
                    <x-etiqueta tom="neutro" variante="solida">Novo</x-etiqueta>
                </div>
            </div>
        </section>

        {{-- --------------------------------------------- selo de frescor -- --}}
        <section aria-labelledby="s-frescor" class="space-y-5">
            <div class="space-y-2">
                <h2 id="s-frescor" class="text-titulo">Selo de frescor</h2>
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Regra 8: o nível é calculado sobre a data de verificação, nunca gravado —
                    degrada sozinho depois de <span class="numero">{{ \App\Models\TaxaDivulgada::DIAS_ATE_DEGRADAR }}</span> dias,
                    sem depender de alguém regerar nada.
                </p>
            </div>

            <ul class="space-y-3 rounded-bloco border border-regua bg-papel p-4 sm:p-6">
                <li><x-selo-frescor :data="$hoje->copy()->subDays(6)" fonte="https://exemplo.test/taxas" /></li>
                <li><x-selo-frescor :data="$hoje->copy()->subDays(61)" fonte="https://exemplo.test/taxas" /></li>
                <li><x-selo-frescor :data="null" /></li>
            </ul>
        </section>

        {{-- ---------------------------------------------- cartao de marca -- --}}
        <section aria-labelledby="s-marcas" class="space-y-5">
            <div class="space-y-2">
                <h2 id="s-marcas" class="text-titulo">Cartão de marca</h2>
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Os cinco estados do resultado, cada um com a cor e o motivo à vista. Só
                    <em>calculado</em> é ranqueado por preço; os outros vêm depois, para que uma
                    mediana de relatos ou um preço de 30 dias nunca dispute a primeira posição
                    com um número publicado e permanente.
                </p>
            </div>

            <div class="space-y-4">
                <x-cartao-marca
                    nome="Marca Amostra"
                    posicao="1"
                    adquirente="Adquirente Amostra"
                    :nota="7.4"
                    :nota-data="$hoje->copy()->subDays(12)"
                    nota-url="https://exemplo.test"
                    href="/guia-visual"
                    :estado="EstadoDoResultado::Calculado"
                    destaque
                >
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                        <div>
                            <dt class="text-miudo text-tinta-suave">Custo mensal</dt>
                            <dd class="numero text-subtitulo font-medium">R$ 284,90</dd>
                        </div>
                        <div>
                            <dt class="text-miudo text-tinta-suave">Crédito à vista</dt>
                            <dd class="numero text-subtitulo">3,15%</dd>
                        </div>
                        <div>
                            <dt class="text-miudo text-tinta-suave">Débito</dt>
                            <dd class="numero text-subtitulo">1,37%</dd>
                        </div>
                        <div>
                            <dt class="text-miudo text-tinta-suave">Mensalidade</dt>
                            <dd class="numero text-subtitulo">R$ 0,00</dd>
                        </div>
                    </dl>
                    <x-selo-frescor class="mt-3" :data="$hoje->copy()->subDays(6)" fonte="https://exemplo.test/taxas" />

                    <x-slot:acoes>
                        <x-botao href="https://exemplo.test" afiliado>Contratar com cupom</x-botao>
                        <x-botao variante="secundaria" href="/guia-visual">Ver todas as taxas</x-botao>
                    </x-slot:acoes>
                </x-cartao-marca>

                <x-cartao-marca
                    nome="Segunda Amostra"
                    posicao="2"
                    adquirente="Outro Adquirente"
                    :estado="EstadoDoResultado::Promocional"
                >
                    <p class="text-sm">
                        <span class="numero text-subtitulo font-medium">R$ 198,40</span>
                        <span class="text-tinta-suave">por mês na tabela de entrada.</span>
                    </p>
                    <p class="mt-2 text-miudo text-reportado">
                        Vale por <span class="numero">30</span> dias ou até <span class="numero">R$ 5.000,00</span>
                        processados, o que vier antes. Depois disso o lojista cai no plano regular da marca.
                    </p>
                </x-cartao-marca>

                <x-cartao-marca nome="Terceira Amostra" posicao="3" :estado="EstadoDoResultado::FaixaReportada">
                    <p class="text-sm">
                        <span class="numero text-subtitulo font-medium">R$ 240,00 – R$ 410,00</span>
                        <span class="text-tinta-suave">por mês, mediana de <span class="numero">71</span> relatos.</span>
                    </p>
                    <p class="mt-2 text-miudo text-tinta-suave">A marca não publica tabela de taxas.</p>
                </x-cartao-marca>

                <x-cartao-marca nome="Quarta Amostra" :estado="EstadoDoResultado::Incompleto">
                    <p class="text-sm text-tinta-suave">Falta dado para este cenário:</p>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-miudo text-tinta-suave">
                        <li>mensalidade do plano</li>
                        <li>preço do aparelho neste plano</li>
                    </ul>
                </x-cartao-marca>

                <x-cartao-marca nome="Quinta Amostra" adquirente="Adquirente Amostra" :estado="EstadoDoResultado::SemDadoPublicado">
                    <p class="text-sm text-tinta-suave">
                        A marca não publica tabela e ainda não há relatos suficientes. Aparece aqui
                        sem número — zero seria mentira.
                    </p>
                </x-cartao-marca>
            </div>
        </section>

        {{-- --------------------------------------------- tabela de taxas -- --}}
        <section aria-labelledby="s-tabelas" class="space-y-5">
            <div class="space-y-2">
                <h2 id="s-tabelas" class="text-titulo">Tabela de taxas</h2>
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Regra 4: as duas classes de dado nunca se misturam numa mesma tabela. A faixa
                    reportada nunca imprime número exato — sai como intervalo, com a mediana
                    rotulada e o número de relatos ao lado.
                </p>
            </div>

            <div class="space-y-6">
                <x-tabela-taxas
                    titulo="Marca Amostra — plano Amostra"
                    :linhas="$taxasDivulgadas"
                    coluna-rotulo="Linha de venda"
                    fonte="https://exemplo.test/taxas"
                    :data-verificacao="$hoje->copy()->subDays(6)"
                />

                <x-tabela-taxas
                    classe="reportada"
                    titulo="Terceira Amostra — relatos de lojistas"
                    :linhas="$faixasReportadas"
                    coluna-rotulo="Linha de venda"
                    :data-verificacao="$hoje->copy()->subDays(61)"
                    :periodo-inicio="$hoje->copy()->subMonths(6)"
                    :periodo-fim="$hoje"
                />
            </div>
        </section>

        {{-- ------------------------------------------------------ cupons -- --}}
        <section aria-labelledby="s-cupons" class="space-y-5">
            <div class="space-y-2">
                <h2 id="s-cupons" class="text-titulo">Bloco de cupom</h2>
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Regra 5: o cupom desconta a adesão, nunca a taxa — e some sozinho ao vencer.
                    O terceiro bloco abaixo está vencido e só aparece porque esta página pede
                    explicitamente para não escondê-lo.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <x-bloco-cupom
                    codigo="AMOSTRA50"
                    marca="Marca Amostra"
                    :valor="50"
                    :tipo-desconto="TipoDesconto::Valor"
                    :incide-sobre="IncideSobre::Adesao"
                    :valido-ate="$hoje->copy()->addDays(45)"
                    url="https://exemplo.test"
                />

                <x-bloco-cupom
                    codigo="AMOSTRA10"
                    marca="Segunda Amostra"
                    :valor="10"
                    :tipo-desconto="TipoDesconto::Percentual"
                    :incide-sobre="IncideSobre::Equipamento"
                    :valido-ate="$hoje->copy()->addDays(3)"
                    condicao="Válido apenas na primeira maquininha da conta."
                    url="https://exemplo.test"
                />

                <x-bloco-cupom
                    codigo="AMOSTRAVELHO"
                    marca="Terceira Amostra"
                    :valor="30"
                    :valido-ate="$hoje->copy()->subDays(2)"
                    :ocultar-vencido="false"
                    url="https://exemplo.test"
                />
            </div>
        </section>

        {{-- ------------------------------------------------------ campos -- --}}
        <section aria-labelledby="s-campos" class="space-y-5">
            <div class="space-y-2">
                <h2 id="s-campos" class="text-titulo">Campos</h2>
                <p class="max-w-2xl text-sm text-tinta-suave">
                    Não existe caminho no componente que produza campo sem
                    <code class="numero text-tinta">&lt;label for&gt;</code>. Ajuda e erro chegam por
                    <code class="numero text-tinta">aria-describedby</code>, e dinheiro entra em
                    português — <span class="numero">10.000,00</span>, não
                    <span class="numero">10000.00</span> (regra 11).
                </p>
            </div>

            <form class="grid gap-5 rounded-bloco border border-regua bg-papel p-4 sm:grid-cols-2 sm:p-6" novalidate>
                <x-campo
                    rotulo="Faturamento no cartão por mês"
                    nome="faturamento"
                    prefixo="R$"
                    inputmode="decimal"
                    placeholder="10.000,00"
                    valor="10.000,00"
                    ajuda="Some só o que passa na maquininha."
                    obrigatorio
                />

                <x-campo
                    rotulo="Parcelamento mais usado"
                    nome="parcelas"
                    elemento="select"
                    :opcoes="['1' => 'À vista', '2' => 'Até 2x', '6' => 'Até 6x', '12' => 'Até 12x', '21' => 'Até 21x']"
                    valor="6"
                    ajuda="De 1 a 21 parcelas, sem faixas agrupadas (regra 2)."
                />

                <x-campo
                    rotulo="Prazo de recebimento"
                    nome="prazo"
                    elemento="select"
                    :opcoes="['' => 'O mais barato que a marca oferecer', 'na_hora' => 'Na hora', 'd_1' => 'Em 1 dia útil', 'd_30' => 'Em 30 dias', 'parcela_a_parcela' => 'Parcela a parcela']"
                />

                <x-campo
                    rotulo="Taxa de antecipação ao mês"
                    nome="antecipacao"
                    sufixo="%"
                    inputmode="decimal"
                    valor="2,49"
                    erro="Informe a taxa com duas casas decimais, como 2,49."
                />

                <x-campo
                    class="sm:col-span-2"
                    rotulo="Observação"
                    nome="observacao"
                    elemento="textarea"
                    ajuda="Opcional. Some qualquer detalhe do seu caso."
                />

                <div class="flex flex-wrap gap-3 sm:col-span-2">
                    <x-botao tipo="submit" tamanho="grande">Comparar</x-botao>
                    <x-botao tipo="reset" variante="secundaria" tamanho="grande">Limpar</x-botao>
                </div>
            </form>
        </section>

        {{-- --------------------------------------------- acessibilidade -- --}}
        <section aria-labelledby="s-a11y" class="space-y-5">
            <h2 id="s-a11y" class="text-titulo">Acessibilidade</h2>
            <ul class="list-disc space-y-2 rounded-bloco border border-regua bg-papel p-4 ps-9 text-sm sm:p-6 sm:ps-10">
                <li>Contraste conferido nos dois temas por script que lê o próprio <code class="numero">app.css</code>: <span class="numero">4,5:1</span> em texto, <span class="numero">3:1</span> em contorno de controle e anel de foco.</li>
                <li>Foco visível em tudo que recebe foco, com <span class="numero">2px</span> de folga — o anel encosta no papel dos dois lados, nunca no preenchimento do botão. Experimente navegar por esta página só com Tab.</li>
                <li>Link “pular para o conteúdo” como primeiro elemento focável, e <code class="numero">&lt;main&gt;</code> recebe o foco de fato.</li>
                <li>Tabela larga rola dentro da própria caixa e é alcançável pelo teclado, com rótulo de região.</li>
                <li>Alvo de toque de <span class="numero">44px</span> em botão, link de navegação e campo.</li>
                <li>Tema respeita a preferência do sistema sem JavaScript, e o botão do cabeçalho reporta o estado por <code class="numero">aria-pressed</code>.</li>
                <li><code class="numero">prefers-reduced-motion</code> desliga transição e animação.</li>
            </ul>
        </section>
    </div>

    {{-- Le o hexadecimal do tema em vigor, para os quadrados nao mentirem no
         escuro. So o guia visual precisa disso. --}}
    <script>
        (function () {
            var alvos = document.querySelectorAll('[data-hex]');

            function pintar() {
                var estilo = getComputedStyle(document.documentElement);
                alvos.forEach(function (alvo) {
                    alvo.textContent = estilo.getPropertyValue(alvo.dataset.hex).trim() || '—';
                });
            }

            pintar();
            new MutationObserver(pintar).observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-tema'],
            });
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', pintar);
        })();
    </script>
</x-layouts.site>

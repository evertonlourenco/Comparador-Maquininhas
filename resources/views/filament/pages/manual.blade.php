<x-filament-panels::page>
    <div class="manual-do-admin space-y-16 text-sm leading-relaxed text-gray-700 dark:text-gray-300">

        <div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/50">
            <p>
                Este manual é para você, ou para quem assumir o painel depois de você, sem precisar entender de
                código. Cada seção explica não só <em>o que</em> clicar, mas <em>por que</em> a tela funciona do jeito
                que funciona — porque lembrar a razão ajuda mais do que decorar o passo a passo, principalmente se
                você voltar aqui meses depois com pressa.
            </p>
            <p>
                Se um passo daqui não bater mais com o que você vê na tela, o painel mudou e este texto ficou
                atrasado — me avise para eu atualizar. Tem um <a href="#glossario" class="text-primary-600 hover:underline dark:text-primary-400">Glossário</a>
                no fim, com os termos do domínio explicados em uma frase cada, caso alguma palavra apareça sem
                contexto suficiente.
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Nota sobre as imagens deste manual: são <strong>esquemas ilustrativos</strong> que eu desenhei para
                mostrar onde cada coisa fica — não são capturas de tela reais. Eu não consigo entrar no painel para
                fotografar a tela de verdade, porque ele exige autenticação em duas etapas da sua conta, e eu nunca
                digito senha ou código de acesso de ninguém. Se algum dia você quiser trocar um esquema por uma
                captura de tela real, é só me mandar o print e eu encaixo no lugar certo.
            </p>
        </div>

        {{-- ÍNDICE --}}
        <nav aria-label="Índice do manual" class="rounded-lg border border-gray-200 p-5 dark:border-gray-700">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Neste manual</p>
            <ol class="grid gap-2 sm:grid-cols-2">
                <li><a href="#ordem-de-cadastro" class="text-primary-600 hover:underline dark:text-primary-400">1. A ordem certa de cadastro</a></li>
                <li><a href="#lancar-taxas" class="text-primary-600 hover:underline dark:text-primary-400">2. Lançar a tabela de taxas de uma marca</a></li>
                <li><a href="#estados" class="text-primary-600 hover:underline dark:text-primary-400">3. O que significa cada estado</a></li>
                <li><a href="#aprovar-e-json" class="text-primary-600 hover:underline dark:text-primary-400">4. Aprovar taxas e regenerar o JSON</a></li>
                <li><a href="#filas-de-revisao" class="text-primary-600 hover:underline dark:text-primary-400">5. As duas filas de revisão de lojistas</a></li>
                <li><a href="#painel-de-saude" class="text-primary-600 hover:underline dark:text-primary-400">6. Como ler o painel de saúde</a></li>
                <li><a href="#restaurar-backup" class="text-primary-600 hover:underline dark:text-primary-400">7. Como restaurar um backup</a></li>
                <li><a href="#nunca-fazer" class="text-primary-600 hover:underline dark:text-primary-400">8. O que nunca fazer</a></li>
                <li><a href="#glossario" class="text-primary-600 hover:underline dark:text-primary-400">Glossário de termos</a></li>
            </ol>
        </nav>

        {{-- 1. ORDEM DE CADASTRO --}}
        <section id="ordem-de-cadastro" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">1. A ordem certa de cadastro</h2>

            <p>
                O catálogo deste site é montado em camadas, e cada camada se apoia na anterior. Cadastrar fora de
                ordem não trava a tela na hora — o painel simplesmente não deixa você escolher algo que ainda não
                existe, o que costuma ser mais confuso do que um erro claro. Entender a cadeia de dependência de uma
                vez evita ficar tentando adivinhar por que um campo não aparece.
            </p>

            <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900/40">
                <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Esquema ilustrativo — a cadeia de dependência
                </p>
                <div class="flex flex-wrap items-center justify-center gap-2 text-center text-xs font-medium sm:text-sm">
                    <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">Adquirente</span>
                    <span class="text-gray-400 dark:text-gray-500">→</span>
                    <span class="rounded-md border-2 border-primary-400 bg-primary-50 px-3 py-2 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">Marca</span>
                    <span class="text-gray-400 dark:text-gray-500">→</span>
                    <span class="rounded-md border-2 border-primary-400 bg-primary-50 px-3 py-2 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">Plano</span>
                    <span class="text-gray-400 dark:text-gray-500">→</span>
                    <span class="flex flex-col gap-2">
                        <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">Equipamento</span>
                        <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">Cupom</span>
                    </span>
                </div>
                <p class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">
                    Marca e Plano em destaque porque são os dois que toda taxa depende diretamente.
                </p>
            </div>

            <p>Detalhando cada elo da corrente:</p>

            <ol class="ml-5 list-decimal space-y-4">
                <li>
                    <p><strong>Adquirente</strong> <span class="text-gray-500 dark:text-gray-400">(menu Dimensões → Adquirentes)</span></p>
                    <p class="mt-1">
                        É a instituição que efetivamente processa o pagamento por trás da marca — por exemplo, a
                        Cielo ou o PagSeguro/PagBank. Esse campo existe só para transparência: às vezes duas marcas
                        diferentes (com nome, preço e site próprios) são, por trás, a mesma processadora revendida.
                        Saber disso ajuda a entender por que a tabela de taxas de duas marcas às vezes é idêntica.
                        Não é obrigatório — se você não sabe qual é o adquirente de uma marca nova, deixe o campo em
                        branco em vez de chutar.
                    </p>
                </li>
                <li>
                    <p><strong>Marca</strong> <span class="text-gray-500 dark:text-gray-400">(menu Catálogo → Marcas)</span></p>
                    <p class="mt-1">
                        A maquininha em si, como o lojista a conhece — Ton, PagBank, InfinitePay. É o ponto de
                        partida de tudo: sem a marca existir, não tem onde pendurar plano, taxa, equipamento ou
                        cupom. É aqui também que entram o logo, a nota do Reclame Aqui, o link do site oficial e as
                        bandeiras que a marca aceita.
                    </p>
                </li>
                <li>
                    <p><strong>Plano</strong> <span class="text-gray-500 dark:text-gray-400">(menu Catálogo → Planos, ou pela aba "Planos" dentro da marca)</span></p>
                    <p class="mt-1">
                        Cada marca pode ter mais de um plano ao mesmo tempo — um plano padrão, um plano promocional
                        que vale só nos primeiros 30 dias, um plano diferente por faixa de faturamento. <strong>A
                        taxa nunca pertence à marca diretamente — ela sempre pertence a um plano</strong> específico
                        daquela marca. É por isso que o plano precisa existir antes: a taxa não tem outro lugar para
                        morar. Se uma marca tem só uma tabela de taxas simples, ainda assim ela mora dentro de um
                        plano — só que um plano só, sem variação.
                    </p>
                </li>
                <li>
                    <p><strong>Equipamento</strong> <span class="text-gray-500 dark:text-gray-400">(menu Catálogo → Equipamentos)</span></p>
                    <p class="mt-1">
                        A maquininha física — modelo, foto, ficha técnica (se imprime comprovante, se tem chip de
                        dados próprio, etc.). O <em>preço</em> de adesão dela não fica no cadastro do equipamento —
                        fica num vínculo separado entre o equipamento e cada plano, porque a mesma maquininha física
                        pode custar valores diferentes dependendo de qual plano o lojista escolhe.
                    </p>
                </li>
                <li>
                    <p><strong>Cupom</strong> <span class="text-gray-500 dark:text-gray-400">(menu Catálogo → Cupons)</span></p>
                    <p class="mt-1">
                        O código de desconto que dá vantagem na adesão. Vem por último na ordem porque, na imensa
                        maioria dos casos, pertence à marca inteira (qualquer maquininha daquela marca), não a um
                        equipamento específico — e obviamente não existe sem a marca já cadastrada.
                    </p>
                </li>
            </ol>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
                <p class="font-semibold">O que quebra se você inverter a ordem</p>
                <p class="mt-2">
                    Tentar cadastrar uma taxa antes do plano existir simplesmente não deixa você escolher o plano — o
                    campo de seleção fica vazio, sem nenhuma opção para marcar. Tentar vincular um equipamento a um
                    plano que ainda não existe é a mesma história: o campo "Plano" do formulário de vínculo aparece
                    sem nada para escolher. O painel não deixa você seguir fora de ordem de jeito nenhum — ele só
                    avisa de um jeito bem menos claro do que este texto (um campo vazio, sem explicação de por quê).
                    Se algum campo aparecer sem opções, o primeiro instinto certo é conferir se o "passo anterior da
                    corrente" já foi cadastrado.
                </p>
            </div>
        </section>

        <hr class="border-gray-200 dark:border-gray-800" />

        {{-- 2. LANÇAR TAXAS EM LOTE --}}
        <section id="lancar-taxas" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">2. Como lançar a tabela de taxas de uma marca em lote</h2>

            <p>
                Uma marca pode ter dezenas — às vezes mais de cem — linhas de taxa: débito, crédito à vista, crédito
                parcelado de 2x a 21x, Pix, multiplicado por prazo de recebimento (na hora, D+1, D+30...) e por grupo
                de bandeiras (Visa/Master, Elo, demais bandeiras — cada marca agrupa do seu jeito). Editar isso taxa
                por taxa, uma tela por vez, é inviável. Por isso existem duas telas de lançamento em lote, e as duas
                ficam dentro de <strong>Taxas → Taxas Divulgadas</strong>.
            </p>

            <div class="space-y-4">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">"Lançamento em lote"</p>
                    <p class="mt-2">
                        Botão no topo da listagem de Taxas Divulgadas. Abre uma grade <strong>em branco</strong>: você
                        escolhe a marca e o prazo de recebimento, e preenche as colunas de 1x a 21x do zero. Use esta
                        tela quando for lançar uma marca (ou um prazo dela) que ainda não tem nenhuma taxa cadastrada.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">"Tabela do plano"</p>
                    <p class="mt-2">
                        Botão na coluna "Plano" da mesma listagem, ou na listagem de Planos. Abre a tabela <em>já
                        preenchida</em> com o que existe hoje para aquele plano — débito, crédito de 1x a 21x e Pix,
                        organizados por grupo de bandeira e por prazo, tudo numa tela só. Use esta tela para corrigir
                        ou completar um plano que já tem alguma taxa cadastrada — é a que resolve melhor o dia a dia,
                        porque você vê o que já existe antes de mexer.
                    </p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900/40">
                <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Esquema ilustrativo — a grade da "Tabela do plano"
                </p>
                <div class="mb-3 flex justify-end gap-2">
                    <span class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white">Publicar toda a tabela</span>
                    <span class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 dark:border-gray-600 dark:text-gray-300">Voltar tudo para rascunho</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] border-collapse text-xs">
                        <thead>
                            <tr>
                                <th class="border border-gray-200 bg-gray-50 p-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800">na hora</th>
                                <th class="border border-gray-200 bg-gray-50 p-2 font-medium dark:border-gray-700 dark:bg-gray-800">Visa/Master</th>
                                <th class="border border-gray-200 bg-gray-50 p-2 font-medium dark:border-gray-700 dark:bg-gray-800">Elo</th>
                                <th class="border border-gray-200 bg-gray-50 p-2 font-medium dark:border-gray-700 dark:bg-gray-800">Demais</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-gray-200 p-2 dark:border-gray-700">Débito</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">1,45%</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">1,95%</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">1,95%</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-200 p-2 dark:border-gray-700">Crédito 1x</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">3,21%</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">3,85%</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">3,85%</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-200 p-2 dark:border-gray-700">Crédito 2x</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">4,31%</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">4,31%</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">4,31%</td>
                            </tr>
                            <tr class="text-gray-400 dark:text-gray-500">
                                <td class="border border-gray-200 p-2 dark:border-gray-700">⋮ até 21x</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">⋮</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">⋮</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700">⋮</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-200 p-2 dark:border-gray-700">Pix</td>
                                <td class="border border-gray-200 p-2 text-center dark:border-gray-700" colspan="3">0,46%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Só aparecem as colunas de grupo de bandeira que a marca de fato usa — se ela agrupa Elo junto de
                    "demais bandeiras" numa coluna só, é isso que a tela mostra, não uma coluna a mais inventada.
                </p>
            </div>

            <p>
                Nas duas telas, você digita o número de parcelas (1, 2, 3... até 21) junto com uma célula de
                percentual, e o sistema decide sozinho como gravar isso por trás:
            </p>

            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <p><strong>1 parcela</strong> grava como <span class="numero">crédito à vista</span>.</p>
                <p class="mt-2"><strong>De 2 a 21 parcelas</strong> grava como <span class="numero">crédito parcelado</span>.</p>
                <p class="mt-3 text-gray-500 dark:text-gray-400">
                    É a mesma distinção que a própria bandeira do cartão usa por trás: "passar no crédito" (1x) é uma
                    operação diferente de "parcelar" (2x para cima), mesmo as duas sendo crédito — e normalmente com
                    percentuais bem diferentes entre si (1x costuma ser bem mais barato que 12x, por exemplo). O
                    painel só está seguindo essa mesma separação ao decidir sozinho qual dos dois tipos gravar; você
                    não precisa escolher isso num campo à parte, é automático a partir do número de parcelas que você
                    digitou.
                </p>
            </div>

            <p>
                Débito e Pix não têm essa dimensão de parcela — ninguém parcela um débito ou um Pix — então eles
                ficam em seções próprias dentro da mesma tela, com um único valor cada, sem coluna de 1x a 21x.
            </p>

            <p>
                Cada célula que você preenche grava sozinha, na hora em que você sai dela — não existe um botão
                "salvar tudo" no fim da tela. Isso tem duas consequências práticas:
            </p>

            <ul class="ml-5 list-disc space-y-2">
                <li>
                    Uma célula que você <strong>apaga</strong> remove de vez a taxa que estava ali. Isso é
                    intencional: uma taxa apagada (ausente) é mais honesta do que uma taxa desatualizada ou errada
                    ficando pra trás sem querer.
                </li>
                <li>
                    Uma célula que você <strong>não toca</strong> continua exatamente como estava — com a mesma
                    fonte e a mesma data de verificação de quando foi conferida da última vez. Editar uma célula não
                    bagunça a fonte/data das outras que você não mexeu.
                </li>
            </ul>
        </section>

        <hr class="border-gray-200 dark:border-gray-800" />

        {{-- 3. ESTADOS --}}
        <section id="estados" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">3. O que significa cada estado</h2>

            <p>
                Cinco palavras aparecem espalhadas pelo painel e pelo site público, e cada uma carrega um significado
                preciso — não são sinônimos entre si, mesmo quando parecem próximas. Aqui está o que cada uma quer
                dizer, sem termo técnico, e onde você a encontra.
            </p>

            <div class="space-y-4">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Rascunho</p>
                    <p class="mt-2">
                        Você cadastrou o número no painel, mas ele <strong>ainda não aparece no site</strong> para
                        quem visita. É o estado padrão de toda taxa nova, sem exceção — nada vai ao ar sozinho, só
                        porque foi digitado. Você precisa aprovar explicitamente (seção 4) para o número sair do
                        rascunho.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Publicado</p>
                    <p class="mt-2">
                        Você conferiu o número contra o site oficial da marca e aprovou — esse valor está (ou vai
                        ficar, assim que o JSON for regenerado — seção 4) visível para o visitante do site.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Aferido</p>
                    <p class="mt-2">
                        É como o <em>site público</em> chama, para o visitante, uma taxa que está publicada pela
                        própria marca (não é mediana de relato de ninguém) e foi conferida há menos de 45 dias. É o
                        estado "bom": o site destaca esse dado com uma cor e um ícone próprios, porque é o mais
                        confiável que existe — veio direto da fonte oficial e ainda está fresco.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Reportado</p>
                    <p class="mt-2">
                        Três situações diferentes usam essa mesma etiqueta no site, e as três têm em comum uma coisa:
                        nenhuma delas é um número definitivo e permanente.
                    </p>
                    <ul class="ml-5 mt-2 list-disc space-y-1">
                        <li>Uma <strong>faixa de relatos</strong> — mediana do que lojistas reportaram, usada para as marcas que não publicam tabela própria (Cielo, Rede, GetNet e Stone).</li>
                        <li>Uma <strong>promoção de entrada</strong> — uma tabela que vale só nos primeiros dias de uso, ou até um teto de vendas, e depois muda para o plano permanente.</li>
                        <li>Uma taxa com <strong>mais de 45 dias</strong> sem reconferir — ela não some do site, só perde o destaque de "aferida" até alguém confirmar o número de novo contra a fonte oficial.</li>
                    </ul>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Vencido</p>
                    <p class="mt-2">
                        Um cupom fora da validade cadastrada. Ele some sozinho do site assim que a data passa — você
                        não precisa lembrar de desativar nada manualmente, mas também não adianta editar a validade
                        depois: se o cupom realmente venceu, o certo é confirmar com o parceiro se ele continua
                        valendo e, se sim, cadastrar a validade nova.
                    </p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs dark:border-gray-700 dark:bg-gray-800/50">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Resumindo a diferença entre os dois pares que mais confundem:</p>
                <p class="mt-2"><strong>Rascunho/Publicado</strong> é sobre o <em>painel</em> — decide se algo aparece no site ou não.</p>
                <p class="mt-1"><strong>Aferido/Reportado/Vencido</strong> é sobre o <em>site público</em> — a cor e o rótulo que um dado já publicado ganha, dependendo de quão confiável ou recente ele é.</p>
            </div>
        </section>

        <hr class="border-gray-200 dark:border-gray-800" />

        {{-- 4. APROVAR E REGENERAR O JSON --}}
        <section id="aprovar-e-json" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">4. Como aprovar taxas — e por que é preciso regenerar o JSON depois</h2>

            <p>
                Em <strong>Taxas → Taxas Divulgadas</strong> (ou <strong>Faixas Reportadas</strong>), marque as linhas
                que você já conferiu e use a ação em massa <strong>"Aprovar e publicar"</strong>. Dentro da tela
                "Tabela do plano", o mesmo efeito para o plano inteiro de uma vez é o botão <strong>"Publicar toda a
                tabela"</strong> no topo. Para desfazer, existe sempre o par oposto: <strong>"Voltar para
                rascunho"</strong> / <strong>"Voltar tudo para rascunho"</strong>.
            </p>

            <div class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                <p class="font-semibold">Por que esse passo existe (a pergunta de fundo)</p>
                <p>
                    Marcar como "Publicado" no painel <strong>não muda o site sozinho</strong>. O comparador é a
                    parte do site que mais gente usa — a calculadora que compara as taxas —, e ela foi desenhada de
                    propósito para <em>não</em> consultar o banco de dados a cada visita. Se consultasse, um vídeo
                    seu levando muita gente de uma vez ao site sobrecarregaria o banco bem na hora que mais importa
                    o site estar de pé.
                </p>
                <p>
                    Em vez disso, o comparador lê um arquivo (<code>comparador.json</code>) que foi gerado com
                    antecedência, guardando um retrato do banco de dados num certo momento. Esse arquivo só é
                    reescrito quando alguém manda reescrever — aprovar uma taxa no painel muda o banco, mas não esse
                    arquivo. Sem regenerá-lo, o site continua mostrando o retrato antigo: sem erro nenhum na tela,
                    sem aviso, só desatualizado em silêncio, e ninguém percebe até reparar que um número não bate.
                </p>
            </div>

            <p>
                <strong>O jeito mais fácil de resolver isso: um botão, direto no painel.</strong> Sem terminal, sem
                SSH, sem nada para copiar errado. Ele aparece em quatro lugares — logo ao entrar no painel
                (Dashboard), e nas telas onde você aprova taxa (listagem de Taxas Divulgadas, listagem de Faixas
                Reportadas, "Tabela do plano" e "Lançamento em lote"):
            </p>

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200">
                <p>
                    Clique em <strong>"Gerar JSON do comparador"</strong>, confirme na janela que abre, e pronto — o
                    painel avisa, numa notificação no canto da tela, quantas marcas e taxas entraram no arquivo novo.
                    Essa é a forma recomendada; use-a sempre que puder, em vez do terminal.
                </p>
            </div>

            <p>
                O próprio botão avisa sozinho quando há trabalho pendente: um selo laranja
                <strong>"Pendente"</strong> aparece ao lado dele sempre que existe alguma aprovação, edição de taxa
                já publicada, ou despublicação ainda não refletida no arquivo. Essa comparação é feita pelo
                <em>conteúdo</em> do catálogo, não só pela data — então editar uma taxa que ainda está em rascunho
                (o trabalho normal de ir curando marca por marca aos poucos) não acende o alerta à toa, porque nada
                em rascunho está no arquivo público de qualquer forma. Sem o selo, o arquivo já está em dia. Ele some
                sozinho assim que você clica no botão e a geração termina — não precisa recarregar a página.
            </p>

            <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900/40">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Esquema ilustrativo — o botão nos dois estados
                </p>
                <div class="flex flex-wrap items-center gap-6">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 dark:border-gray-600 dark:text-gray-300">
                            ↻ Gerar JSON do comparador
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">em dia, sem selo</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-500 px-3 py-1.5 text-xs font-medium text-white">
                            ↻ Gerar JSON do comparador
                            <span class="rounded bg-white/25 px-1.5 py-0.5 text-[10px] font-semibold">Pendente</span>
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">há mudança não publicada</span>
                    </div>
                </div>
            </div>

            <p class="text-gray-500 dark:text-gray-400">
                O botão só existe desde 17/09/2026. Antes disso o único jeito era rodar o comando por SSH — o que
                travou o terminal na primeira tentativa real (o comando é comprido, quebra em duas linhas na tela, e
                copiar só a primeira linha perde a aspa de fechamento; o terminal fica esperando você completar a
                aspa, mostrando <code>quote&gt;</code> em vez de voltar ao prompt normal — digite <code>'</code> e
                aperte Enter para sair disso, ou <code>Ctrl+C</code> para cancelar). O comando continua funcionando,
                para quando o painel estiver fora do ar ou você preferir o terminal — desta vez num bloco que não
                quebra linha, para copiar sem risco:
            </p>
<pre class="overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>ssh comparador '/opt/alt/php84/usr/bin/php ~/domains/maquinacerta.com.br/comparador/artisan comparador:gerar-json'</code></pre>
            <p class="text-gray-500 dark:text-gray-400">
                Ou o deploy completo, que regenera o JSON como parte dele — use quando também tiver código novo para subir:
            </p>
<pre class="overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>ssh comparador '~/domains/maquinacerta.com.br/comparador/deploy.sh'</code></pre>
            <p class="text-gray-500 dark:text-gray-400">
                Sinal de que funcionou, pelo botão ou pelo terminal: o total de marcas e taxas aparece na notificação
                (ou na tabela, no terminal). Se esse número não mudou depois de você aprovar algo, alguma coisa não
                regenerou — confira de novo antes de avisar o Everton.
            </p>
        </section>

        <hr class="border-gray-200 dark:border-gray-800" />

        {{-- 5. FILAS DE REVISÃO --}}
        <section id="filas-de-revisao" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">5. As duas filas de revisão de relatos de lojistas</h2>

            <p>
                Além do que você mesmo cadastra a partir do site oficial de cada marca, o site também recebe
                informação de quem realmente usa as maquininhas no dia a dia — o lojista. Essa informação entra
                pelo site público e cai em uma de duas filas dentro do grupo <strong>"Relatos de lojistas"</strong>
                do menu:
            </p>

            <div class="space-y-4">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Propostas Recebidas</p>
                    <p class="mt-2">
                        Quando um lojista conta, pelo formulário público do site, a proposta comercial que recebeu da
                        Cielo, Rede, GetNet ou Stone. Essas quatro marcas não publicam tabela de taxas em lugar
                        nenhum público — a única forma de saber o que elas realmente cobram é essa. Cada proposta tem
                        um botão "Baixar anexo" quando a pessoa mandou foto ou PDF da tabela que recebeu.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Relatos de Taxa Incorreta</p>
                    <p class="mt-2">
                        Quando alguém, olhando a tabela de uma marca qualquer no site, clica em "reportar taxa
                        errada" e descreve o que acha que está errado. Pode ser uma taxa desatualizada, uma
                        informação que já mudou, ou um erro de digitação nosso mesmo.
                    </p>
                </div>
            </div>

            <p>
                Nas duas telas, o filtro <strong>"Status"</strong> separa <strong>Pendente</strong> (ninguém olhou
                ainda) de <strong>Revisado</strong> (já foi conferido). Use o filtro "Pendente" como sua lista de
                tarefas dessa área.
            </p>

            <div class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                <p class="font-semibold">O que nunca fazer com elas</p>
                <p>
                    Essas duas telas <strong>não publicam nada sozinhas no site</strong> — não existe, e nunca vai
                    existir de propósito, um botão que transforma um relato direto numa taxa publicada. Isso é
                    deliberado: um relato de lojista é a palavra de uma pessoa só, não uma fonte oficial verificável
                    como o site de uma marca.
                </p>
                <p>
                    O fluxo correto é: ler o relato, decidir se ele faz sentido (bate com outros relatos, ou com o
                    que você já sabe da marca), e então <strong>cadastrar a faixa reportada — ou corrigir a taxa —
                    você mesmo, à mão</strong>, usando as telas normais de Taxas descritas nas seções 2 e 4. Só
                    depois disso marque o relato original como "Revisado". Marcar como revisado sem antes ter
                    conferido e agido sobre o conteúdo é, na prática, o mesmo erro que publicar uma taxa sem fonte —
                    só que disfarçado de "já resolvi".
                </p>
            </div>
        </section>

        <hr class="border-gray-200 dark:border-gray-800" />

        {{-- 6. PAINEL DE SAÚDE --}}
        <section id="painel-de-saude" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">6. Como ler o painel de saúde e o que fazer com cada alerta</h2>

            <p>
                A tela inicial do <code>/admin</code> — o Dashboard, a primeira coisa que você vê ao entrar — já é o
                painel de saúde do site inteiro. Não precisa abrir uma conexão SSH nem entrar no hPanel da Hostinger
                para saber se o site está bem: os cartões dessa tela respondem isso de relance.
            </p>

            <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900/40">
                <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Esquema ilustrativo — a leitura rápida das cores
                </p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-center dark:border-emerald-900/50 dark:bg-emerald-900/20">
                        <p class="text-xs font-semibold text-emerald-800 dark:text-emerald-300">Verde</p>
                        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">Ok, nada a fazer</p>
                    </div>
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-center dark:border-amber-900/50 dark:bg-amber-900/20">
                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">Laranja</p>
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">De olho, resolver quando der</p>
                    </div>
                    <div class="rounded-md border border-red-200 bg-red-50 p-3 text-center dark:border-red-900/50 dark:bg-red-900/20">
                        <p class="text-xs font-semibold text-red-800 dark:text-red-300">Vermelho</p>
                        <p class="mt-1 text-xs text-red-700 dark:text-red-400">Resolver logo</p>
                    </div>
                    <div class="rounded-md border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">Cinza — "Sem dado"</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Não é alarme, é honestidade</p>
                    </div>
                </div>
            </div>

            <p>
                Um cartão cinza com "Sem dado" nunca é motivo de alarme — é o painel sendo honesto que aquele número
                ainda não foi medido, normalmente porque falta uma configuração externa (por exemplo, o token de
                análise de tráfego da Cloudflare), não porque algo quebrou.
            </p>

            <div class="space-y-4">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Links de afiliado quebrados</p>
                    <p class="mt-2">
                        <strong>É o alerta de maior valor prático da lista.</strong> Um comando roda todo dia de
                        madrugada e testa se o site de cada marca e o link de cada cupom ainda respondem. Um link
                        quebrado é receita de comissão perdida, e sem esse cartão seria completamente invisível —
                        ninguém clica em todo link de afiliado todo santo dia para conferir. Se aparecer vermelho:
                        abra a marca ou o cupom apontado, teste o link você mesmo no navegador, e corrija a URL se
                        ela realmente mudou.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Taxas não verificadas há mais de 30 dias / faixas de frescor</p>
                    <p class="mt-2">
                        Esse alerta antecipa o selo de "desatualizada" que o site público mostra automaticamente aos
                        45 dias (regra do "selo de frescor" — ver Glossário) — 30 dias é um aviso antecipado, para
                        você ter tempo de agir antes do selo realmente degradar. A ação é separar um tempo, reabrir
                        essas marcas, conferir o site oficial de novo, e reconfirmar o número. Mesmo quando o valor
                        não mudou nada, só atualizar a data de verificação já resolve o alerta.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Cupons vencendo em 7 dias</p>
                    <p class="mt-2">
                        Contate o parceiro (o programa de afiliados da marca) para confirmar se o cupom continua
                        valendo depois da data cadastrada e, se sim, atualize a validade no cadastro do cupom antes
                        que ele vença e suma sozinho do site.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Marcas sem taxa cadastrada / Propostas e relatos pendentes / Detecções do monitor pendentes</p>
                    <p class="mt-2">
                        Três filas de trabalho pendente diferentes, agrupadas aqui por serem do mesmo tipo — "algo
                        está esperando você". Cada cartão tem um link direto embutido nele; clicar no cartão já leva
                        reto à tela que resolve aquele item, sem precisar navegar pelo menu.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Backup diário</p>
                    <p class="mt-2">
                        <strong>"Ok"</strong> quer dizer que o backup rodou de madrugada, terminou sem erro, e uma
                        cópia foi enviada também para o Google Drive (fora do servidor — a garantia real de que um
                        problema no servidor não leva o backup junto). <strong>"Falhou"</strong>, ou um horário muito
                        antigo parado sem atualizar, merece aviso imediato — é o tipo de coisa que nunca se resolve
                        sozinha, e que só se descobre tarde demais se ninguém olhar este cartão de vez em quando.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Jobs pendentes na fila</p>
                    <p class="mt-2">
                        Este projeto não usa fila de processamento em segundo plano para nada — toda tarefa roda na
                        hora. Por isso esse número deveria ser sempre zero. <strong>Qualquer número diferente de
                        zero é estranho</strong> e quer dizer que uma configuração do servidor mudou sem que
                        ninguém tenha decidido isso de propósito. Me avise se aparecer.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Certificado SSL</p>
                    <p class="mt-2">
                        O certificado que garante o cadeado de "site seguro" no navegador. Ele expira periodicamente
                        e normalmente se renova sozinho, mas o cartão mostra quantos dias faltam para o vencimento —
                        se aparecer vermelho (poucos dias restando), vale investigar por que a renovação automática
                        não aconteceu, porque um certificado vencido derruba o cadeado de segurança do site inteiro.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">APP_DEBUG</p>
                    <p class="mt-2">
                        Uma configuração interna do sistema que liga ou desliga a tela de erro "detalhada". Quando
                        <strong>ligada</strong>, qualquer erro no site — mesmo um bobo, tipo um link quebrado —
                        mostra na tela, para qualquer visitante, o código-fonte do trecho que falhou, os caminhos de
                        arquivo do servidor e até valores de configuração sensíveis. É uma ferramenta ótima durante o
                        desenvolvimento (ajuda a entender rápido o que deu errado), mas em produção é um vazamento
                        de informação sério — por isso ela precisa estar sempre <strong>desligada</strong> no site no
                        ar, e o painel confere isso e pinta de vermelho se, por algum motivo, alguém ligou.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Permissão do .env</p>
                    <p class="mt-2">
                        O arquivo <code>.env</code> guarda todas as senhas e chaves do site — a senha do banco de
                        dados, a chave de criptografia interna, credenciais de e-mail, tokens de serviços externos.
                        "Permissão" aqui é a permissão de arquivo do sistema operacional (o mesmo conceito do
                        <code>chmod</code> do Unix/Linux): quem no servidor tem autorização para ler esse arquivo. O
                        valor correto é <code>600</code>, que restringe a leitura só ao dono do arquivo (a própria
                        conta de hospedagem). Se esse número aparecer diferente — por exemplo <code>644</code>, mais
                        aberto — outras contas que dividem o mesmo servidor compartilhado conseguiriam, em teoria,
                        ler as senhas do site. Este alerta é sobre segurança do servidor em si, não de código —
                        avise imediatamente se aparecer vermelho.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Usuários sem 2FA</p>
                    <p class="mt-2">
                        Quantas contas de administrador ainda não configuraram a autenticação em duas etapas (o
                        autenticador do celular, que gera um código que muda a cada 30 segundos). O painel exige essa
                        configuração no primeiro login de cada conta nova, então esse número deveria ficar em zero
                        pouco depois de qualquer administrador novo ser criado.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Cliques em cupom e Tráfego</p>
                    <p class="mt-2">
                        Esses dois não são alerta — são acompanhamento, para você entender como o site está sendo
                        usado. "Cliques em cupom" mostra quantas pessoas copiaram um código ou clicaram em "usar
                        cupom", por marca e por cupom individual — útil para bater com o relatório que os parceiros
                        de afiliado mandam no fim do mês. "Tráfego" mostra visitas por dia, mas só passa a mostrar
                        número de verdade depois que um token de acesso à API da Cloudflare for configurado; até lá,
                        o cartão fica vazio de propósito, com o motivo explicado nele mesmo — isso não é um bug, é o
                        painel recusando inventar um número que não mediu de fato.
                    </p>
                </div>
            </div>
        </section>

        <hr class="border-gray-200 dark:border-gray-800" />

        {{-- 7. RESTAURAR BACKUP --}}
        <section id="restaurar-backup" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">7. Como restaurar um backup</h2>

            <p>
                Situações em que isso é necessário: alguém apagou ou alterou dado importante por engano no painel,
                uma migração de código deu errado e corrompeu alguma tabela, ou o servidor teve um problema sério.
                Precisa de acesso SSH ao servidor — se você não tem certeza do que está fazendo, é mais seguro pedir
                para eu rodar isso e te mostrar o resultado antes de qualquer confirmação, em vez de arriscar sozinho.
            </p>

            <ol class="ml-5 list-decimal space-y-4">
                <li>
                    <p><strong>Ache o carimbo de data/hora do backup que você quer.</strong></p>
<pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>ssh comparador 'ls -lht ~/backups/comparador/ | head -10'</code></pre>
                    <p class="mt-2">
                        Os nomes seguem o padrão <code>banco-2026-09-17_030002.sql.gz</code> — a data e a hora estão
                        no próprio nome do arquivo, e a lista vem ordenada do mais recente para o mais antigo.
                    </p>
                </li>
                <li>
                    <p><strong>Restaure o banco</strong> (troque <code>&lt;carimbo&gt;</code> pelo que você achou no passo anterior):</p>
<pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>gunzip -c ~/backups/comparador/banco-&lt;carimbo&gt;.sql.gz \
  | mysql --defaults-extra-file=~/.comparador-backup.cnf u835756808_comparador</code></pre>
                    <p class="mt-2">
                        O dump já vem com a instrução para apagar cada tabela antes de recriá-la — não precisa
                        limpar nada manualmente antes de rodar este comando.
                    </p>
                </li>
                <li>
                    <p><strong>Restaure os arquivos enviados</strong> (logos de marca, fotos de equipamento, anexos de proposta), se precisar:</p>
<pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>tar -xzf ~/backups/comparador/arquivos-&lt;carimbo&gt;.tar.gz -C ~/domains/maquinacerta.com.br/comparador</code></pre>
                </li>
                <li>
                    <p><strong>Restaure o <code>.env</code> só se a chave de segurança (APP_KEY) tiver se perdido</strong> — é a exceção, não o padrão de todo restore:</p>
<pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>cp ~/backups/comparador/env-&lt;carimbo&gt; ~/domains/maquinacerta.com.br/comparador/.env
chmod 600 ~/domains/maquinacerta.com.br/comparador/.env</code></pre>
                </li>
                <li>
                    <p><strong>Sempre termine rodando o deploy</strong>, mesmo que você não tenha restaurado arquivo nem <code>.env</code> — os caches do site ficam apontando para o estado de antes até isso rodar:</p>
<pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>ssh comparador '~/domains/maquinacerta.com.br/comparador/deploy.sh'</code></pre>
                </li>
            </ol>

            <p class="text-gray-500 dark:text-gray-400">
                Se o backup do próprio dia estiver corrompido, ou se o disco do servidor tiver sumido junto com ele,
                os três arquivos também existem numa cópia externa no Google Drive (pasta
                <code>backups-maquina-certa</code>) — baixe primeiro com
                <code>rclone copy gdrive:&lt;nome-do-arquivo&gt; .</code> antes de seguir os mesmos passos acima.
            </p>
        </section>

        <hr class="border-gray-200 dark:border-gray-800" />

        {{-- 8. NUNCA FAZER --}}
        <section id="nunca-fazer" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">8. O que nunca fazer</h2>

            <p>
                Três regras que valem mais do que qualquer procedimento passo a passo, porque quebrá-las custa caro
                de um jeito difícil de desfazer depois.
            </p>

            <div class="space-y-4">
                <div class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">Nunca publique uma taxa sem fonte e sem data de verificação.</p>
                    <p>
                        Campo vazio é honesto — mostra claramente que aquele dado ainda não foi confirmado. Um número
                        errado publicado como se fosse certo é risco real de propaganda enganosa, sob o Código de
                        Defesa do Consumidor. Se você não tem certeza absoluta de um número, deixe-o em rascunho até
                        confirmar de verdade, mesmo que isso signifique a marca ficar incompleta no site por um
                        tempo — incompleto é melhor do que errado.
                    </p>
                </div>
                <div class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">Nunca edite arquivo direto pelo servidor.</p>
                    <p>
                        Qualquer mudança de código, texto de página ou configuração precisa entrar pelo repositório
                        (Git) e ir ao ar através do <code>deploy.sh</code> — nunca editando um arquivo manualmente no
                        servidor por SSH. Uma edição feita direto lá se perde no próximo deploy, sem nenhum aviso,
                        porque o deploy sempre substitui o código pelo que está no GitHub — ele não sabe (nem tem
                        como saber) que alguém mexeu manualmente por fora.
                    </p>
                </div>
                <div class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">Nunca rode um seeder em produção sem saber exatamente o que ele faz.</p>
                    <p>
                        Um seeder de carga de marca pode reescrever, sem avisar, taxas que você já aprovou
                        manualmente no painel — ou, em casos já vistos de verdade neste projeto, apagar dado que não
                        deveria mais ser tocado. Rodar um seeder em produção é trabalho para o Claude fazer, com o
                        cuidado de ler o comando inteiro antes de executar, nunca uma tarefa para fazer de improviso.
                    </p>
                </div>
            </div>
        </section>

        <hr class="border-gray-200 dark:border-gray-800" />

        {{-- GLOSSÁRIO --}}
        <section id="glossario" class="scroll-mt-20 space-y-5">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Glossário de termos</h2>
            <p>
                Palavras do domínio deste projeto que aparecem no painel e neste manual, explicadas em uma ou duas
                frases cada, em ordem alfabética.
            </p>

            <dl class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                @php
                    $termos = [
                        'Adquirente' => 'A instituição que processa o pagamento por trás de uma marca (ex.: Cielo, PagSeguro). Campo de transparência — duas marcas diferentes às vezes revendem o mesmo adquirente.',
                        'Bandeira' => 'A rede do cartão (Visa, Mastercard, Elo, American Express...) ou uma bandeira de vale-refeição/alimentação (Alelo, VR, Ticket...). É o que está escrito no cartão físico.',
                        'Cupom' => 'Código de desconto que dá vantagem na adesão de uma maquininha. A taxa cobrada pelo cartão é sempre a mesma, com ou sem cupom — o desconto é só no custo inicial do equipamento.',
                        'Deploy' => 'O processo de publicar código novo no site em produção — puxar a versão mais recente do GitHub, instalar dependências, rodar migrações do banco e reiniciar os caches. Feito pelo script deploy.sh.',
                        'Equipamento' => 'A maquininha física em si — modelo, foto, ficha técnica. O preço dela varia por plano, então mora num vínculo separado, não no cadastro do equipamento.',
                        'Faixa reportada' => 'Uma estimativa de taxa baseada em relatos de lojistas (mediana, mínimo e máximo), usada só para marcas que não publicam tabela própria. Nunca aparece como número exato, sempre como intervalo.',
                        'Grupo de bandeira' => 'Como uma marca agrupa as bandeiras para efeito de preço — por exemplo, "Visa e Mastercard" numa coluna e "demais bandeiras" noutra. Cada marca decide o próprio agrupamento.',
                        'JSON do comparador' => 'O arquivo público (comparador.json) que o site lê no navegador para mostrar a calculadora de comparação, sem consultar o banco de dados a cada visita. Ver seção 4.',
                        'Marca' => 'A maquininha como o lojista a conhece pelo nome — Ton, PagBank, InfinitePay. A entidade raiz de todo o catálogo.',
                        'Plano' => 'Uma oferta específica de uma marca — o plano padrão, um plano promocional, um plano por faixa de faturamento. A taxa sempre pertence a um plano, nunca diretamente à marca.',
                        'Prazo de recebimento' => 'Quanto tempo depois da venda o dinheiro cai na conta do lojista — na hora, D+1 (um dia útil depois), D+30, ou parcela a parcela. É uma das dimensões que definem uma taxa.',
                        'Rascunho / Publicado' => 'O status de uma taxa dentro do painel. Rascunho não aparece no site; Publicado aparece (depois de o JSON ser regenerado — ver seção 4). Ver seção 3 para os outros três estados (aferido, reportado, vencido).',
                        'Regra 9' => 'Como o time chama, internamente, a decisão de o comparador ler um arquivo gerado em vez de consultar o banco a cada visita — para o site aguentar picos de tráfego de vídeo sem cair.',
                        'Regra 10' => 'Como o time chama, internamente, a exigência de que nenhum dado coletado automaticamente (de sites de marca, de relatos de lojista) vá ao ar sem um humano revisar e aprovar antes.',
                        'Seeder' => 'Um script de carga de dados em massa, usado para popular o banco pela primeira vez ou trazer um catálogo novo. Rodar de novo em produção é arriscado — ver seção 8.',
                        'Selo de frescor' => 'A marcação visual, no site público, de quão recente é a verificação de uma taxa. Degrada de "aferida" para "desatualizada" sozinha aos 45 dias, contados da data de verificação.',
                        'SITE_EM_BREVE' => 'Uma chave de configuração que, enquanto ligada, fecha o site público ao ar (mostra uma página "em breve" para qualquer visitante) sem afetar o painel /admin, que continua acessível.',
                        'SSH' => 'O protocolo usado para abrir um terminal de linha de comando dentro do servidor remotamente, a partir do computador. É como se digita comandos direto no servidor.',
                        '2FA (autenticação em duas etapas)' => 'Uma segunda trava de segurança no login, além da senha — um código de 6 dígitos que muda a cada 30 segundos, gerado por um aplicativo autenticador no celular.',
                    ];
                @endphp
                @foreach ($termos as $termo => $definicao)
                    <div class="grid gap-1 p-4 sm:grid-cols-[220px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-950 dark:text-white">{{ $termo }}</dt>
                        <dd>{{ $definicao }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

    </div>
</x-filament-panels::page>

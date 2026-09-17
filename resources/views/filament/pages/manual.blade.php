<x-filament-panels::page>
    <div class="manual-do-admin space-y-10 text-sm leading-relaxed text-gray-700 dark:text-gray-300">

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="text-sm">
                Este manual é para você, ou para quem assumir o painel depois de você, sem precisar entender de código.
                Se um passo daqui não bater mais com o que você vê na tela, o painel mudou e este texto ficou atrasado —
                me avise para eu atualizar.
            </p>
        </div>

        {{-- ÍNDICE --}}
        <nav aria-label="Índice do manual" class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Neste manual</p>
            <ol class="grid gap-1 sm:grid-cols-2">
                <li><a href="#ordem-de-cadastro" class="text-primary-600 hover:underline dark:text-primary-400">1. A ordem certa de cadastro</a></li>
                <li><a href="#lancar-taxas" class="text-primary-600 hover:underline dark:text-primary-400">2. Lançar a tabela de taxas de uma marca</a></li>
                <li><a href="#estados" class="text-primary-600 hover:underline dark:text-primary-400">3. O que significa cada estado</a></li>
                <li><a href="#aprovar-e-json" class="text-primary-600 hover:underline dark:text-primary-400">4. Aprovar taxas e regenerar o JSON</a></li>
                <li><a href="#filas-de-revisao" class="text-primary-600 hover:underline dark:text-primary-400">5. As duas filas de revisão de lojistas</a></li>
                <li><a href="#painel-de-saude" class="text-primary-600 hover:underline dark:text-primary-400">6. Como ler o painel de saúde</a></li>
                <li><a href="#restaurar-backup" class="text-primary-600 hover:underline dark:text-primary-400">7. Como restaurar um backup</a></li>
                <li><a href="#nunca-fazer" class="text-primary-600 hover:underline dark:text-primary-400">8. O que nunca fazer</a></li>
            </ol>
        </nav>

        {{-- 1. ORDEM DE CADASTRO --}}
        <section id="ordem-de-cadastro" class="scroll-mt-20 space-y-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">1. A ordem certa de cadastro</h2>
            <p>
                Cada coisa que você cadastra depende da que veio antes dela existir. Cadastrar fora de ordem não quebra a
                tela na hora — quebra depois, de um jeito confuso de rastrear. A ordem é sempre esta:
            </p>
            <ol class="ml-5 list-decimal space-y-2">
                <li>
                    <strong>Adquirente</strong> <span class="text-gray-500 dark:text-gray-400">(menu Dimensões → Adquirentes)</span>.
                    É quem processa o pagamento por trás da marca — a Cielo, o PagSeguro/PagBank, etc. Só existe para você
                    saber, por transparência, que duas marcas diferentes às vezes usam o mesmo adquirente por trás. Não é
                    obrigatório — se você não sabe qual é, deixe em branco.
                </li>
                <li>
                    <strong>Marca</strong> <span class="text-gray-500 dark:text-gray-400">(menu Catálogo → Marcas)</span>.
                    A maquininha em si — Ton, PagBank, etc. Sem a marca existir, nada do resto tem onde pendurar.
                </li>
                <li>
                    <strong>Plano</strong> <span class="text-gray-500 dark:text-gray-400">(menu Catálogo → Planos, ou pela aba
                    "Planos" dentro da marca)</span>.
                    Cada marca pode ter mais de um plano — o plano normal, o plano promocional dos primeiros 30 dias, um
                    plano por faixa de faturamento. <strong>A taxa nunca é da marca — é sempre do plano.</strong> Por isso o
                    plano vem antes da taxa: sem ele, não tem onde a taxa morar.
                </li>
                <li>
                    <strong>Equipamento</strong> <span class="text-gray-500 dark:text-gray-400">(menu Catálogo → Equipamentos)</span>.
                    A maquininha física — modelo, foto, ficha técnica. O preço de adesão dela (quanto custa para comprar)
                    é cadastrado separado, vinculando o equipamento a cada plano — porque a mesma maquininha pode custar
                    diferente dependendo do plano escolhido.
                </li>
                <li>
                    <strong>Cupom</strong> <span class="text-gray-500 dark:text-gray-400">(menu Catálogo → Cupons)</span>.
                    O código de desconto na adesão. Vem por último porque pertence à marca como um todo (não a um
                    equipamento específico, na imensa maioria dos casos) e não faz sentido sem a marca já existir.
                </li>
            </ol>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
                <strong>O que quebra se você inverter a ordem:</strong> tentar cadastrar uma taxa antes do plano existir
                simplesmente não deixa você escolher o plano — o campo fica vazio, sem opção. Tentar vincular um
                equipamento a um plano que ainda não existe é a mesma história. O painel não deixa você seguir fora de
                ordem; ele só te avisa de um jeito menos claro do que este texto.
            </div>
        </section>

        {{-- 2. LANÇAR TAXAS EM LOTE --}}
        <section id="lancar-taxas" class="scroll-mt-20 space-y-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">2. Como lançar a tabela de taxas de uma marca em lote</h2>
            <p>
                Editar taxa por taxa, uma de cada vez, é o jeito lento. Existem duas telas para lançar uma tabela inteira
                de uma vez, e as duas ficam dentro de <strong>Taxas → Taxas Divulgadas</strong>:
            </p>
            <ul class="ml-5 list-disc space-y-2">
                <li>
                    <strong>"Lançamento em lote"</strong> — botão no topo da listagem de Taxas Divulgadas. Abre uma grade
                    em branco: você escolhe a marca, o prazo de recebimento (na hora, D+1, D+30...) e preenche as colunas
                    de 1x a 21x. Use para lançar uma marca que ainda não tem nada.
                </li>
                <li>
                    <strong>"Tabela do plano"</strong> — botão na coluna "Plano" da mesma listagem, ou na listagem de
                    Planos. Abre a tabela <em>já preenchida</em> com o que existe hoje para aquele plano — débito, crédito
                    de 1x a 21x, por bandeira e por prazo, tudo numa tela só. Use esta para corrigir ou completar um plano
                    que já tem taxa cadastrada — é a tela que resolve o dia a dia melhor.
                </li>
            </ul>
            <p>
                Nas duas telas, você digita o número de parcelas (1, 2, 3... até 21) e uma célula de percentual — o
                sistema decide sozinho o resto:
            </p>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <p><strong>1 parcela</strong> grava como <span class="numero">crédito à vista</span>.</p>
                <p><strong>De 2 a 21 parcelas</strong> grava como <span class="numero">crédito parcelado</span>.</p>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    É a mesma distinção que a bandeira do cartão usa: "passar no crédito" (1x) é uma operação diferente de
                    "parcelar" (2x pra cima), mesmo as duas sendo crédito. O painel só está seguindo essa mesma regra ao
                    decidir sozinho qual das duas gravar — você não escolhe isso num campo à parte.
                </p>
            </div>
            <p>
                Débito e Pix não têm essa dimensão de parcela (ninguém parcela um débito) — eles ficam em seções próprias
                dentro da mesma tela, um valor só cada.
            </p>
            <p>
                Cada célula que você preenche grava sozinha, na hora — não existe um botão "salvar tudo" no fim. Célula
                que você apaga <strong>remove</strong> a taxa que estava ali (uma taxa apagada é mais honesta do que uma
                taxa errada). Célula que você não toca continua exatamente como estava, com a fonte e a data de quando
                foi conferida da última vez — editar uma célula não bagunça as outras.
            </p>
        </section>

        {{-- 3. ESTADOS --}}
        <section id="estados" class="scroll-mt-20 space-y-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">3. O que significa cada estado</h2>
            <p>Cinco palavras aparecem espalhadas pelo painel e pelo site. Aqui está o que cada uma quer dizer, sem termo técnico:</p>

            <div class="space-y-3">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Rascunho</p>
                    <p>Você cadastrou o número, mas ele <strong>ainda não aparece no site</strong>. É o estado padrão de
                    toda taxa nova — nada vai ao ar sozinho.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Publicado</p>
                    <p>Você conferiu e aprovou — esse número está (ou vai ficar, no próximo passo 4) visível para quem
                    visita o site.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Aferido</p>
                    <p>É como o site chama, para o visitante, uma taxa <strong>publicada pela própria marca</strong> e
                    conferida há menos de 45 dias. É o estado "bom" — o site pinta esse dado em destaque porque é o mais
                    confiável que existe: veio direto da fonte e está fresco.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Reportado</p>
                    <p>Três situações diferentes usam essa mesma etiqueta no site, e as três têm em comum não ser um
                    número definitivo: (1) uma <strong>faixa</strong> — mediana de relatos de lojistas, para as marcas
                    que não publicam tabela (Cielo, Rede, GetNet, Stone); (2) uma <strong>promoção de entrada</strong>,
                    que vale só nos primeiros dias ou até um teto de vendas; (3) uma taxa <strong>já com mais de 45
                    dias</strong> sem reconferir — ela não some, só perde o destaque de "aferida" até alguém confirmar
                    de novo.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Vencido</p>
                    <p>Cupom fora da validade. Some sozinho do site quando a data passa — você não precisa lembrar de
                    apagar nada.</p>
                </div>
            </div>
        </section>

        {{-- 4. APROVAR E REGENERAR O JSON --}}
        <section id="aprovar-e-json" class="scroll-mt-20 space-y-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">4. Como aprovar taxas — e por que é preciso regenerar o JSON depois</h2>
            <p>
                Em <strong>Taxas → Taxas Divulgadas</strong> (ou <strong>Faixas Reportadas</strong>), marque as linhas que
                você já conferiu e use a ação em massa <strong>"Aprovar e publicar"</strong> — ou, dentro da tela "Tabela
                do plano", o botão <strong>"Publicar toda a tabela"</strong> no topo, que publica o plano inteiro de uma
                vez. Para desfazer, existe o par oposto: <strong>"Voltar para rascunho"</strong> / <strong>"Voltar tudo
                para rascunho"</strong>.
            </p>
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                <p class="font-semibold">Por que esse passo existe:</p>
                <p class="mt-1">
                    Marcar como "Publicado" no painel <strong>não muda o site sozinho</strong>. O comparador é a parte do
                    site que mais gente usa, e ele não consulta o banco de dados a cada visita — se consultasse, um
                    vídeo seu levando muita gente de uma vez ao site derrubaria o banco. Em vez disso, ele lê um arquivo
                    (<code>comparador.json</code>) gerado com antecedência, e esse arquivo só é reescrito quando alguém
                    manda. Sem isso, o site continua mostrando o número antigo — sem erro, sem aviso, só desatualizado
                    em silêncio.
                </p>
            </div>

            <p><strong>O jeito mais fácil: um botão, direto no painel.</strong> Sem terminal, sem SSH, sem nada para
            copiar errado. Ele aparece em três lugares — logo ao entrar no painel (Dashboard), e nas duas telas onde
            você aprova taxa (listagem de Taxas Divulgadas e "Tabela do plano”):</p>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200">
                <p>Clique em <strong>"Gerar JSON do comparador"</strong>, confirme, e pronto — o painel avisa quantas
                marcas e taxas entraram no arquivo novo. Essa é a forma recomendada; use-a sempre que puder.</p>
            </div>

            <p>
                O próprio botão avisa quando há trabalho pendente: um selo laranja <strong>"Pendente"</strong> aparece
                ao lado dele sempre que existe alguma aprovação, edição ou despublicação ainda não refletida no
                arquivo — comparado pelo conteúdo, não só pela data, então editar uma taxa que ainda está em rascunho
                (as marcas que você for curando aos poucos) não aciona o aviso à toa. Sem o selo, o arquivo já está em
                dia. O selo some sozinho assim que você clica e a geração termina.
            </p>

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

        {{-- 5. FILAS DE REVISÃO --}}
        <section id="filas-de-revisao" class="scroll-mt-20 space-y-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">5. As duas filas de revisão de relatos de lojistas</h2>
            <p>Ficam no grupo <strong>"Relatos de lojistas"</strong> do menu:</p>
            <ul class="ml-5 list-disc space-y-2">
                <li>
                    <strong>Propostas Recebidas</strong> — quando um lojista conta, pelo formulário público, a proposta
                    que recebeu da Cielo, Rede, GetNet ou Stone (essas quatro não publicam tabela em lugar nenhum, então
                    é a única fonte que existe para elas). Tem um botão "Baixar anexo" quando a pessoa mandou foto ou PDF
                    da proposta.
                </li>
                <li>
                    <strong>Relatos de Taxa Incorreta</strong> — quando alguém clica em "reportar taxa errada" no site e
                    descreve o que está errado.
                </li>
            </ul>
            <p>Nas duas, o filtro "Status" separa <strong>Pendente</strong> (ninguém olhou ainda) de <strong>Revisado</strong>.</p>
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                <p class="font-semibold">O que nunca fazer com elas:</p>
                <p class="mt-1">
                    Essas duas telas <strong>não publicam nada sozinhas</strong> — não existe um botão que transforma um
                    relato direto numa taxa no site. É assim de propósito: um relato de lojista é a palavra de uma
                    pessoa, não uma fonte oficial. Ler, decidir se faz sentido, e <strong>cadastrar a faixa reportada (ou
                    corrigir a taxa) você mesmo, à mão</strong>, nas telas normais de Taxas — só então marcar o relato
                    como "Revisado". Marcar como revisado sem ter conferido nada é o mesmo erro que publicar taxa sem
                    fonte.
                </p>
            </div>
        </section>

        {{-- 6. PAINEL DE SAÚDE --}}
        <section id="painel-de-saude" class="scroll-mt-20 space-y-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">6. Como ler o painel de saúde e o que fazer com cada alerta</h2>
            <p>
                A tela inicial do <code>/admin</code> (o Dashboard) já é o painel de saúde — não precisa abrir SSH nem o
                hPanel da Hostinger para saber se o site está bem. Regra geral: <strong>verde é "ok", laranja é "de
                olho", vermelho é "resolver logo"</strong>. Cartão cinza com "Sem dado" não é alarme — é honestidade:
                aquele número não foi medido ainda (normalmente porque falta uma configuração externa, não um problema).
            </p>

            <div class="space-y-3">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Links de afiliado quebrados</p>
                    <p><strong>É o alerta mais importante da lista.</strong> Link morto é receita perdida e, sem esse
                    cartão, completamente invisível. Vermelho aqui: abra a marca ou o cupom apontado, confira o link à
                    mão e corrija a URL.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Taxas não verificadas há mais de 30 dias / faixas de frescor</p>
                    <p>Antecipa o selo de "desatualizada" do site (que só aparece com 45 dias). Alerta aqui: separe um
                    tempo para reabrir essas marcas, conferir o site oficial de novo e reconfirmar o número — mesmo que
                    ele não tenha mudado, atualizar a data já resolve.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Cupons vencendo em 7 dias</p>
                    <p>Contate o parceiro para confirmar se o cupom continua valendo e, se sim, atualize a validade no
                    cadastro do cupom.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Marcas sem taxa cadastrada / Propostas e relatos pendentes / Detecções do monitor pendentes</p>
                    <p>Três filas de trabalho pendente. Cada uma tem um link direto no próprio cartão — clique nele para
                    ir reto à tela que resolve.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Backup diário</p>
                    <p><strong>"Ok"</strong> quer dizer que rodou de madrugada e terminou direito, com cópia enviada
                    também para o Google Drive. <strong>"Falhou"</strong> ou um horário muito antigo: me avise — é o tipo
                    de coisa que não se resolve sozinha e que você só descobre tarde se ninguém olhar aqui.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Jobs pendentes na fila</p>
                    <p>Deveria ser sempre zero neste projeto. <strong>Qualquer número diferente de zero é estranho</strong> —
                    quer dizer que uma configuração mudou sem querer. Me avise se aparecer.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Certificado SSL / APP_DEBUG / Permissão do .env / Usuários sem 2FA</p>
                    <p>Segurança básica do servidor. SSL vencendo em poucos dias, <code>APP_DEBUG</code> ligado em
                    produção, ou alguém sem o autenticador configurado — qualquer um desses em vermelho merece atenção
                    rápida, mas nenhum deles muda sozinho: normalmente é sinal de que algo foi mexido manualmente no
                    servidor.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="font-semibold text-gray-950 dark:text-white">Cliques em cupom e Tráfego</p>
                    <p>Não são alerta — são acompanhamento. Cliques mostra quantas pessoas copiaram código ou clicaram
                    em "usar cupom", por marca e por cupom, para bater com o relatório que os parceiros mandam no fim do
                    mês. Tráfego mostra visitas por dia — só aparece depois que o Everton configurar o token da
                    Cloudflare; até lá o cartão fica vazio de propósito, e isso não é um bug.</p>
                </div>
            </div>
        </section>

        {{-- 7. RESTAURAR BACKUP --}}
        <section id="restaurar-backup" class="scroll-mt-20 space-y-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">7. Como restaurar um backup</h2>
            <p>
                Precisa de acesso SSH ao servidor. Se você não tem certeza do que está fazendo, é mais seguro pedir para
                eu rodar isso e te mostrar o resultado antes de qualquer confirmação.
            </p>
            <ol class="ml-5 list-decimal space-y-3">
                <li>
                    <strong>Ache o carimbo de data/hora do backup que você quer.</strong>
<pre class="mt-1 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>ssh comparador 'ls -lht ~/backups/comparador/ | head -10'</code></pre>
                    Os nomes seguem o padrão <code>banco-2026-09-17_030002.sql.gz</code> — a data e a hora estão no
                    próprio nome do arquivo.
                </li>
                <li>
                    <strong>Restaure o banco</strong> (troque <code>&lt;carimbo&gt;</code> pelo que você achou acima):
<pre class="mt-1 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>gunzip -c ~/backups/comparador/banco-&lt;carimbo&gt;.sql.gz \
  | mysql --defaults-extra-file=~/.comparador-backup.cnf u835756808_comparador</code></pre>
                    O dump já vem com instrução para apagar cada tabela antes de recriar — não precisa limpar nada à
                    mão antes.
                </li>
                <li>
                    <strong>Restaure os arquivos enviados</strong> (logos, fotos de equipamento, anexos de proposta), se precisar:
<pre class="mt-1 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>tar -xzf ~/backups/comparador/arquivos-&lt;carimbo&gt;.tar.gz -C ~/domains/maquinacerta.com.br/comparador</code></pre>
                </li>
                <li>
                    <strong>Restaure o <code>.env</code> só se a chave de segurança (APP_KEY) tiver se perdido</strong> —
                    é a exceção, não o padrão:
<pre class="mt-1 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>cp ~/backups/comparador/env-&lt;carimbo&gt; ~/domains/maquinacerta.com.br/comparador/.env
chmod 600 ~/domains/maquinacerta.com.br/comparador/.env</code></pre>
                </li>
                <li>
                    <strong>Sempre termine rodando o deploy</strong>, mesmo que você não tenha restaurado arquivo nem
                    <code>.env</code> — os caches do site ficam apontando para o estado de antes até isso rodar:
<pre class="mt-1 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100"><code>ssh comparador '~/domains/maquinacerta.com.br/comparador/deploy.sh'</code></pre>
                </li>
            </ol>
            <p class="text-gray-500 dark:text-gray-400">
                Se o backup do próprio dia estiver corrompido ou o disco do servidor tiver sumido junto, os três arquivos
                também existem no Google Drive (pasta <code>backups-maquina-certa</code>) — baixe com
                <code>rclone copy gdrive:&lt;nome-do-arquivo&gt; .</code> antes de seguir os mesmos passos.
            </p>
        </section>

        {{-- 8. NUNCA FAZER --}}
        <section id="nunca-fazer" class="scroll-mt-20 space-y-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">8. O que nunca fazer</h2>
            <div class="space-y-3">
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">Nunca publique uma taxa sem fonte e sem data de verificação.</p>
                    <p class="mt-1">
                        Campo vazio é honesto — um número errado no ar é risco real de propaganda enganosa (CDC). Se você
                        não tem certeza de um número, deixe em rascunho até confirmar, mesmo que isso signifique a marca
                        ficar incompleta por um tempo no site.
                    </p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">Nunca edite arquivo direto pelo servidor.</p>
                    <p class="mt-1">
                        Qualquer mudança de código, texto de página ou configuração entra pelo repositório (Git) e vai ao
                        ar pelo <code>deploy.sh</code> — nunca editando um arquivo à mão no servidor por SSH. Uma edição
                        direta lá se perde no próximo deploy, sem aviso, porque o deploy sempre busca a versão do
                        GitHub.
                    </p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                    <p class="font-semibold">Nunca rode um seeder em produção sem saber exatamente o que ele faz.</p>
                    <p class="mt-1">
                        Um seeder de carga de marca pode reescrever taxas que você já aprovou no painel, ou — em casos já
                        vistos neste projeto — apagar dado que não devia mais tocar. Rodar isso em produção é trabalho
                        do Claude, com o cuidado de ler o comando inteiro antes, não uma tarefa para fazer de improviso.
                    </p>
                </div>
            </div>
        </section>

    </div>
</x-filament-panels::page>

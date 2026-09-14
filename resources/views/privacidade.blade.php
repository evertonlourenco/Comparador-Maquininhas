@php
    $navegacao = [];
    $razaoSocial = config('services.legal.razao_social');
    $cnpj = config('services.legal.cnpj');
    $emailContato = config('services.legal.email_contato');
@endphp

<x-layouts.site
    titulo="Política de Privacidade"
    descricao="Como tratamos os dados que você envia neste site, com que base legal, e como falar com o responsável — conforme a LGPD."
    :navegacao="$navegacao"
    canonical="{{ url('/privacidade') }}"
>
    <div class="mx-auto w-full max-w-2xl space-y-10 px-4 py-8 sm:px-6">
        <header class="space-y-3">
            <h1 class="text-manchete">Política de Privacidade</h1>
            <p class="text-subtitulo text-tinta-suave">
                Última atualização em <time datetime="{{ now()->toDateString() }}" class="numero">{{ \App\Support\Dinheiro::data(now()) }}</time>.
            </p>
        </header>

        <section aria-labelledby="s-controlador" class="space-y-3">
            <h2 id="s-controlador" class="text-titulo">Quem é o responsável por este site</h2>
            @if ($razaoSocial)
                <p class="text-tinta">
                    {{ $razaoSocial }}{{ $cnpj ? ', CNPJ '.$cnpj : '' }}, é quem controla os dados
                    tratados neste site, na forma da Lei Geral de Proteção de Dados (Lei 13.709/2018).
                </p>
            @else
                <p class="rounded-bloco border border-reportado bg-reportado-fundo px-4 py-3 text-sm text-reportado">
                    A identificação completa do responsável (razão social e CNPJ) ainda está a
                    definir e entra aqui antes do lançamento do site. Um campo vazio é o estado
                    honesto até essa decisão ser tomada — preferimos isso a inventar um dado.
                </p>
            @endif
            <p class="text-tinta">
                Contato para qualquer assunto de privacidade:
                @if ($emailContato)
                    <a href="mailto:{{ $emailContato }}" class="text-link underline underline-offset-4 hover:no-underline">{{ $emailContato }}</a>.
                @else
                    <span class="text-reportado">e-mail de contato a definir antes do lançamento.</span>
                @endif
            </p>
        </section>

        <section aria-labelledby="s-dados" class="space-y-3">
            <h2 id="s-dados" class="text-titulo">Quais dados coletamos, e por quê</h2>

            <div class="space-y-2">
                <h3 class="font-medium text-tinta">Formulário de proposta recebida (/enviar-proposta)</h3>
                <p class="text-tinta">
                    É anônimo por desenho: não pedimos nome nem e-mail. Coletamos a marca, as taxas
                    e condições da proposta, o estado, o segmento do negócio e o faturamento
                    aproximado que você informa, além de um anexo opcional (foto ou PDF da
                    proposta). Base legal: <strong class="font-medium">consentimento</strong> —
                    você marca a caixa de autorização antes de enviar, e o texto ao lado dela é a
                    finalidade exata: uso agregado e anônimo, nunca como número isolado.
                </p>
            </div>

            <div class="space-y-2">
                <h3 class="font-medium text-tinta">Botão "reportar taxa errada"</h3>
                <p class="text-tinta">
                    Coletamos a mensagem que você escreve e, só se você quiser ser respondido, um
                    e-mail de contato — o campo é opcional. Base legal:
                    <strong class="font-medium">consentimento</strong>, dado ao enviar o formulário.
                </p>
            </div>

            <div class="space-y-2">
                <h3 class="font-medium text-tinta">Navegação e cookies</h3>
                <p class="text-tinta">
                    Sua preferência de tema (claro/escuro) fica só no seu navegador
                    (<code>localStorage</code>), nunca chega a nós. Com sua autorização no banner
                    de cookies, usamos o Google Analytics para entender quantas pessoas visitam o
                    site e quais páginas usam mais — nunca sem esse aceite prévio. Base legal:
                    <strong class="font-medium">consentimento</strong> para analytics,
                    <strong class="font-medium">legítimo interesse</strong> para o cookie técnico de
                    tema (essencial ao funcionamento, e nem chega ao servidor).
                </p>
            </div>
        </section>

        <section aria-labelledby="s-comissao" class="space-y-3">
            <h2 id="s-comissao" class="text-titulo">Links de afiliado</h2>
            <p class="text-tinta">
                Quando você clica num link para contratar uma maquininha, registramos o clique e o
                código de cupom usado (não dados pessoais seus) para fins de comissionamento com a
                marca parceira. A taxa mostrada é a mesma do site oficial da marca.
            </p>
        </section>

        <section aria-labelledby="s-direitos" class="space-y-3">
            <h2 id="s-direitos" class="text-titulo">Seus direitos</h2>
            <p class="text-tinta">
                Pelo art. 18 da LGPD, você pode pedir confirmação de tratamento, acesso,
                correção, anonimização, portabilidade ou eliminação dos seus dados, e revogar
                consentimento a qualquer momento. Como o formulário de proposta é anônimo, não há
                como localizar um envio específico sem informação que o identifique — se você
                enviou um anexo com dado pessoal visível (ex.: nome numa foto), esse é o único
                caminho de vincular o envio a você. Para exercer qualquer direito, escreva para o
                contato do topo desta página.
            </p>
        </section>

        <section aria-labelledby="s-cookies-gerenciar" class="space-y-3">
            <h2 id="s-cookies-gerenciar" class="text-titulo">Mudar sua escolha de cookies</h2>
            <p class="text-tinta">
                O link "Gerenciar cookies", no rodapé de qualquer página, reabre o banner de
                consentimento para você aceitar ou recusar de novo.
            </p>
        </section>
    </div>
</x-layouts.site>

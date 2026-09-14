{{--
    Pagina do modo "em breve" (config/site.php).

    Standalone de proposito: nao usa <x-layouts.site> porque esse layout traz
    navegacao, rodape institucional e o banner de cookies — nada disso faz
    sentido num site que ainda nao abriu, e o rodape apontaria para paginas
    que respondem 503.

    O status 503 quem devolve e o middleware SiteEmBreve. Aqui vai tambem o
    noindex, como segunda tranca: se algum dia a pagina for servida com 200
    por engano, ela continua fora do indice.

    O script inline de tema abaixo e byte a byte o mesmo do layout: o hash
    SHA-256 dele esta fixado na CSP (CabecalhosDeSeguranca e regra do
    Cloudflare). Nao editar sem recalcular nos dois lugares.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }} — em breve</title>

    <script @if (isset($cspNonce)) nonce="{{ $cspNonce }}" @endif>
        (function () {
            try {
                var salvo = localStorage.getItem('tema');
                var escuro = salvo
                    ? salvo === 'escuro'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.dataset.tema = escuro ? 'escuro' : 'claro';
            } catch (e) {}
        })();
    </script>

    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/png" sizes="192x192" href="/marca/favicon-192.png">
    <link rel="apple-touch-icon" href="/marca/favicon-192.png">
    <meta name="theme-color" content="#18264b">

    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh flex-col items-center justify-center bg-superficie px-4 py-10 text-tinta antialiased">

    <main class="w-full max-w-xl rounded-bloco border border-regua bg-papel px-6 py-10 text-center sm:px-10 sm:py-14">
        <h1 class="sr-only">{{ config('app.name') }} — em breve</h1>

        <img
            src="/marca/logo-horizontal-assinatura.png"
            srcset="/marca/logo-horizontal-assinatura.png 1x, /marca/logo-horizontal-assinatura-2x.png 2x"
            width="593" height="192"
            alt="{{ config('app.name') }}, by Monetizando"
            class="so-tema-claro mx-auto h-auto w-56 sm:w-72"
        >
        <img
            src="/marca/logo-horizontal-assinatura-negativo.png"
            srcset="/marca/logo-horizontal-assinatura-negativo.png 1x, /marca/logo-horizontal-assinatura-negativo-2x.png 2x"
            width="593" height="192"
            alt="{{ config('app.name') }}, by Monetizando"
            class="so-tema-escuro mx-auto h-auto w-56 sm:w-72"
        >

        <p class="mx-auto mt-8 inline-flex items-center rounded-selo bg-acao-fundo px-2.5 py-1 text-selo font-semibold uppercase text-acao">
            Em breve
        </p>

        <p class="mx-auto mt-5 max-w-md font-titulo text-2xl leading-tight font-semibold text-balance sm:text-3xl">
            Um comparador de taxas de maquininhas de cartão, feito para quem
            vende e precisa saber quanto custa receber.
        </p>

        <p class="mx-auto mt-5 max-w-md text-tinta-suave">
            Estamos conferindo cada taxa na fonte oficial antes de publicar.
            Número errado aqui custa dinheiro de verdade a quem confia nele,
            então preferimos demorar.
        </p>
    </main>

</body>
</html>

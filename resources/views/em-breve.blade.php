{{--
    Pagina do modo "em breve" (config/site.php).

    Standalone de proposito: nao usa <x-layouts.site> porque esse layout traz
    navegacao, rodape institucional e o banner de cookies — nada disso faz
    sentido num site que ainda nao abriu, e o rodape apontaria para paginas
    que respondem 503.

    O status 503 quem devolve e o middleware SiteEmBreve. Aqui vai tambem o
    noindex, como segunda tranca: se algum dia a pagina for servida com 200
    por engano, ela continua fora do indice.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }} — em breve</title>

    <script>
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

    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh flex-col items-center justify-center bg-papel px-6 text-tinta antialiased">

    <main class="w-full max-w-xl text-center">
        <p class="font-titulo text-3xl sm:text-4xl">{{ config('app.name') }}</p>

        <hr class="mx-auto my-8 w-16 border-0 border-t border-regua-forte">

        <h1 class="text-xl font-medium sm:text-2xl">Em breve</h1>

        <p class="mx-auto mt-4 max-w-md text-balance text-tinta-suave">
            Um comparador de taxas de maquininhas de cartão, feito para quem
            vende e precisa saber quanto custa receber.
        </p>

        <p class="mx-auto mt-6 max-w-md text-sm text-tinta-suave">
            Estamos conferindo cada taxa na fonte oficial antes de publicar.
            Número errado aqui custa dinheiro de verdade a quem confia nele,
            então preferimos demorar.
        </p>
    </main>

</body>
</html>

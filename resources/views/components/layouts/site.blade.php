@props([
    'titulo' => null,
    'descricao' => null,
    'navegacao' => [],
    'linksRodape' => [],
    'atualizadoEm' => null,
    'indexavel' => true,
    // Entradas do Vite alem do par basico. O comparador (etapa 07) traz
    // Alpine e os dois motores, e eles nao tem por que pesar nas outras
    // paginas.
    'scripts' => [],
])

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">

    <title>{{ $titulo ? $titulo.' — Comparador de Maquininhas' : 'Comparador de Maquininhas' }}</title>
    @if ($descricao)
        <meta name="description" content="{{ $descricao }}">
    @endif
    @unless ($indexavel)
        <meta name="robots" content="noindex, nofollow">
    @endunless

    {{-- Antes da folha de estilo, para o tema nao piscar. Se o script falhar
         ou o JavaScript estiver desligado, nao ha atributo e o
         prefers-color-scheme do app.css assume. --}}
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
    @vite(array_merge(['resources/css/app.css', 'resources/js/app.js'], $scripts))
</head>
<body class="flex min-h-dvh flex-col bg-papel text-tinta antialiased">
    <a href="#conteudo"
       class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded-selo focus:bg-tinta focus:px-4 focus:py-2 focus:text-papel">
        Pular para o conteúdo
    </a>

    <x-cabecalho :navegacao="$navegacao" />

    {{-- tabindex="-1" para o link de pular entregar o foco de fato. --}}
    <main id="conteudo" tabindex="-1" class="flex-1 focus:outline-none">
        {{ $slot }}
    </main>

    <x-rodape :links="$linksRodape" :atualizado-em="$atualizadoEm" />

    {{-- Confirmacoes curtas (copiar cupom) chegam aqui para o leitor de tela. --}}
    <div data-avisos role="status" aria-live="polite" class="sr-only"></div>
</body>
</html>

{{--
    Etapa 10: consentimento de cookies. Escondido por padrao (`hidden`) — o
    app.js decide se mostra, lendo o que ja esta salvo em localStorage.
    Analytics (GA4) so carrega depois do clique em "Aceitar", nunca antes —
    ver App\Support (config('services.ga4.id')) e a funcao carregarAnalytics()
    em resources/js/app.js.
--}}
<div
    data-banner-cookies
    hidden
    role="region"
    aria-label="Consentimento de cookies"
    class="fixed inset-x-0 bottom-0 z-40 border-t border-regua-forte bg-papel"
>
    <div class="mx-auto flex w-full max-w-5xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4 sm:px-6">
        <p class="min-w-[16rem] flex-1 text-sm text-tinta">
            Usamos cookies essenciais para o site funcionar e, só com sua autorização,
            cookies de estatística de visita. Leia a
            <a href="{{ route('privacidade') }}" class="text-link underline underline-offset-4 hover:no-underline">política de privacidade</a>.
        </p>

        {{-- No celular os dois botoes dividem a largura, com o mesmo peso: nenhum
             dos dois e a "acao principal" (LGPD), entao nenhum sai em verde. --}}
        <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:shrink-0">
            <button type="button" data-cookies-recusar class="min-h-12 rounded-botao border-[1.5px] border-tinta px-5 font-titulo text-[0.9375rem] font-semibold text-tinta hover:bg-superficie">
                Recusar
            </button>
            <button type="button" data-cookies-aceitar class="min-h-12 rounded-botao border-[1.5px] border-tinta bg-tinta px-5 font-titulo text-[0.9375rem] font-semibold text-papel transition-opacity hover:opacity-90">
                Aceitar
            </button>
        </div>
    </div>
</div>

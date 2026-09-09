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
    class="fixed inset-x-0 bottom-0 z-40 border-t border-regua-forte bg-superficie-forte"
>
    <div class="mx-auto flex w-full max-w-5xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4 sm:px-6">
        <p class="min-w-0 flex-1 text-sm text-tinta">
            Usamos cookies essenciais para o site funcionar e, só com sua autorização,
            cookies de estatística de visita. Leia a
            <a href="{{ route('privacidade') }}" class="text-link underline underline-offset-4 hover:no-underline">política de privacidade</a>.
        </p>

        <div class="flex shrink-0 gap-2">
            <button type="button" data-cookies-recusar class="min-h-11 rounded-selo border border-contorno px-4 text-sm font-medium text-tinta hover:bg-superficie">
                Recusar
            </button>
            <button type="button" data-cookies-aceitar class="min-h-11 rounded-selo bg-tinta px-4 text-sm font-medium text-papel hover:opacity-90">
                Aceitar
            </button>
        </div>
    </div>
</div>

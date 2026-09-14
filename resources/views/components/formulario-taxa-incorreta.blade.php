@props([
    // Sem marcaSlug o componente nao renderiza nada — guia-visual (amostra,
    // sem marca real) e o unico chamador que hoje deixa isto de fora.
    'marcaSlug' => null,
    'contexto' => null,
])

@if ($marcaSlug)
    <details class="text-miudo">
        <summary class="inline-flex min-h-11 cursor-pointer items-center text-link underline underline-offset-4 hover:no-underline">
            Taxa errada? Avise
        </summary>

        <form
            data-form-taxa-incorreta
            action="{{ route('eventos.taxa-incorreta') }}"
            method="post"
            class="mt-3 w-full max-w-md space-y-3 rounded-bloco border border-regua bg-superficie p-4"
        >
            @csrf
            <input type="hidden" name="marca" value="{{ $marcaSlug }}">
            <input type="hidden" name="contexto" value="{{ $contexto }}">
            <input type="hidden" name="pagina_url" value="{{ url()->current() }}">

            <div class="space-y-1">
                <label for="mensagem-{{ $marcaSlug }}" class="block font-medium text-tinta">O que está errado?</label>
                <textarea
                    id="mensagem-{{ $marcaSlug }}"
                    name="mensagem"
                    required
                    minlength="5"
                    maxlength="2000"
                    rows="3"
                    class="w-full rounded-botao border border-contorno bg-papel px-3 py-2 text-base text-tinta"
                ></textarea>
            </div>

            <div class="space-y-1">
                <label for="email-{{ $marcaSlug }}" class="block font-medium text-tinta">Seu e-mail (opcional, para respondermos)</label>
                <input
                    type="email"
                    id="email-{{ $marcaSlug }}"
                    name="email_contato"
                    maxlength="190"
                    class="min-h-12 w-full rounded-botao border border-contorno bg-papel px-3 py-2 text-base text-tinta"
                >
            </div>

            <x-campo-honeypot />

            <button type="submit" class="min-h-12 rounded-botao border-[1.5px] border-tinta bg-tinta px-5 font-titulo text-[0.9375rem] font-semibold text-papel hover:opacity-90">
                Enviar aviso
            </button>

            <p data-status-taxa-incorreta hidden role="status" class="text-reportado"></p>
        </form>
    </details>
@endif

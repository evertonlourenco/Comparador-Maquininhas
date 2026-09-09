/**
 * O pouco de JavaScript que o portal publico usa. O tema em si e escrito no
 * <html> pelo script inline do layout, antes da primeira pintura — aqui fica
 * so o que depende de interacao.
 */

const raiz = document.documentElement;

function avisar(texto) {
    const regiao = document.querySelector('[data-avisos]');
    if (!regiao) return;

    // Limpar antes faz o leitor de tela reanunciar quando o texto se repete.
    regiao.textContent = '';
    window.setTimeout(() => {
        regiao.textContent = texto;
    }, 50);
}

function sincronizarBotaoDeTema(botao) {
    const escuro = raiz.dataset.tema === 'escuro';
    botao.setAttribute('aria-pressed', String(escuro));

    const rotulo = botao.querySelector('[data-rotulo-tema]');
    if (rotulo) {
        rotulo.textContent = escuro ? 'Usar tema claro' : 'Usar tema escuro';
    }
}

document.querySelectorAll('[data-alternar-tema]').forEach((botao) => {
    sincronizarBotaoDeTema(botao);

    botao.addEventListener('click', () => {
        const escuro = raiz.dataset.tema !== 'escuro';
        raiz.dataset.tema = escuro ? 'escuro' : 'claro';

        try {
            localStorage.setItem('tema', raiz.dataset.tema);
        } catch (e) {
            // Navegacao anonima com armazenamento bloqueado: o tema vale so
            // para esta pagina, e tudo bem.
        }

        document.querySelectorAll('[data-alternar-tema]').forEach(sincronizarBotaoDeTema);
    });
});

/**
 * Etapa 09: um evento por clique em "usar cupom" (data-usar-cupom) ou por
 * copia de codigo (data-copiar, quando tambem carrega data-marca/data-cupom/
 * data-origem — x-bloco-cupom so os inclui quando recebe marcaSlug e origem).
 * "Fire and forget": uma falha de rede nao pode travar a copia nem a
 * navegacao do lojista, entao o erro so cai no console.
 */
function rastrearEventoCupom(tipoEvento, alvo) {
    const marca = alvo.dataset.marca;
    const codigo = alvo.dataset.cupom;
    const origem = alvo.dataset.origem;
    if (!marca || !codigo || !origem) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return;

    fetch('/eventos/cupons', {
        method: 'POST',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            marca,
            codigo,
            tipo_evento: tipoEvento,
            pagina_origem: origem,
        }),
    }).catch(() => {});
}

document.addEventListener('click', async (evento) => {
    const botao = evento.target.closest('[data-copiar]');
    if (!botao) return;

    const codigo = botao.dataset.copiar;

    try {
        await navigator.clipboard.writeText(codigo);
        avisar(`Código ${codigo} copiado.`);
    } catch (e) {
        // Sem permissao de area de transferencia (ou sem HTTPS): o codigo esta
        // visivel na tela ao lado do botao, entao da para copiar a mao.
        avisar('Não foi possível copiar. Selecione o código na tela.');
    }

    rastrearEventoCupom('copiar_codigo', botao);
});

document.addEventListener('click', (evento) => {
    const link = evento.target.closest('[data-usar-cupom]');
    if (!link) return;

    rastrearEventoCupom('usar_cupom', link);
});

/**
 * A listagem de marcas (etapa 08): marcar caixas de "comparar" monta o link
 * para o comparador com `?m=slug1,slug2` — o mesmo parametro que
 * resources/js/comparador/estado.mjs le na home. Vanilla, sem Alpine: essa
 * pagina nao precisa do bundle do comparador so por causa de uma caixa de
 * selecao.
 */
document.querySelectorAll('[data-selecao-marcas]').forEach((raiz) => {
    const irComparar = raiz.querySelector('[data-ir-comparar]');
    if (!irComparar) return;

    const contadores = raiz.querySelectorAll('[data-contagem-selecionadas]');
    const hrefBase = irComparar.getAttribute('href') || '/';

    function atualizar() {
        const slugs = Array.from(raiz.querySelectorAll('[data-marca-checkbox]:checked'))
            .map((caixa) => caixa.value)
            .sort();

        irComparar.href = slugs.length ? `${hrefBase}?m=${slugs.join(',')}` : hrefBase;
        irComparar.classList.toggle('pointer-events-none', slugs.length === 0);
        irComparar.setAttribute('aria-disabled', String(slugs.length === 0));

        contadores.forEach((contador) => {
            contador.textContent = String(slugs.length);
        });
    }

    raiz.addEventListener('change', (evento) => {
        if (evento.target.matches('[data-marca-checkbox]')) {
            atualizar();
        }
    });

    atualizar();
});

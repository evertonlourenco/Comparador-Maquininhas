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
 *
 * Etapa 12: o mesmo clique tambem vira evento de GA4 — "usar_cupom" e a
 * saida de afiliado de verdade (regra 5: o cupom desconta a adesao, nunca a
 * taxa; o clique que interessa para o parceiro e este), "copiar_codigo" e
 * a copia do codigo. window.gtag so existe depois do consentimento
 * (carregarAnalytics), entao o `?.` e a guarda inteira — sem fila, sem
 * evento contado antes do aceite.
 */
const NOME_DO_EVENTO_GA4 = {
    usar_cupom: 'clique_afiliado',
    copiar_codigo: 'copia_codigo',
};

function rastrearEventoCupom(tipoEvento, alvo) {
    const marca = alvo.dataset.marca;
    const codigo = alvo.dataset.cupom;
    const origem = alvo.dataset.origem;
    if (!marca || !codigo || !origem) return;

    window.gtag?.('event', NOME_DO_EVENTO_GA4[tipoEvento] ?? tipoEvento, {
        marca,
        cupom: codigo,
        pagina_origem: origem,
    });

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

/**
 * Etapa 10: consentimento de cookies. O banner so aparece quando ainda nao
 * ha escolha salva; "Aceitar" e o unico caminho que chama carregarAnalytics()
 * — "Recusar" so grava a escolha e esconde. Numa visita nova com "aceito" ja
 * salvo, o analytics carrega direto: o consentimento ja tinha sido dado
 * antes, entao carregar de novo nao fere "so depois do aceite".
 */
function carregarAnalytics() {
    const ga4Id = document.querySelector('meta[name="ga4-id"]')?.content;

    if (ga4Id && !document.querySelector('[data-gtag-script]')) {
        const script = document.createElement('script');
        script.async = true;
        script.dataset.gtagScript = '';
        script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga4Id)}`;
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        // Global, e nao uma funcao local: o comparador (etapa 07) e um bundle
        // Vite separado deste arquivo — so um window.gtag e alcancavel dos
        // dois lados. Enquanto o consentimento nao existir, window.gtag
        // simplesmente nao existe, e todo chamador usa `window.gtag?.(...)`
        // — sem fila, sem evento aceito antes da hora.
        window.gtag = function () {
            window.dataLayer.push(arguments);
        };
        window.gtag('js', new Date());
        window.gtag('config', ga4Id, { anonymize_ip: true });
    }

    // Etapa 12: mapa de calor e gravacao de sessao. Snippet padrao da
    // Microsoft — carrega independente do GA4 (um site pode ter so um dos
    // dois configurado). A mascara dos campos sensiveis fica no HTML
    // (data-clarity-mask, ver /enviar-proposta), nao aqui.
    const clarityId = document.querySelector('meta[name="clarity-id"]')?.content;

    if (clarityId && !document.querySelector('[data-clarity-script]')) {
        (function (c, l, a, r, i, t, y) {
            c[a] =
                c[a] ||
                function () {
                    (c[a].q = c[a].q || []).push(arguments);
                };
            t = l.createElement(r);
            t.async = 1;
            t.dataset.clarityScript = '';
            t.src = 'https://www.clarity.ms/tag/' + i;
            y = l.getElementsByTagName(r)[0];
            y.parentNode.insertBefore(t, y);
        })(window, document, 'clarity', 'script', clarityId);
    }
}

function lerConsentimentoCookies() {
    try {
        return localStorage.getItem('consentimento_cookies');
    } catch (e) {
        return null;
    }
}

function gravarConsentimentoCookies(valor) {
    try {
        localStorage.setItem('consentimento_cookies', valor);
    } catch (e) {
        // Sem armazenamento: o banner volta a aparecer na proxima visita, e
        // tudo bem — nao ha analytics sendo carregado indevidamente por isso.
    }
}

const bannerCookies = document.querySelector('[data-banner-cookies]');

if (bannerCookies) {
    const consentimento = lerConsentimentoCookies();

    if (consentimento === 'aceito') {
        carregarAnalytics();
    } else if (consentimento !== 'recusado') {
        bannerCookies.hidden = false;
    }

    bannerCookies.querySelector('[data-cookies-aceitar]')?.addEventListener('click', () => {
        gravarConsentimentoCookies('aceito');
        bannerCookies.hidden = true;
        carregarAnalytics();
    });

    bannerCookies.querySelector('[data-cookies-recusar]')?.addEventListener('click', () => {
        gravarConsentimentoCookies('recusado');
        bannerCookies.hidden = true;
    });
}

document.querySelectorAll('[data-gerenciar-cookies]').forEach((link) => {
    link.addEventListener('click', (evento) => {
        evento.preventDefault();
        gravarConsentimentoCookies('');
        if (bannerCookies) bannerCookies.hidden = false;
    });
});

/**
 * Etapa 10: o formulario "reportar taxa errada" (x-formulario-taxa-incorreta),
 * reaproveitado em toda <x-tabela-taxas>. Sem JavaScript o form e um POST
 * comum, que recarrega a pagina com a mensagem de confirmacao — aqui e so o
 * reforco progressivo, para nao perder o lugar da tabela na tela.
 */
document.querySelectorAll('[data-form-taxa-incorreta]').forEach((form) => {
    const status = form.querySelector('[data-status-taxa-incorreta]');

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!token) {
            form.submit();
            return;
        }

        const botao = form.querySelector('button[type="submit"]');
        if (botao) botao.disabled = true;

        try {
            const resposta = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });

            if (resposta.ok) {
                form.hidden = true;
                if (status) {
                    status.hidden = false;
                    status.textContent = 'Obrigado! Vamos conferir essa taxa.';
                }
                avisar('Obrigado! Vamos conferir essa taxa.');
            } else {
                const corpo = await resposta.json().catch(() => null);
                const mensagem = corpo?.errors?.mensagem?.[0] || 'Não foi possível enviar. Tente de novo.';
                if (status) {
                    status.hidden = false;
                    status.textContent = mensagem;
                }
                if (botao) botao.disabled = false;
            }
        } catch (e) {
            // Sem rede: deixa o formulario nativo assumir na proxima tentativa.
            if (botao) botao.disabled = false;
        }
    });
});

/**
 * Etapa 10: linhas de taxa repetiveis em /enviar-proposta. Quatro linhas
 * fixas (debito, credito a vista, credito parcelado, Pix) ja funcionam sem
 * JavaScript nenhum — isto so acrescenta um botao para casos que precisem de
 * mais de uma faixa de parcelamento, por exemplo.
 */
const containerLinhasTaxa = document.querySelector('[data-linhas-taxa]');
const modeloLinhaTaxa = document.querySelector('[data-modelo-linha-taxa]');
const botaoAdicionarLinhaTaxa = document.querySelector('[data-adicionar-linha-taxa]');

if (containerLinhasTaxa && modeloLinhaTaxa && botaoAdicionarLinhaTaxa) {
    let indiceLinhaTaxa = containerLinhasTaxa.querySelectorAll('[data-linha-taxa]').length;

    botaoAdicionarLinhaTaxa.addEventListener('click', () => {
        const linha = modeloLinhaTaxa.content.firstElementChild.cloneNode(true);

        linha.querySelectorAll('[name]').forEach((campo) => {
            campo.name = campo.name.replace('__INDICE__', String(indiceLinhaTaxa));
            campo.id = campo.id ? campo.id.replace('__INDICE__', String(indiceLinhaTaxa)) : campo.id;
        });
        linha.querySelectorAll('label[for]').forEach((rotulo) => {
            rotulo.htmlFor = rotulo.htmlFor.replace('__INDICE__', String(indiceLinhaTaxa));
        });

        containerLinhasTaxa.appendChild(linha);
        indiceLinhaTaxa += 1;
    });
}

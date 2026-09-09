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
});

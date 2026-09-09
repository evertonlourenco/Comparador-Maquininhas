/**
 * Confere o contraste da paleta lendo `resources/css/app.css` — nao ha copia
 * dos hexadecimais aqui, entao a checagem nao envelhece quando o tema muda.
 *
 * Dois limites, os da WCAG 2.2 nivel AA:
 *   4.5:1  texto (1.4.3)
 *   3:1    limite de controle e anel de foco (1.4.11)
 *
 * O fio de tabela e a moldura de cartao ficam de fora do que e exigido, e isso
 * e decisao, nao esquecimento: a linha nao carrega informacao — a zebra, o
 * cabecalho e o espaco ja separam as celulas —, entao ela e decorativa e a
 * 1.4.11 nao a alcanca. Os valores dela saem no relatorio assim mesmo, para
 * ninguem confundir "decorativo" com "nao medido".
 *
 * Uso: node scripts/verifica-contraste.mjs
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const css = readFileSync(resolve(raiz, 'resources/css/app.css'), 'utf8');

/** Le os `--cor-*` do bloco cujo seletor casa com o padrao dado. */
function tokensDoBloco(padrao) {
    const inicio = css.search(padrao);
    if (inicio === -1) {
        throw new Error(`Bloco nao encontrado em app.css: ${padrao}`);
    }

    const abre = css.indexOf('{', inicio);
    let profundidade = 0;
    let fim = abre;
    for (; fim < css.length; fim++) {
        if (css[fim] === '{') profundidade++;
        if (css[fim] === '}' && --profundidade === 0) break;
    }

    const corpo = css.slice(abre + 1, fim);
    const tokens = {};
    for (const [, nome, valor] of corpo.matchAll(/--cor-([a-z-]+):\s*(#[0-9a-fA-F]{6})\s*;/g)) {
        tokens[nome] = valor.toLowerCase();
    }

    return tokens;
}

const claro = tokensDoBloco(/^:root \{/m);
const escuro = tokensDoBloco(/^\[data-tema='escuro'\] \{/m);
const escuroDoSistema = tokensDoBloco(/^\s+:root:not\(\[data-tema='claro'\]\) \{/m);

const hex = (h) => {
    const n = parseInt(h.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
};
const linear = (c) => (c / 255 <= 0.04045 ? c / 255 / 12.92 : ((c / 255 + 0.055) / 1.055) ** 2.4);
const luminancia = (h) => {
    const [r, g, b] = hex(h).map(linear);
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const razao = (a, b) => {
    const [maior, menor] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);
    return (maior + 0.05) / (menor + 0.05);
};

/** [rotulo, frente, fundo, minimo] */
const exigidos = (p) => [
    ['texto no papel', p.tinta, p.papel, 4.5],
    ['texto na superficie', p.tinta, p.superficie, 4.5],
    ['texto na superficie forte', p.tinta, p['superficie-forte'], 4.5],
    ['metadado no papel', p['tinta-suave'], p.papel, 4.5],
    ['metadado na superficie', p['tinta-suave'], p.superficie, 4.5],
    ['metadado na superficie forte', p['tinta-suave'], p['superficie-forte'], 4.5],
    ['link no papel', p.link, p.papel, 4.5],
    ['link na superficie', p.link, p.superficie, 4.5],
    ['aferido no papel', p.aferido, p.papel, 4.5],
    ['aferido na superficie', p.aferido, p.superficie, 4.5],
    ['aferido no fundo aferido', p.aferido, p['aferido-fundo'], 4.5],
    ['reportado no papel', p.reportado, p.papel, 4.5],
    ['reportado na superficie', p.reportado, p.superficie, 4.5],
    ['reportado no fundo reportado', p.reportado, p['reportado-fundo'], 4.5],
    ['vencido no papel', p.vencido, p.papel, 4.5],
    ['vencido na superficie', p.vencido, p.superficie, 4.5],
    ['vencido no fundo vencido', p.vencido, p['vencido-fundo'], 4.5],
    ['papel sobre botao aferido', p.papel, p.aferido, 4.5],
    ['papel sobre botao tinta', p.papel, p.tinta, 4.5],
    ['papel sobre botao vencido', p.papel, p.vencido, 4.5],
    ['papel sobre etiqueta solida reportado', p.papel, p.reportado, 4.5],
    ['papel sobre etiqueta solida apagada', p.papel, p['tinta-suave'], 4.5],
    ['contorno de campo no papel', p.contorno, p.papel, 3],
    ['contorno de campo na superficie', p.contorno, p.superficie, 3],
    ['contorno de campo na superficie forte', p.contorno, p['superficie-forte'], 3],
    ['borda de etiqueta aferido', p.aferido, p['aferido-fundo'], 3],
    ['borda de etiqueta reportado', p.reportado, p['reportado-fundo'], 3],
    ['borda de etiqueta vencido', p.vencido, p['vencido-fundo'], 3],
    ['anel de foco no papel', p.foco, p.papel, 3],
    ['anel de foco na superficie', p.foco, p.superficie, 3],
    ['anel de foco na superficie forte', p.foco, p['superficie-forte'], 3],
];

const decorativos = (p) => [
    ['fio de tabela no papel', p.regua, p.papel],
    ['fio de tabela na superficie', p.regua, p.superficie],
    ['fio de secao no papel', p['regua-forte'], p.papel],
];

let falhas = 0;

// Os dois blocos escuros precisam ser identicos: um serve o atributo, o outro
// serve quem esta sem JavaScript, e um so pode nao andar sem o outro.
const divergentes = Object.keys(escuro).filter((k) => escuro[k] !== escuroDoSistema[k]);
if (divergentes.length || Object.keys(escuro).length !== Object.keys(escuroDoSistema).length) {
    falhas++;
    console.log(
        `FALHA  o bloco [data-tema='escuro'] e o de prefers-color-scheme divergem em: ${
            divergentes.join(', ') || '(numero de tokens)'
        }`
    );
}

for (const [nome, paleta] of [
    ['CLARO', claro],
    ['ESCURO', escuro],
]) {
    console.log(`\n── tema ${nome} ──`);

    for (const [rotulo, frente, fundo, minimo] of exigidos(paleta)) {
        if (!frente || !fundo) {
            falhas++;
            console.log(`FALHA  token ausente em app.css para "${rotulo}"`);
            continue;
        }

        const r = razao(frente, fundo);
        const passou = r >= minimo;
        if (!passou) falhas++;
        console.log(
            `${passou ? 'ok   ' : 'FALHA'} ${r.toFixed(2).padStart(5)}:1  (min ${minimo})  ${rotulo}  ${frente}/${fundo}`
        );
    }

    for (const [rotulo, frente, fundo] of decorativos(paleta)) {
        console.log(`  --  ${razao(frente, fundo).toFixed(2).padStart(5)}:1  (decorativo)  ${rotulo}  ${frente}/${fundo}`);
    }
}

console.log(falhas ? `\n${falhas} verificacao(oes) reprovada(s).` : '\nToda exigencia de contraste passa nos dois temas.');
process.exit(falhas ? 1 : 0);

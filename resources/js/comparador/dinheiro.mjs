// Espelho em JavaScript de app/Support/Dinheiro.php (regra 11).
//
// Toda mudanca aqui tem de ser feita la, e vice-versa. O teste
// ParidadeDoMotorTest roda os dois sobre os mesmos casos e falha quando eles
// divergem em um centavo - e essa e a unica coisa que segura a duplicacao de
// pe. Ver a nota sobre a decisao 2 no CLAUDE.md.

/**
 * Meio centavo para cima, sem deixar o erro binario decidir o desempate.
 *
 * Math.round(1.005 * 100) / 100 da 1, porque 1,005 nao existe em ponto
 * flutuante: o double mais proximo e 1,00499999999999989..., e vezes 100 da
 * 100.49999999999999. O PHP corrige isso dentro do round() e daria 1.01.
 * Como as duas implementacoes precisam concordar, nenhuma das duas usa o
 * arredondamento nativo: as duas reduzem o valor escalado a 15 digitos
 * significativos (a precisao que um double IEEE-754 sempre reconstroi sem
 * ambiguidade) e so entao desempatam.
 *
 * Nem todo meio centavo diverge - 2,675 x 100 da 267,5 exatos e os dois
 * idiomas ja concordam em 2,68. Os que divergem estao tabelados no teste de
 * paridade, para que ele nao passe por sorte.
 */
export function arredondar(valor, casas = 2) {
  if (!Number.isFinite(valor)) {
    return valor;
  }

  const fator = 10 ** casas;
  const escalado = comQuinzeDigitos(valor * fator);
  const inteiro = Math.floor(Math.abs(escalado) + 0.5);

  return (escalado < 0 ? -inteiro : inteiro) / fator;
}

function comQuinzeDigitos(valor) {
  return Number(valor.toPrecision(15));
}

/** Decimal vindo do JSON. Ausente continua ausente: dado que falta nao e zero. */
export function doBanco(valor) {
  return valor === null || valor === undefined || valor === '' ? null : Number(valor);
}

/**
 * O que a pessoa digitou, em pt-BR: "10.000,00", "R$ 1.234,56", "0,57".
 *
 * Havendo virgula, ela e o decimal e todo ponto e milhar. Sem virgula, o ponto
 * e milhar quando separa um grupo de exatamente 3 digitos ("10.500" = dez mil
 * e quinhentos) e decimal em qualquer outro caso ("10.5" = dez e meio).
 */
export function doUsuario(valor) {
  if (valor === null || valor === undefined || valor === '') {
    return null;
  }

  if (typeof valor === 'number') {
    return valor;
  }

  const limpo = String(valor).replace(/[^0-9,.\-]/g, '');

  if (limpo === '' || limpo === '-') {
    return null;
  }

  if (limpo.includes(',')) {
    return Number(limpo.replace(/\./g, '').replace(',', '.'));
  }

  const partes = limpo.split('.');

  if (partes.length > 1 && partes[partes.length - 1].length === 3) {
    return Number(partes.join(''));
  }

  return Number(limpo);
}

// Regra 11: Intl.NumberFormat('pt-BR') no JavaScript do comparador. O formato
// decimal (e nao o de moeda) e proposital: o de moeda insere um espaco fino
// nao separavel depois do "R$", e a regra pede espaco normal.
const formatadores = new Map();

function formatador(casas) {
  if (!formatadores.has(casas)) {
    formatadores.set(
      casas,
      new Intl.NumberFormat('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas }),
    );
  }

  return formatadores.get(casas);
}

/** "1.234.567,89" */
export function numero(valor, casas = 2) {
  return formatador(casas).format(arredondar(valor, casas));
}

/** "R$ 1.234,56", com espaco depois do R$. */
export function real(valor) {
  return `R$ ${numero(valor, 2)}`;
}

/** "2,49%" — sempre duas casas, nunca "2,5%" nem "2,4900%". */
export function percentual(valor) {
  return `${numero(valor, 2)}%`;
}

/** "08/09/2026" a partir de "2026-09-08". */
export function data(valor) {
  if (valor === null || valor === undefined || valor === '') {
    return null;
  }

  const [ano, mes, dia] = String(valor).slice(0, 10).split('-');

  return `${dia}/${mes}/${ano}`;
}

/** Dias inteiros entre duas datas Y-m-d, sem fuso no meio do caminho. */
export function diferencaEmDias(de, ate) {
  return Math.round((Date.parse(`${ate}T00:00:00Z`) - Date.parse(`${de}T00:00:00Z`)) / 86400000);
}

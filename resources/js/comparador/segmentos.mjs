// Os atalhos da segunda pergunta do comparador (etapa 07).
//
// POR QUE COMECAR POR BOTAO DE SEGMENTO
//
// O mix de vendas e a pergunta que decide o resultado - a mesma marca ganha ou
// perde conforme o lojista venda mais em debito ou mais em parcelado. Mas
// pedir quatro percentuais e um ticket medio de cara e pedir para a pessoa ir
// embora. Entao o segmento carrega um mix tipico, quem quiser ajusta nos
// controles finos, e a maioria nao vai querer.
//
// O QUE ESTES NUMEROS SAO, E O QUE ELES NAO SAO
//
// Eles nao sao dado verificado, e nunca podem ser exibidos como se fossem.
// Nao ha aqui `fonte` nem `data_verificacao`, e nao ha porque nao existe
// pesquisa publica de mix de meios de pagamento por segmento com a
// granularidade que a tela pede. O que existe e um ponto de partida plausivel,
// declarado como palpite editavel na propria tela - a regra 6 vale para taxa,
// que e afirmacao nossa sobre a marca; isto e chute do lojista sobre o proprio
// negocio, e ele tem todo direito de corrigir.
//
// A distincao importa porque as duas coisas se parecem na tela e sao opostas:
// a taxa errada e risco de CDC; o mix errado e o usuario descrevendo mal a
// propria loja, e o unico remedio possivel e deixar os controles a mao.
//
// A soma dos quatro percentuais e menor que 100 de proposito: o resto e
// dinheiro, que nao passa na maquininha e nao custa taxa nenhuma. Uma padaria
// que passasse 100% no cartao seria a padaria errada.

/**
 * @typedef {object} Segmento
 * @property {string} rotulo       Como aparece no botao.
 * @property {number} debito       % do faturamento em debito.
 * @property {number} creditoAvista % do faturamento em credito a vista.
 * @property {number} creditoParcelado % do faturamento em credito parcelado.
 * @property {number} pix          % do faturamento em Pix.
 * @property {number} parcelas     Numero de parcelas tipico (regra 2: inteiro).
 * @property {number} ticket       Ticket medio em reais, de onde sai a
 *                                 quantidade de transacoes do mes.
 */

/** @type {Record<string, Segmento>} */
export const SEGMENTOS = {
  padaria: {
    rotulo: 'Padaria',
    debito: 34,
    creditoAvista: 20,
    creditoParcelado: 2,
    pix: 19,
    parcelas: 2,
    ticket: 22,
  },
  salao: {
    rotulo: 'Salão de beleza',
    debito: 22,
    creditoAvista: 26,
    creditoParcelado: 14,
    pix: 28,
    parcelas: 3,
    ticket: 90,
  },
  roupas: {
    rotulo: 'Loja de roupas',
    debito: 15,
    creditoAvista: 20,
    creditoParcelado: 40,
    pix: 18,
    parcelas: 4,
    ticket: 180,
  },
  food_truck: {
    rotulo: 'Food truck',
    debito: 30,
    creditoAvista: 22,
    creditoParcelado: 3,
    pix: 33,
    parcelas: 2,
    ticket: 35,
  },
  feira: {
    rotulo: 'Feira',
    debito: 26,
    creditoAvista: 12,
    creditoParcelado: 2,
    pix: 40,
    parcelas: 2,
    ticket: 28,
  },
  delivery: {
    rotulo: 'Delivery',
    debito: 20,
    creditoAvista: 25,
    creditoParcelado: 5,
    pix: 45,
    parcelas: 2,
    ticket: 55,
  },
  oficina: {
    rotulo: 'Oficina',
    debito: 12,
    creditoAvista: 18,
    creditoParcelado: 45,
    pix: 22,
    parcelas: 6,
    ticket: 350,
  },
  outro: {
    rotulo: 'Outro',
    debito: 25,
    creditoAvista: 25,
    creditoParcelado: 15,
    pix: 25,
    parcelas: 3,
    ticket: 60,
  },
};

export const SEGMENTO_PADRAO = 'outro';

/** Lista pronta para o x-for dos botoes, na ordem em que foram declarados. */
export const LISTA_DE_SEGMENTOS = Object.entries(SEGMENTOS).map(([chave, dados]) => ({
  chave,
  ...dados,
}));

/**
 * O mix vira as linhas de venda do cenario (App\Motor\VendaDoCenario).
 *
 * Duas conversoes acontecem aqui, e as duas sao do lojista para o motor:
 *
 * 1. Percentual do faturamento -> reais no mes. O que sobrar dos quatro
 *    percentuais e dinheiro em especie, e nao vira linha nenhuma: dinheiro nao
 *    passa na maquininha.
 * 2. Ticket medio -> quantidade de transacoes. O motor precisa dela quando a
 *    taxa cobra valor fixo por venda, e sem ela ele nao estima: declara que
 *    falta. Com ticket zerado a quantidade sai nula de proposito, que e o
 *    estado honesto de "nao informei".
 *
 * O grupo de bandeiras entra pelo percentual de Visa e Mastercard: cada linha
 * de cartao vira duas quando o lojista declara vender fora dessas duas
 * bandeiras, porque o grupo e dimensao da chave da regra 1 e algumas marcas
 * cobram diferente nele - ou nem publicam, como a SumUp.
 */
export function vendasDoMix({ faturamento, mix, parcelas, ticket, visaMaster }) {
  const vendas = [];
  const fatia = (percentual) => (Number(faturamento) * Number(percentual)) / 100;
  const proporcaoVisaMaster = Math.min(100, Math.max(0, Number(visaMaster))) / 100;

  const linhasDeCartao = [
    ['debito', 1, mix.debito],
    ['credito_avista', 1, mix.credito_avista],
    ['credito_parcelado', Math.max(2, Math.round(Number(parcelas))), mix.credito_parcelado],
  ];

  for (const [tipo, parcelasDaLinha, percentual] of linhasDeCartao) {
    const valor = fatia(percentual);

    if (valor <= 0) {
      continue;
    }

    for (const [grupo, proporcao] of [
      ['visa_master', proporcaoVisaMaster],
      ['demais', 1 - proporcaoVisaMaster],
    ]) {
      const valorDoGrupo = valor * proporcao;

      if (valorDoGrupo <= 0) {
        continue;
      }

      vendas.push({
        tipo_operacao: tipo,
        grupo,
        parcelas: parcelasDaLinha,
        valor_mensal: valorDoGrupo,
        quantidade_mensal: quantidade(valorDoGrupo, ticket),
      });
    }
  }

  const valorEmPix = fatia(mix.pix);

  if (valorEmPix > 0) {
    // Pix nao tem bandeira: o grupo tecnico e resolvido pelo proprio motor.
    vendas.push({
      tipo_operacao: 'pix',
      parcelas: 1,
      valor_mensal: valorEmPix,
      quantidade_mensal: quantidade(valorEmPix, ticket),
    });
  }

  return vendas;
}

/** Quanto do faturamento nao passa na maquininha, em pontos percentuais. */
export function percentualEmDinheiro(mix) {
  const soma = mix.debito + mix.credito_avista + mix.credito_parcelado + mix.pix;

  return Math.round((100 - soma) * 100) / 100;
}

function quantidade(valor, ticket) {
  const medio = Number(ticket);

  if (!Number.isFinite(medio) || medio <= 0) {
    return null;
  }

  // Uma venda e o piso: se passou valor, passou pelo menos uma transacao.
  return Math.max(1, Math.round(valor / medio));
}

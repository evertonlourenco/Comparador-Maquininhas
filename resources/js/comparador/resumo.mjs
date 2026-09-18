// Espelho em JavaScript de app/Motor/ResumoDoComparador.php (etapa 07).
//
// Mesma regra do motor: isto e traducao linha a linha do PHP, e nao reescrita
// idiomatica. Nomes de campo, ordem de operacao e arredondamento intermediario
// sao iguais de proposito, porque
// tests/Feature/Comparador/ParidadeDoResumoTest.php compara os dois campo a
// campo - inclusive as strings ja formatadas em pt-BR - sobre os mesmos casos
// de borda da etapa 05.
//
// Os quatro numeros da tela, e de onde saem:
//
//   custo mensal recorrente = total mensal - adesao amortizada
//   taxa efetiva combinada  = custo mensal recorrente / volume vendido x 100
//   custo inicial           = a adesao, sem cupom e com cupom (regra 5)
//   quanto sobra no mes     = faturamento - total mensal
//
// O nome de cada chave muda com o estado, igual ao motor: promocional,
// parcial e faixa nunca usam o nome do numero exato e permanente.

import { arredondar, percentual, real } from './dinheiro.mjs';
import { ESTADOS } from './motor.mjs';

/** Espelha ResumoDoComparador::resumir(). */
export function resumir(resultado) {
  const cenario = resultado.cenario;
  const faturamento = arredondar(Number(cenario.faturamento_mensal));

  const volume = arredondar(
    cenario.vendas.reduce((soma, venda) => soma + Number(venda.valor_mensal), 0),
  );

  const itens = resultado.itens.map((item) => ({
    ...item,
    comparacao: comparacao(item, volume, faturamento),
  }));

  return {
    ...resultado,
    itens,
    resumo: resumo(itens, cenario, volume, faturamento),
  };
}

/**
 * Os quatro numeros de um item. Nulo quando o item nao tem numero nenhum
 * (regra 4: marca sem dado publicado aparece sem valor, e zero seria mentira).
 */
function comparacao(item, volume, faturamento) {
  const estado = item.estado;

  if (estado === 'sem_dado_publicado') {
    return null;
  }

  const base = {
    volume_vendido: volume,
    custo_inicial: custoInicial(item),
  };

  if (estado === 'faixa_reportada') {
    return { ...base, ...pontasDaFaixa(item, volume, faturamento) };
  }

  const custos = item.custos;
  const sufixo = sufixoDoEstado(estado, custos);
  const total = Number(custos[`total_mensal${sufixo}`]);
  const adesaoPorMes = arredondar(Number(item.adesao?.por_mes ?? 0));
  const recorrente = arredondar(total - adesaoPorMes);

  const reais = {
    [`custo_mensal_recorrente${sufixo}`]: recorrente,
    [`custo_mensal_total${sufixo}`]: total,
    [`adesao_amortizada${sufixo}`]: adesaoPorMes,
    [`sobra_no_mes${sufixo}`]: arredondar(faturamento - total),
  };

  const percentuais = {
    [`taxa_efetiva_combinada${sufixo}`]: percentualSobre(recorrente, volume),
    [`taxa_efetiva_das_vendas${sufixo}`]: percentualSobre(Number(custos.vendas), volume),
  };

  return {
    ...base,
    chave: sufixo,
    ...reais,
    ...percentuais,
    formatado: formatar(reais, percentuais),
  };
}

/**
 * Regra 4, classe B: as tres pontas da faixa, e nenhuma chave que possa ser
 * lida como valor unico. O sufixo acompanha o genero da palavra em portugues.
 */
function pontasDaFaixa(item, volume, faturamento) {
  const faixa = item.custos_faixa;
  const adesaoPorMes = arredondar(Number(item.adesao?.por_mes ?? 0));
  const reais = {};
  const percentuais = {};

  for (const [ponta, feminino] of [
    ['minimo', 'minima'],
    ['mediana', 'mediana'],
    ['maximo', 'maxima'],
  ]) {
    const total = Number(faixa[`total_mensal_${ponta}`]);
    const recorrente = arredondar(total - adesaoPorMes);

    reais[`custo_mensal_recorrente_${ponta}`] = recorrente;
    reais[`custo_mensal_total_${ponta}`] = total;
    reais[`sobra_no_mes_${feminino}`] = arredondar(faturamento - total);

    percentuais[`taxa_efetiva_combinada_${feminino}`] = percentualSobre(recorrente, volume);
    percentuais[`taxa_efetiva_das_vendas_${feminino}`] = percentualSobre(
      Number(faixa[`vendas_${ponta}`]),
      volume,
    );
  }

  reais.adesao_amortizada = adesaoPorMes;

  return {
    chave: '_faixa',
    ...reais,
    ...percentuais,
    formatado: formatar(reais, percentuais),
  };
}

/**
 * Regra 5: o cupom desconta a adesao, e por isso os dois precos andam juntos.
 * Sem aparelho resolvido nao ha custo inicial - nulo, nunca zero.
 */
function custoInicial(item) {
  const adesao = item.adesao ?? null;

  if (adesao === null || adesao.vigente === null) {
    return null;
  }

  const semCupom = Number(adesao.vigente);
  const comCupom = Number(adesao.valor_final);
  const economia = Number(adesao.desconto_do_cupom);

  return {
    sem_cupom: semCupom,
    com_cupom: comCupom,
    economia,
    tem_cupom: economia > 0,
    cupom: item.cupom?.codigo ?? null,
    parcelas_oferecidas: adesao.parcelas_oferecidas,
    parcela_da_marca: adesao.parcela_da_marca,
    formatado: {
      sem_cupom: real(semCupom),
      com_cupom: real(comCupom),
      economia: real(economia),
      parcela_da_marca: item.formatado?.adesao?.parcela_da_marca ?? null,
    },
  };
}

/**
 * O topo da tela: quem ganhou, quem perdeu e quanto separa os dois.
 *
 * Regra 4 outra vez - so o bloco CALCULADO entra aqui. Promocao, faixa
 * reportada e incompleto tem bloco proprio na pagina e nunca melhor nem pior,
 * porque nao correram a mesma corrida.
 */
function resumo(itens, cenario, volume, faturamento) {
  const quantidades = {};

  for (const estado of Object.keys(ESTADOS)) {
    quantidades[estado] = itens.filter((item) => item.estado === estado).length;
  }

  const ranqueaveis = itens.filter((item) => item.estado === 'calculado');

  // O motor ja entregou os itens ordenados pelo custo mensal recorrente (sem a
  // adesao, etapa 20), com desempate estavel. Reordenar aqui seria arriscar
  // discordar dele.
  const melhor = ranqueaveis.length === 0 ? null : extremo(ranqueaveis[0]);
  const pior = ranqueaveis.length < 2 ? null : extremo(ranqueaveis[ranqueaveis.length - 1]);

  const diferenca =
    melhor === null || pior === null
      ? null
      : arredondar(pior.custo_mensal_recorrente - melhor.custo_mensal_recorrente);

  const horizonte = Number(cenario.horizonte_meses);
  const fora = arredondar(faturamento - volume);

  return {
    faturamento_mensal: faturamento,
    volume_vendido: volume,
    // O que nao passa na maquininha (dinheiro, principalmente) nao custa taxa
    // nenhuma, e por isso precisa aparecer: sem ele a "taxa efetiva" pareceria
    // incidir sobre o faturamento inteiro.
    fora_da_maquininha: fora,
    horizonte_meses: horizonte,
    quantidades,
    melhor,
    pior,
    diferenca_mensal: diferenca,
    diferenca_no_horizonte: diferenca === null ? null : arredondar(diferenca * horizonte),
    formatado: {
      faturamento_mensal: real(faturamento),
      volume_vendido: real(volume),
      fora_da_maquininha: real(fora),
      diferenca_mensal: diferenca === null ? null : real(diferenca),
      diferenca_no_horizonte: diferenca === null ? null : real(arredondar(diferenca * horizonte)),
    },
  };
}

/** O cartao curto do melhor e do pior, para o topo da pagina. */
function extremo(item) {
  const c = item.comparacao;

  return {
    marca: item.marca.nome,
    marca_slug: item.marca.slug,
    plano: item.plano?.nome ?? null,
    plano_id: item.plano?.id ?? null,
    custo_mensal_total: c.custo_mensal_total,
    custo_mensal_recorrente: c.custo_mensal_recorrente,
    taxa_efetiva_combinada: c.taxa_efetiva_combinada,
    sobra_no_mes: c.sobra_no_mes,
    formatado: {
      custo_mensal_total: real(c.custo_mensal_total),
      custo_mensal_recorrente: real(c.custo_mensal_recorrente),
      taxa_efetiva_combinada:
        c.taxa_efetiva_combinada === null ? null : percentual(c.taxa_efetiva_combinada),
      sobra_no_mes: real(c.sobra_no_mes),
    },
  };
}

/**
 * Volume zero nao vira taxa efetiva zero: sem nada passando na maquininha a
 * divisao nao existe, e um "0,00%" ali seria a mentira mais confortavel da
 * tela.
 */
function percentualSobre(custo, volume) {
  return volume <= 0 ? null : arredondar((custo / volume) * 100);
}

/** Regra 11 na saida, como no motor. */
function formatar(reais, percentuais) {
  const saida = {};

  for (const [chave, valor] of Object.entries(reais)) {
    saida[chave] = real(valor);
  }

  for (const [chave, valor] of Object.entries(percentuais)) {
    saida[chave] = valor === null ? null : percentual(valor);
  }

  return saida;
}

/** O mesmo sufixo que o motor usou na chave do total. */
function sufixoDoEstado(estado, custos) {
  if (estado === 'promocional') {
    return 'total_mensal_promocional_parcial' in custos ? '_promocional_parcial' : '_promocional';
  }

  return estado === 'incompleto' ? '_parcial' : '';
}

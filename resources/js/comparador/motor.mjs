// Espelho em JavaScript de app/Motor/MotorDeCalculo.php.
//
// POR QUE EXISTEM DOIS MOTORES (etapa 05, decisao 2)
//
// A regra 9 manda o comparador rodar no navegador sobre um JSON estatico, sem
// consulta ao banco por visita. As entradas do lojista - faturamento, mix de
// vendas, parcelas, prazo, horizonte - formam um espaco continuo: nao ha como
// pre-calcular resultado para toda combinacao possivel e guardar no JSON sem
// congelar a granularidade das perguntas. Entao a aritmetica roda no
// navegador, e o JSON carrega os insumos (taxas, tarifas, precos), nao
// respostas prontas.
//
// So que o PHP tambem precisa da mesma conta: o painel mostra custo estimado,
// os testes cobram valores conhecidos e o gerador de JSON precisa saber o que
// esta exportando. Duplicar e o preco; a alternativa (pre-calcular tudo) sai
// mais cara e mente mais.
//
// O que segura a duplicacao de pe e o teste: tests/Feature/Motor/
// ParidadeDoMotorTest.php gera os casos com o motor em PHP, roda este arquivo
// sobre os mesmos casos em Node e falha se qualquer campo divergir. Mudou aqui,
// muda la - e o teste avisa quando alguem esquecer.
//
// Regra pratica: este arquivo e traducao linha a linha do PHP, e nao uma
// reescrita idiomatica. Nomes de campo, ordem de operacao e arredondamento
// intermediario sao iguais de proposito.

import { arredondar, data as formatarData, diferencaEmDias, doBanco, doUsuario, percentual, real } from './dinheiro.mjs';

export const HORIZONTE_PADRAO = 12;
const GRUPO_PIX = 'pix';
const GRUPO_PADRAO = 'visa_master';

/** Espelha App\Motor\EstadoDoResultado. */
export const ESTADOS = {
  calculado: { ordem: 0, ranqueavel: true },
  // Tabela de entrada, com prazo para acabar. Numero verdadeiro, mas nao
  // disputa posicao com preco permanente.
  promocional: { ordem: 1, ranqueavel: false },
  faixa_reportada: { ordem: 2, ranqueavel: false },
  incompleto: { ordem: 3, ranqueavel: false },
  sem_dado_publicado: { ordem: 4, ranqueavel: false },
};

const ROTULOS_DE_OPERACAO = {
  debito: 'Débito',
  credito_avista: 'Crédito à vista',
  credito_parcelado: 'Crédito parcelado',
  pix: 'Pix',
};

// ---------------------------------------------------------------------------
// Cenario
// ---------------------------------------------------------------------------

/** Espelha App\Motor\Cenario::deArray e VendaDoCenario::deArray. */
export function normalizarCenario(dados) {
  const vendas = (dados.vendas ?? []).map((venda) => {
    const tipo = venda.tipo_operacao;

    return {
      tipo_operacao: tipo,
      // Pix nao tem bandeira: o grupo dele e sempre o grupo tecnico.
      grupo: tipo === 'pix' ? GRUPO_PIX : (venda.grupo ?? GRUPO_PADRAO),
      parcelas: Number(venda.parcelas ?? (tipo === 'credito_parcelado' ? 2 : 1)),
      valor_mensal: doUsuario(venda.valor_mensal) ?? 0,
      quantidade_mensal:
        venda.quantidade_mensal === undefined || venda.quantidade_mensal === null
          ? null
          : Number(venda.quantidade_mensal),
    };
  });

  if (vendas.length === 0) {
    throw new Error('Um cenario sem nenhuma venda nao tem o que comparar.');
  }

  const horizonte = Number(dados.horizonte_meses ?? HORIZONTE_PADRAO);

  if (horizonte < 1) {
    throw new Error('O horizonte de amortizacao da adesao precisa ser de ao menos 1 mes.');
  }

  return {
    faturamento_mensal: doUsuario(dados.faturamento_mensal) ?? 0,
    vendas,
    prazo: dados.prazo ?? null,
    antecipacao_avulsa: Boolean(dados.antecipacao_avulsa ?? false),
    horizonte_meses: horizonte,
    saques_mensais: Number(dados.saques_mensais ?? 0),
    teds_mensais: Number(dados.teds_mensais ?? 0),
    pix_envios_mensais: Number(dados.pix_envios_mensais ?? 0),
    aplicar_cupom: Boolean(dados.aplicar_cupom ?? true),
    equipamento_id: dados.equipamento_id === undefined || dados.equipamento_id === null ? null : Number(dados.equipamento_id),
    hoje: dados.hoje ?? new Date().toISOString().slice(0, 10),
  };
}

/**
 * Os grupos entram por parametro porque este rotulo sai direto na tela do
 * lojista (etapa 07): "Débito (Visa e Mastercard)" e uma frase, "debito
 * (visa_master)" e um identificador de banco.
 */
function rotuloDaVenda(venda, grupos = null) {
  let base = ROTULOS_DE_OPERACAO[venda.tipo_operacao];

  if (venda.tipo_operacao === 'credito_parcelado') {
    base += ` em ${venda.parcelas}x`;
  }

  if (venda.tipo_operacao === 'pix') {
    return base;
  }

  return `${base} (${grupos?.[venda.grupo]?.nome ?? venda.grupo})`;
}

// ---------------------------------------------------------------------------
// Motor
// ---------------------------------------------------------------------------

export function calcular(catalogo, cenarioCru) {
  const cenario = normalizarCenario(cenarioCru);
  const itens = [];

  for (const marca of catalogo.marcas) {
    const planos = planosElegiveis(marca, cenario);

    // Regra 4: marca sem nenhum plano elegivel nao some do resultado.
    if (planos.length === 0) {
      itens.push(semDadoPublicado(marca, cenario));
      continue;
    }

    for (const plano of planos) {
      itens.push(avaliarPlano(catalogo, marca, plano, cenario));
    }
  }

  return {
    cenario,
    catalogo: {
      versao: catalogo.versao ?? null,
      gerado_em: catalogo.gerado_em ?? null,
      contem_rascunhos: catalogo.contem_rascunhos ?? false,
    },
    itens: ordenar(itens.map(formatar)),
  };
}

/** O primitivo da regra 6: quanto custa uma venda naquela taxa. */
export function custoDaVenda(taxa, valorMensal, quantidadeMensal) {
  const pct = Number(taxa.percentual);
  const valorFixo = Number(taxa.valor_fixo);
  const custoPercentual = arredondar((valorMensal * pct) / 100);

  if (valorFixo === 0) {
    return { percentual: pct, custo_percentual: custoPercentual, custo_fixo: 0, custo: custoPercentual, falta: null };
  }

  if (quantidadeMensal === null) {
    return {
      percentual: pct,
      custo_percentual: custoPercentual,
      custo_fixo: null,
      custo: null,
      falta: 'quantidade de transações no mês (a taxa cobra valor fixo por venda)',
    };
  }

  const custoFixo = arredondar(quantidadeMensal * valorFixo);

  return {
    percentual: pct,
    custo_percentual: custoPercentual,
    custo_fixo: custoFixo,
    custo: arredondar(custoPercentual + custoFixo),
    falta: null,
  };
}

/** Regra 3: so o enquadramento automatico filtra por faturamento. */
function planosElegiveis(marca, cenario) {
  return marca.planos.filter((plano) => {
    if (plano.tipo_enquadramento !== 'automatico') {
      return true;
    }

    const minimo = plano.faturamento_min;
    const maximo = plano.faturamento_max;

    return (
      (minimo === null || cenario.faturamento_mensal >= minimo) &&
      (maximo === null || cenario.faturamento_mensal <= maximo)
    );
  });
}

function avaliarPlano(catalogo, marca, plano, cenario) {
  // Regra 4: um plano e avaliado por uma classe de dado so.
  if (plano.taxas.length > 0) {
    return avaliarComTaxasDivulgadas(catalogo, marca, plano, cenario);
  }

  if (plano.faixas.length > 0) {
    return avaliarComFaixaReportada(catalogo, marca, plano, cenario);
  }

  return {
    ...esqueleto(marca, plano, cenario),
    estado: 'sem_dado_publicado',
    motivo: 'O plano não tem nenhuma taxa publicada nem faixa reportada.',
  };
}

function avaliarComTaxasDivulgadas(catalogo, marca, plano, cenario) {
  let faltando = [];
  let avisos = [];
  const prazosUsados = [];
  const datasDeVerificacao = [];

  // Etapa 20 (Everton, 18/09/2026): os cartoes de um plano caem num prazo so.
  // Pedido o prazo, e aquele; "tanto faz", o prazo unico mais barato do plano.
  // O Pix fica fora da escolha: cai sempre na hora.
  const prazoDosCartoes = cenario.prazo ?? prazoMaisBaratoDoPlano(catalogo, plano, cenario);
  const linhas = resolverLinhas(catalogo, plano, cenario, prazoDosCartoes);

  for (const linha of linhas) {
    if (linha.falta !== null) {
      faltando.push(linha.falta);
      continue;
    }

    if (linha.venda.tipo_operacao !== 'pix' && !prazosUsados.includes(linha.prazo)) {
      prazosUsados.push(linha.prazo);
    }

    datasDeVerificacao.push(linha.data_verificacao);
  }

  const conta = custoDaConta(plano);
  const aparelho = custoDoAparelho(marca, plano, cenario);
  const antecipacao = custoDaAntecipacaoAvulsa(catalogo, plano, cenario, linhas);

  faltando = [...faltando, ...conta.faltando, ...aparelho.faltando, ...antecipacao.faltando];
  avisos = [...avisos, ...conta.avisos, ...aparelho.avisos, ...antecipacao.avisos];

  // Taxa publicada pode vir condicionada - o Pix a 0% que so vale com a chave
  // ativada no aplicativo. A condicao anda colada no numero, em
  // vendas[].condicao (etapa 20: o "?" ao lado da taxa), nao nos avisos.

  const custoVendas = arredondar(linhas.reduce((soma, l) => soma + (l.custo ?? 0), 0));
  const total = arredondar(custoVendas + conta.custo + aparelho.custo + antecipacao.custo);
  const completo = faltando.length === 0;
  const promocional = plano.tipo_enquadramento === 'promocional';

  const estado = promocional ? 'promocional' : completo ? 'calculado' : 'incompleto';

  const chaveDoTotal = promocional
    ? completo
      ? 'total_mensal_promocional'
      : 'total_mensal_promocional_parcial'
    : completo
      ? 'total_mensal'
      : 'total_mensal_parcial';

  return {
    ...esqueleto(marca, plano, cenario),
    estado,
    motivo: promocional
      ? motivoDaPromocao(plano)
      : completo
        ? null
        : 'Falta dado para fechar este cenário.',
    promocao: promocional ? promocaoDoPlano(marca, plano, cenario) : null,
    prazos_usados: prazosUsados,
    equipamento: aparelho.equipamento,
    adesao: aparelho.adesao,
    cupom: aparelho.cupom,
    custos: {
      vendas: custoVendas,
      conta: conta.custo,
      aparelho: aparelho.custo,
      antecipacao_avulsa: antecipacao.custo,
      // O total so se chama total_mensal quando nao falta nada e o plano e
      // permanente.
      [chaveDoTotal]: total,
    },
    vendas: linhas,
    conta: conta.itens,
    frescor: frescor(datasDeVerificacao, catalogo, cenario),
    faltando: unicos(faltando),
    avisos: unicos(avisos),
  };
}

/** Regra 4, classe B: nunca existe a chave total_mensal aqui. */
function avaliarComFaixaReportada(catalogo, marca, plano, cenario) {
  const faltando = [];
  const linhas = [];
  const datasDeVerificacao = [];
  const soma = { minimo: 0, mediana: 0, maximo: 0 };

  for (const venda of cenario.vendas) {
    const faixa = faixaDaLinha(plano, venda, cenario);

    if (faixa === null) {
      const falta = `faixa reportada para ${rotuloDaVenda(venda, catalogo.grupos)}`;
      faltando.push(falta);
      linhas.push({ venda, falta });
      continue;
    }

    const custos = {};

    for (const ponta of ['minimo', 'mediana', 'maximo']) {
      custos[ponta] = arredondar((venda.valor_mensal * Number(faixa[ponta])) / 100);
      soma[ponta] += custos[ponta];
    }

    datasDeVerificacao.push(faixa.data_verificacao);
    linhas.push({
      venda,
      prazo: faixa.prazo,
      percentual_minimo: Number(faixa.minimo),
      percentual_mediana: Number(faixa.mediana),
      percentual_maximo: Number(faixa.maximo),
      n_relatos: faixa.n_relatos,
      custo_minimo: custos.minimo,
      custo_mediana: custos.mediana,
      custo_maximo: custos.maximo,
      falta: null,
    });
  }

  const conta = custoDaConta(plano);
  const aparelho = custoDoAparelho(marca, plano, cenario);
  const fixo = conta.custo + aparelho.custo;

  return {
    ...esqueleto(marca, plano, cenario),
    estado: 'faixa_reportada',
    motivo:
      'Esta marca não publica tabela. O custo vem de relatos de lojistas e é uma faixa, não um valor exato.',
    equipamento: aparelho.equipamento,
    adesao: aparelho.adesao,
    cupom: aparelho.cupom,
    custos_faixa: {
      vendas_minimo: arredondar(soma.minimo),
      vendas_mediana: arredondar(soma.mediana),
      vendas_maximo: arredondar(soma.maximo),
      conta: conta.custo,
      aparelho: aparelho.custo,
      total_mensal_minimo: arredondar(soma.minimo + fixo),
      total_mensal_mediana: arredondar(soma.mediana + fixo),
      total_mensal_maximo: arredondar(soma.maximo + fixo),
    },
    vendas: linhas,
    conta: conta.itens,
    frescor: frescor(datasDeVerificacao, catalogo, cenario),
    faltando: unicos([...faltando, ...conta.faltando, ...aparelho.faltando]),
    avisos: unicos([...conta.avisos, ...aparelho.avisos]),
  };
}

/** Regra 4: a marca aparece, com o motivo, e sem numero nenhum. */
function semDadoPublicado(marca, cenario) {
  const motivo =
    marca.planos.length === 0
      ? marca.publica_tabela
        ? 'A marca ainda não tem nenhum plano cadastrado.'
        : 'A marca não publica tabela de taxas e ainda não tem faixa reportada por lojistas.'
      : 'Nenhum plano desta marca atende ao faturamento informado.';

  return { ...esqueleto(marca, null, cenario), estado: 'sem_dado_publicado', motivo };
}

function esqueleto(marca, plano, cenario) {
  return {
    marca: {
      id: marca.id,
      nome: marca.nome,
      slug: marca.slug,
      site_url: marca.site_url,
      logo_url: marca.logo_url,
      publica_tabela: marca.publica_tabela,
      adquirente: marca.adquirente,
      reclame_aqui: marca.reclame_aqui,
    },
    plano:
      plano === null
        ? null
        : { id: plano.id, nome: plano.nome, slug: plano.slug, tipo_enquadramento: plano.tipo_enquadramento },
    enquadramento: plano === null ? null : enquadramento(plano),
    horizonte_meses: cenario.horizonte_meses,
    prazos_usados: [],
    promocao: null,
    equipamento: null,
    adesao: null,
    cupom: null,
    custos: null,
    custos_faixa: null,
    vendas: [],
    conta: {},
    frescor: { nivel: 'sem_data', data_verificacao: null, dias: null },
    faltando: [],
    avisos: [],
  };
}

function enquadramento(plano) {
  const avisos = {
    escolhido: 'Plano de adesão opcional: o lojista escolhe e assume o compromisso de volume.',
    negociado: 'Plano negociado caso a caso. O percentual publicado é referência, não garantia.',
    promocional:
      'Tabela de entrada: o lojista cai nela sozinho ao ativar a maquininha e sai dela sozinho quando o limite estoura.',
  };

  return { tipo: plano.tipo_enquadramento, aviso: avisos[plano.tipo_enquadramento] ?? null };
}

/** Os dois limites da promocao valem em disjuncao: o que vier antes. */
function motivoDaPromocao(plano) {
  const promocao = plano.promocao ?? null;
  const limites = [];

  if ((promocao?.dias ?? null) !== null) {
    limites.push(`${promocao.dias} dias`);
  }

  if ((promocao?.valor_processado ?? null) !== null) {
    limites.push(`${real(promocao.valor_processado)} processados`);
  }

  if (limites.length === 0) {
    return 'Tabela de entrada, por tempo limitado. A marca não publicou o prazo exato.';
  }

  return `Tabela de entrada: vale por ${limites.join(' ou até ')}, o que vier antes. Depois disso o preço muda.`;
}

/** O plano em que o lojista cai quando a promocao acaba. */
function promocaoDoPlano(marca, plano, cenario) {
  const promocao = plano.promocao ?? { dias: null, valor_processado: null, sucessor_id: null };
  let sucessor = null;

  for (const candidato of planosElegiveis(marca, cenario)) {
    const declarado = promocao.sucessor_id !== null && candidato.id === promocao.sucessor_id;
    const automatico = promocao.sucessor_id === null && candidato.tipo_enquadramento === 'automatico';

    if (declarado || automatico) {
      sucessor = { id: candidato.id, nome: candidato.nome, slug: candidato.slug };
      break;
    }
  }

  return { dias: promocao.dias, valor_processado: promocao.valor_processado, sucessor };
}

/** Etapa 20: cartoes no mesmo prazo; Pix livre (null), cai sempre na hora. */
function resolverLinhas(catalogo, plano, cenario, prazoDosCartoes) {
  return cenario.vendas.map((venda) =>
    resolverLinha(catalogo, plano, venda, cenario, venda.tipo_operacao === 'pix' ? null : prazoDosCartoes),
  );
}

/**
 * "Tanto faz o prazo": o prazo unico que fecha a conta com menos falta e,
 * entre esses, o mais barato (vendas + antecipacao avulsa). Empate pela ordem
 * da dimensao curada. Sem taxa de cartao no plano, null.
 */
function prazoMaisBaratoDoPlano(catalogo, plano, cenario) {
  const prazos = [];

  for (const taxa of plano.taxas) {
    if (taxa.tipo_operacao !== 'pix' && !prazos.includes(taxa.prazo)) {
      prazos.push(taxa.prazo);
    }
  }

  let melhor = null;

  for (const prazo of prazos) {
    const linhas = resolverLinhas(catalogo, plano, cenario, prazo);
    const faltas = linhas.filter((l) => l.falta !== null).length;
    const custo = arredondar(
      linhas.reduce((soma, l) => soma + (l.custo ?? 0), 0) +
        custoDaAntecipacaoAvulsa(catalogo, plano, cenario, linhas).custo,
    );
    const ordem = catalogo.prazos[prazo]?.ordem ?? 0;

    if (
      melhor === null ||
      faltas < melhor.faltas ||
      (faltas === melhor.faltas && custo < melhor.custo) ||
      (faltas === melhor.faltas && custo === melhor.custo && ordem < melhor.ordem)
    ) {
      melhor = { faltas, custo, ordem, prazo };
    }
  }

  return melhor?.prazo ?? null;
}

/** Resolve a quinta dimensao da chave (o prazo) para uma linha de venda. */
function resolverLinha(catalogo, plano, venda, cenario, prazo) {
  const candidatas = plano.taxas.filter(
    (taxa) =>
      taxa.tipo_operacao === venda.tipo_operacao &&
      taxa.grupo === venda.grupo &&
      taxa.parcelas === venda.parcelas &&
      (prazo === null || taxa.prazo === prazo),
  );

  if (candidatas.length === 0) {
    return {
      venda,
      prazo: null,
      custo: null,
      falta:
        `taxa de ${rotuloDaVenda(venda, catalogo.grupos)}` +
        (prazo === null ? '' : ` no prazo ${catalogo.prazos[prazo]?.nome ?? prazo}`),
    };
  }

  let melhor = null;

  for (const taxa of candidatas) {
    const custo = custoDaVenda(taxa, venda.valor_mensal, venda.quantidade_mensal);
    const comparavel =
      (custo.custo ?? 0) +
      antecipacaoDaLinha(catalogo, plano, venda, taxa, custo.custo ?? 0, cenario).custo;

    // Desempate estavel: a ordem do prazo na dimensao curada.
    const ordem = catalogo.prazos[taxa.prazo]?.ordem ?? 0;

    if (
      melhor === null ||
      comparavel < melhor.comparavel ||
      (comparavel === melhor.comparavel && ordem < melhor.ordem)
    ) {
      melhor = { taxa, custo, comparavel, ordem };
    }
  }

  const { taxa, custo } = melhor;

  return {
    venda,
    prazo: taxa.prazo,
    percentual: custo.percentual,
    valor_fixo: Number(taxa.valor_fixo),
    custo_percentual: custo.custo_percentual,
    custo_fixo: custo.custo_fixo,
    custo: custo.custo,
    condicao: taxa.condicao ?? null,
    data_verificacao: taxa.data_verificacao,
    falta: custo.falta === null ? null : `${rotuloDaVenda(venda, catalogo.grupos)}: falta ${custo.falta}`,
  };
}

function faixaDaLinha(plano, venda, cenario) {
  for (const faixa of plano.faixas) {
    if (
      faixa.tipo_operacao === venda.tipo_operacao &&
      faixa.grupo === venda.grupo &&
      faixa.parcelas === venda.parcelas &&
      // Etapa 20: o Pix cai sempre na hora; o prazo pedido e dos cartoes.
      (cenario.prazo === null || venda.tipo_operacao === 'pix' || faixa.prazo === cenario.prazo)
    ) {
      return faixa;
    }
  }

  return null;
}

/**
 * Custo da conta. Mensalidade e o unico custo de conta que o Maquina Certa
 * compara.
 *
 * Decisao do Everton em 17/09/2026: tarifa de saque, de TED e de Pix
 * (recebido ou enviado) sao custos da CONTA DIGITAL da adquirente - o
 * lojista nao e obrigado a usa-la, pode receber o que a maquininha processa
 * na conta do proprio banco. O Maquina Certa compara so o que e inescapavel
 * pra quem usa a maquininha: taxa de venda, custo de adesao/aluguel do
 * aparelho e mensalidade (quando existe). Espelha
 * App\Motor\MotorDeCalculo::custoDaConta() - os mesmos campos ficaram
 * vestigiais no catalogo (`plano.conta.tarifa_*`) e no cenario
 * (`saques_mensais`, `teds_mensais`, `pix_envios_mensais`), sem custo de
 * manter, mas nenhum dos dois motores le mais nenhum deles.
 */
function custoDaConta(plano) {
  const conta = plano.conta;
  const faltando = [];
  const itens = {};
  let total = 0;

  if (conta.mensalidade === null) {
    faltando.push('mensalidade do plano');
  } else {
    const mensalidade = arredondar(Number(conta.mensalidade));
    total += mensalidade;
    itens.mensalidade = mensalidade;
  }

  return { custo: arredondar(total), itens, faltando, avisos: [] };
}

/** Aluguel mensal + adesao amortizada no horizonte. Ver a nota longa no PHP. */
function custoDoAparelho(marca, plano, cenario) {
  const vazio = { custo: 0, equipamento: null, adesao: null, cupom: null, faltando: [], avisos: [] };

  if (plano.equipamentos.length === 0) {
    return { ...vazio, faltando: ['equipamento vinculado a este plano'] };
  }

  let candidatos = plano.equipamentos;

  if (cenario.equipamento_id !== null) {
    candidatos = candidatos.filter((e) => e.id === cenario.equipamento_id);

    if (candidatos.length === 0) {
      return { ...vazio, faltando: ['o equipamento escolhido não é vendido neste plano'] };
    }
  }

  const cupom = cenario.aplicar_cupom ? cupomVigente(marca, cenario) : null;
  let melhor = null;

  for (const equipamento of candidatos) {
    const orcamento = orcamentoDoAparelho(equipamento, cupom, cenario);

    if (orcamento === null) {
      continue;
    }

    if (
      melhor === null ||
      orcamento.custo < melhor.custo ||
      (orcamento.custo === melhor.custo && equipamento.id < melhor.equipamento.id)
    ) {
      melhor = orcamento;
    }
  }

  if (melhor === null) {
    return { ...vazio, faltando: ['preço de adesão ou aluguel do aparelho'] };
  }

  return melhor;
}

function orcamentoDoAparelho(equipamento, cupom, cenario) {
  const adesaoVigente = equipamento.preco_adesao_promocional ?? equipamento.preco_adesao;
  const aluguel = equipamento.aluguel_mensal;

  if (adesaoVigente === null && aluguel === null) {
    return null;
  }

  const avisos = [];

  if (adesaoVigente === null) {
    avisos.push(
      `O aparelho ${equipamento.nome} está cadastrado sem preço de adesão: a comparação considerou apenas o aluguel.`,
    );
  }

  if (aluguel === null) {
    avisos.push(
      `O aparelho ${equipamento.nome} está cadastrado sem aluguel mensal: a comparação considerou o aparelho como compra.`,
    );
  }

  const adesaoCheia = Number(adesaoVigente ?? 0);
  const aluguelMensal = arredondar(Number(aluguel ?? 0));

  // Regra 5: o cupom desconta a adesao. Nunca o percentual da taxa.
  // Etapa 17: cupom com valor nao informado (desconto real, mas sem numero -
  // PagBank e Mercado Pago) nao entra na conta: 0 mentiria "sem desconto".
  let desconto = 0;

  if (
    cupom !== null &&
    cupom.valor !== null &&
    (cupom.equipamento_id === null || cupom.equipamento_id === equipamento.id)
  ) {
    desconto =
      cupom.tipo_desconto === 'percentual'
        ? arredondar((adesaoCheia * Number(cupom.valor)) / 100)
        : arredondar(Number(cupom.valor));

    desconto = Math.min(desconto, adesaoCheia);
  }

  const adesaoFinal = arredondar(adesaoCheia - desconto);
  const amortizada = arredondar(adesaoFinal / cenario.horizonte_meses);

  return {
    custo: arredondar(aluguelMensal + amortizada),
    equipamento: {
      id: equipamento.id,
      nome: equipamento.nome,
      slug: equipamento.slug,
      aluguel_mensal: aluguelMensal,
    },
    adesao: {
      preco_cheio: doBanco(equipamento.preco_adesao),
      preco_promocional: doBanco(equipamento.preco_adesao_promocional),
      vigente: adesaoVigente === null ? null : adesaoCheia,
      desconto_do_cupom: desconto,
      valor_final: adesaoFinal,
      amortizada_em_meses: cenario.horizonte_meses,
      // Oferta da marca, nao criterio nosso. Nulo e "a marca nao declarou".
      parcelas_oferecidas: equipamento.parcelas_adesao ?? null,
      parcela_da_marca: equipamento.parcelas_adesao
        ? arredondar(adesaoFinal / equipamento.parcelas_adesao)
        : null,
      por_mes: amortizada,
    },
    cupom: desconto > 0 ? cupom : null,
    faltando: [],
    avisos,
  };
}

/** Regra 5: cupom vence sozinho, contra o "hoje" do cenario. */
function cupomVigente(marca, cenario) {
  for (const cupom of marca.cupons) {
    // Regra 5 (revista na etapa 17): valido_ate nulo e "sem data de fim",
    // vigente. Antes da etapa 20, null >= hoje dava falso e todo cupom sem
    // data de fim sumia do resultado.
    const comecou = cupom.valido_de === null || cupom.valido_de <= cenario.hoje;
    const naoAcabou = cupom.valido_ate === null || cupom.valido_ate >= cenario.hoje;

    if (comecou && naoAcabou) {
      return cupom;
    }
  }

  return null;
}

/** Etapa 05, decisao 3: antecipacao embutida no percentual nao e cobrada de novo. */
function custoDaAntecipacaoAvulsa(catalogo, plano, cenario, linhas) {
  if (!cenario.antecipacao_avulsa) {
    return { custo: 0, faltando: [], avisos: [], itens: [] };
  }

  let total = 0;
  const faltando = [];
  const avisos = [];
  const itens = [];

  cenario.vendas.forEach((venda, indice) => {
    const linha = linhas[indice] ?? null;

    if (linha === null || linha.falta !== null || linha.prazo === null) {
      return;
    }

    const prazo = catalogo.prazos[linha.prazo];

    if (prazo.antecipacao_embutida) {
      avisos.push(
        `Antecipação não foi cobrada em ${rotuloDaVenda(venda, catalogo.grupos)}: o percentual do prazo "${prazo.nome}" já embute o adiantamento.`,
      );
      return;
    }

    if (plano.conta.taxa_antecipacao_mensal === null) {
      faltando.push('taxa de antecipação avulsa do plano');
      return;
    }

    const custo = antecipacaoDaLinha(catalogo, plano, venda, { prazo: linha.prazo }, linha.custo ?? 0, cenario);

    total += custo.custo;
    itens.push({ venda: rotuloDaVenda(venda, catalogo.grupos), meses: custo.meses, custo: custo.custo });
  });

  return { custo: arredondar(total), faltando, avisos, itens };
}

/** Incide sobre o que sobra a receber, pelos meses que o recebivel espera. */
function antecipacaoDaLinha(catalogo, plano, venda, taxa, custoDaLinha, cenario) {
  const mensal = plano.conta.taxa_antecipacao_mensal;

  if (!cenario.antecipacao_avulsa || mensal === null) {
    return { custo: 0, meses: 0 };
  }

  const prazo = catalogo.prazos[taxa.prazo] ?? null;

  if (prazo === null || prazo.antecipacao_embutida) {
    return { custo: 0, meses: 0 };
  }

  const meses = prazo.dias !== null ? prazo.dias / 30 : (venda.parcelas + 1) / 2;
  const base = venda.valor_mensal - custoDaLinha;

  return { custo: arredondar(((base * Number(mensal)) / 100) * meses), meses };
}

/** Regra 8: recalculado contra o "hoje", nunca gravado no JSON. */
function frescor(datas, catalogo, cenario) {
  const validas = datas.filter((d) => d !== null && d !== undefined && d !== '');

  if (validas.length === 0) {
    return { nivel: 'sem_data', data_verificacao: null, dias: null };
  }

  const maisAntiga = validas.reduce((a, b) => (a < b ? a : b));
  const dias = diferencaEmDias(maisAntiga, cenario.hoje);

  return {
    nivel: dias <= catalogo.dias_ate_degradar ? 'fresca' : 'desatualizada',
    data_verificacao: maisAntiga,
    dias,
  };
}

/** Regra 11: a volta para pt-BR acontece na saida do motor, e nao na pagina. */
function formatar(item) {
  const custos = item.custos;
  const faixa = item.custos_faixa;
  const adesao = item.adesao;

  item.vendas = item.vendas.map((linha) => {
    if ('percentual' in linha) {
      linha.percentual_formatado = percentual(linha.percentual);
    }

    if ('percentual_mediana' in linha) {
      linha.percentual_mediana_formatado = percentual(linha.percentual_mediana);
      linha.percentual_minimo_formatado = percentual(linha.percentual_minimo);
      linha.percentual_maximo_formatado = percentual(linha.percentual_maximo);
    }

    linha.custo_formatado = linha.custo === undefined || linha.custo === null ? null : real(linha.custo);

    return linha;
  });

  item.formatado = {
    total_mensal: custos?.total_mensal === undefined ? null : real(custos.total_mensal),
    total_mensal_parcial: custos?.total_mensal_parcial === undefined ? null : real(custos.total_mensal_parcial),
    total_mensal_promocional:
      custos?.total_mensal_promocional === undefined ? null : real(custos.total_mensal_promocional),
    total_mensal_promocional_parcial:
      custos?.total_mensal_promocional_parcial === undefined
        ? null
        : real(custos.total_mensal_promocional_parcial),
    vendas: custos === null ? null : real(custos.vendas),
    conta: custos === null ? null : real(custos.conta),
    aparelho: custos === null ? null : real(custos.aparelho),
    antecipacao_avulsa: custos === null ? null : real(custos.antecipacao_avulsa),
    faixa:
      faixa === null
        ? null
        : {
            total_mensal_minimo: real(faixa.total_mensal_minimo),
            total_mensal_mediana: real(faixa.total_mensal_mediana),
            total_mensal_maximo: real(faixa.total_mensal_maximo),
          },
    adesao:
      adesao === null
        ? null
        : {
            valor_final: real(adesao.valor_final),
            por_mes: real(adesao.por_mes),
            desconto_do_cupom: real(adesao.desconto_do_cupom),
            // "12x de R$ 16,58" - o jeito como a marca vende a adesao.
            parcela_da_marca:
              adesao.parcela_da_marca === null
                ? null
                : `${adesao.parcelas_oferecidas}x de ${real(adesao.parcela_da_marca)}`,
          },
    frescor: { data_verificacao: formatarData(item.frescor.data_verificacao), dias: item.frescor.dias },
  };

  return item;
}

/** Regra 4 na ordenacao: so o bloco calculado e ranqueado por preco. */
function ordenar(itens) {
  return itens.sort((a, b) => {
    const ordemA = ESTADOS[a.estado].ordem;
    const ordemB = ESTADOS[b.estado].ordem;

    if (ordemA !== ordemB) {
      return ordemA - ordemB;
    }

    return (
      comparar(chaveDeOrdenacao(a), chaveDeOrdenacao(b)) ||
      strcmp(a.marca.nome, b.marca.nome) ||
      comparar(a.plano?.id ?? 0, b.plano?.id ?? 0)
    );
  });
}

/**
 * Etapa 20 (Everton, 18/09/2026): ranking pelo que sai todo mes, sem a adesao
 * amortizada - ela e custo de entrada e aparece junto do cupom.
 */
function chaveDeOrdenacao(item) {
  return arredondar(totalDoEstado(item) - Number(item.adesao?.por_mes ?? 0));
}

function totalDoEstado(item) {
  switch (item.estado) {
    case 'calculado':
      return Number(item.custos.total_mensal);
    case 'promocional':
      return Number(item.custos.total_mensal_promocional ?? item.custos.total_mensal_promocional_parcial);
    case 'incompleto':
      return Number(item.custos.total_mensal_parcial);
    case 'faixa_reportada':
      return Number(item.custos_faixa.total_mensal_mediana);
    default:
      return 0;
  }
}

function comparar(a, b) {
  return a < b ? -1 : a > b ? 1 : 0;
}

// strcmp do PHP compara bytes; o < do JavaScript compara unidades UTF-16. Para
// nomes acentuados os dois discordam, entao aqui se compara byte a byte.
const emBytes = new TextEncoder();

function strcmp(a, b) {
  const x = emBytes.encode(a);
  const y = emBytes.encode(b);

  for (let i = 0; i < Math.min(x.length, y.length); i++) {
    if (x[i] !== y[i]) {
      return x[i] < y[i] ? -1 : 1;
    }
  }

  return comparar(x.length, y.length);
}

/** array_unique + array_values do PHP: mantem a primeira ocorrencia. */
function unicos(lista) {
  return [...new Set(lista)];
}

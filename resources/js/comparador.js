/**
 * O comparador (etapa 07).
 *
 * Regra 9: isto roda inteiro no navegador, sobre o JSON estatico que
 * `php artisan comparador:gerar-json` grava em /dados/comparador.json. Nenhuma
 * consulta ao banco por visita - a carga vem em picos de video, e um pico de
 * video sobre um comparador que consulta banco a cada slider arrastado e um
 * banco no chao.
 *
 * A aritmetica nao mora aqui. Ela mora em comparador/motor.mjs (gemeo de
 * App\Motor\MotorDeCalculo) e comparador/resumo.mjs (gemeo de
 * App\Motor\ResumoDoComparador), e os dois testes de paridade cobram que os
 * gemeos concordem centavo a centavo com o PHP. Este arquivo so faz tres
 * coisas: traduzir o que a pessoa respondeu em um cenario, chamar os dois
 * motores e guardar o estado na URL.
 */

import Alpine from 'alpinejs';
import { doUsuario, numero as formatarNumero, percentual, real } from './comparador/dinheiro.mjs';
import { calcular } from './comparador/motor.mjs';
import { resumir } from './comparador/resumo.mjs';
import { daUrl, paraUrl, TODAS_AS_MARCAS } from './comparador/estado.mjs';
import { LISTA_DE_SEGMENTOS, SEGMENTOS, SEGMENTO_PADRAO, vendasDoMix } from './comparador/segmentos.mjs';

/** Faturamento de partida: o meio da faixa que o publico do canal declara. */
const FATURAMENTO_PADRAO = 10000;

/**
 * Visa e Mastercard em 100% por padrao, e nao um numero "mais realista".
 *
 * O grupo de bandeiras e dimensao da chave da regra 1, e algumas marcas nao
 * publicam taxa fora de Visa e Mastercard - a SumUp diz isso em toda tabela
 * dela. Chutar 15% em "demais bandeiras" jogaria essas marcas para o bloco
 * "falta dado" por causa de um numero que o lojista nao informou. Entao o
 * padrao e o cenario que fecha, e quem vende bastante em Elo ou Amex ajusta no
 * controle fino - com o aviso do lado explicando o que muda.
 */
const VISA_MASTER_PADRAO = 100;

/**
 * Marcas parceiras primeiro, na ordem pedida pelo Everto em 17/09/2026 - as
 * demais mantem a ordem que o catalogo ja trouxer. `indexOf` devolve -1 para
 * quem nao esta na lista, e -1 fica depois de qualquer indice real porque o
 * comparador substitui por um numero maior que o tamanho da lista.
 */
const ORDEM_PARCEIROS = ['ton', 'mercado-pago', 'facilitypay', 'sidepay', 'trincapay', 'yelly', 'pagbank'];

function estadoInicial() {
  const segmento = SEGMENTOS[SEGMENTO_PADRAO];

  return {
    faturamento: FATURAMENTO_PADRAO,
    segmento: SEGMENTO_PADRAO,
    mix: {
      debito: segmento.debito,
      credito_avista: segmento.creditoAvista,
      credito_parcelado: segmento.creditoParcelado,
      pix: segmento.pix,
    },
    parcelas: segmento.parcelas,
    ticket: segmento.ticket,
    visaMaster: VISA_MASTER_PADRAO,
    prazo: '',
    // Nenhuma marca marcada de fabrica (decisao do Everton, 17/09/2026): quem
    // nao quer escolher usa o botao "Escolha por mim", que e que leva a
    // TODAS_AS_MARCAS.
    marcas: [],
    aplicarCupom: true,
  };
}

/** O preset de um segmento no formato do estado. Desconhecido devolve null. */
function presetDoSegmento(chave) {
  const segmento = SEGMENTOS[chave];

  if (!segmento) {
    return null;
  }

  return {
    mix: {
      debito: segmento.debito,
      credito_avista: segmento.creditoAvista,
      credito_parcelado: segmento.creditoParcelado,
      pix: segmento.pix,
    },
    parcelas: segmento.parcelas,
    ticket: segmento.ticket,
  };
}

function comparador(caminhoDoJson) {
  return {
    ...estadoInicial(),

    segmentos: LISTA_DE_SEGMENTOS,
    catalogo: null,
    resultado: null,
    carregando: true,
    // Erro de carga do JSON, nao de cenario: sem catalogo nao ha comparador,
    // e a pagina precisa dizer isso em vez de ficar vazia.
    erroDeCarga: null,
    avancado: false,
    copiado: false,
    erroAoCopiar: false,
    // Independente do preset do segmento: uma vez aberto a mao, so fecha se a
    // pessoa clicar de novo no "Ajustar o mix..." (pedido do Everton,
    // 17/09/2026) - trocar de segmento nao pode fechar a caixa sozinho.
    detalhesMixAbertos: false,
    // Slug da marca cujo modal de promocao esta aberto, ou null.
    promocaoAberta: null,
    temporizador: null,
    // Etapa 12: debounce proprio para o evento de uso do GA4, separado do
    // debounce do calculo (120ms — pensado para o motor, nao para analytics).
    // Sem isso, arrastar um slider dispararia um evento por frame.
    temporizadorAnalytics: null,

    // Texto dos campos de dinheiro, em pt-BR (regra 11). O numero so existe
    // depois de doUsuario(); a tela nunca mostra "10000.00".
    faturamentoTexto: '',
    ticketTexto: '',

    init() {
      const inicial = daUrl(window.location.search, estadoInicial(), presetDoSegmento);

      Object.assign(this, inicial);
      this.faturamentoTexto = formatarNumero(this.faturamento, 2);
      this.ticketTexto = formatarNumero(this.ticket, 2);

      // O texto do campo e a fonte; o numero e derivado dele. Um $watch em vez
      // de um x-on:input no markup porque a ordem entre o x-model e o listener
      // do proprio elemento nao e garantida, e o campo leria o valor anterior.
      this.$watch('faturamentoTexto', () => this.lerFaturamento());
      this.$watch('ticketTexto', () => this.lerTicket());

      this.$watch('assinatura', () => {
        this.agendar();
        this.agendarAnalytics();
      });

      fetch(caminhoDoJson, { headers: { Accept: 'application/json' } })
        .then((resposta) => {
          if (!resposta.ok) {
            throw new Error(`O arquivo respondeu ${resposta.status}.`);
          }

          return resposta.json();
        })
        .then((catalogo) => {
          this.catalogo = catalogo;
          this.carregando = false;
          this.calcular();
        })
        .catch((erro) => {
          this.carregando = false;
          this.erroDeCarga = erro.message;
        });
    },

    // -----------------------------------------------------------------
    // Entrada
    // -----------------------------------------------------------------

    /** Tudo que muda um numero na tela, num valor so, para o $watch. */
    get assinatura() {
      return JSON.stringify([
        this.faturamento,
        this.segmento,
        this.mix,
        this.parcelas,
        this.ticket,
        this.visaMaster,
        this.prazo,
        this.marcas,
        this.aplicarCupom,
      ]);
    },

    lerFaturamento() {
      this.faturamento = Math.max(0, doUsuario(this.faturamentoTexto) ?? 0);
    },

    lerTicket() {
      this.ticket = Math.max(0, doUsuario(this.ticketTexto) ?? 0);
    },

    /** Regra 11 tambem na saida do campo: o que ficou escrito volta em pt-BR. */
    formatarCampos() {
      this.faturamentoTexto = formatarNumero(this.faturamento, 2);
      this.ticketTexto = formatarNumero(this.ticket, 2);
    },

    escolherSegmento(chave) {
      const preset = presetDoSegmento(chave);

      if (preset === null) {
        return;
      }

      this.segmento = chave;
      this.mix = { ...preset.mix };
      this.parcelas = preset.parcelas;
      this.ticket = preset.ticket;
      this.ticketTexto = formatarNumero(this.ticket, 2);

      window.gtag?.('event', 'segmento_selecionado', { segmento: chave });
    },

    /**
     * Ajustar um controle fino tira a tela do preset - e o rotulo tem de dizer.
     *
     * Os quatro juntos somam sempre 100 (decisao do Everton, 17/09/2026: a
     * pergunta 1 ja e so o que passa na maquininha, entao nao ha mais
     * "dinheiro que sobra" para segurar a soma abaixo de 100). Por isso mexer
     * numa faixa redistribui as outras tres na mesma proporcao entre si -
     * "as mesmas proporcoes, no espaco que sobrou" - em vez de so travar no
     * teto como antes. Quando as outras tres estao todas zeradas (arrastar a
     * unica faixa com valor direto para baixo), reparte o espaco em partes
     * iguais entre elas, que e o unico ponto de partida que nao inventa um
     * favorito.
     */
    ajustarMix(chave, valor) {
      const pedido = Math.min(100, Math.max(0, Number(valor) || 0));
      const outras = Object.keys(this.mix).filter((outra) => outra !== chave);
      const somaOutras = outras.reduce((soma, outra) => soma + this.mix[outra], 0);
      const disponivel = 100 - pedido;

      const mix = { ...this.mix, [chave]: pedido };
      let somaAjustada = 0;

      outras.forEach((outra, indice) => {
        if (indice === outras.length - 1) {
          // A ultima absorve o resto do arredondamento, para a soma nunca
          // escapar de 100 por causa de decimais perdidos nas anteriores.
          mix[outra] = disponivel - somaAjustada;

          return;
        }

        const fatia =
          somaOutras === 0
            ? Math.round(disponivel / outras.length)
            : Math.round((this.mix[outra] * disponivel) / somaOutras);

        mix[outra] = fatia;
        somaAjustada += fatia;
      });

      this.mix = mix;
    },

    get mixIgualAoSegmento() {
      const preset = presetDoSegmento(this.segmento);

      if (preset === null) {
        return false;
      }

      return (
        preset.mix.debito === this.mix.debito &&
        preset.mix.credito_avista === this.mix.credito_avista &&
        preset.mix.credito_parcelado === this.mix.credito_parcelado &&
        preset.mix.pix === this.mix.pix &&
        preset.parcelas === this.parcelas &&
        preset.ticket === this.ticket
      );
    },

    // -----------------------------------------------------------------
    // Marcas
    // -----------------------------------------------------------------

    /** Parceiras primeiro, na ordem da lista acima; as demais mantem a ordem do catalogo. */
    get todasAsMarcas() {
      if (this.catalogo === null) {
        return [];
      }

      const posicao = (marca) => {
        const indice = ORDEM_PARCEIROS.indexOf(marca.slug);

        return indice === -1 ? ORDEM_PARCEIROS.length : indice;
      };

      return [...this.catalogo.marcas].sort((a, b) => posicao(a) - posicao(b));
    },

    marcaEscolhida(slug) {
      return this.marcas === TODAS_AS_MARCAS || this.marcas.includes(slug);
    },

    alternarMarca(slug) {
      const todos = this.todasAsMarcas.map((marca) => marca.slug);
      const atual = this.marcas === TODAS_AS_MARCAS ? todos : this.marcas;
      const proximo = atual.includes(slug)
        ? atual.filter((item) => item !== slug)
        : [...atual, slug];

      // Voltar a ter todas as marcas volta a valer `*`, e nao a lista de hoje
      // congelada: assim o link continua certo quando entrar marca nova.
      this.marcas = proximo.length === todos.length ? TODAS_AS_MARCAS : proximo;
    },

    escolhaPorMim() {
      this.marcas = TODAS_AS_MARCAS;
      this.$nextTick(() => {
        const alvo = document.getElementById('resultado');
        alvo?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        alvo?.focus({ preventScroll: true });
      });
    },

    get quantasMarcas() {
      return this.marcas === TODAS_AS_MARCAS ? this.todasAsMarcas.length : this.marcas.length;
    },

    // -----------------------------------------------------------------
    // Calculo
    // -----------------------------------------------------------------

    /**
     * O cenario no formato de App\Motor\Cenario::deArray.
     *
     * Antecipacao, saques, TEDs, Pix enviados e o horizonte de diluicao da
     * adesao nao tem mais controle na tela (decisao do Everton, 17/09/2026:
     * nenhuma marca cadastrada cobra antecipacao extra hoje, e a adesao passou
     * a mostrar sempre a vista e em 12x, sem selecao de horizonte). Ficam de
     * fora daqui de proposito - Cenario::deArray ja assume o padrao certo
     * (zero, zero, zero, false e 12 meses) para cada um.
     */
    get cenario() {
      return {
        faturamento_mensal: this.faturamento,
        vendas: vendasDoMix({
          faturamento: this.faturamento,
          mix: this.mix,
          parcelas: this.parcelas,
          ticket: this.ticket,
          visaMaster: this.visaMaster,
        }),
        prazo: this.prazo === '' ? null : this.prazo,
        aplicar_cupom: this.aplicarCupom,
        hoje: new Date().toISOString().slice(0, 10),
      };
    },

    agendar() {
      window.clearTimeout(this.temporizador);
      this.temporizador = window.setTimeout(() => this.calcular(), 120);
      this.gravarUrl();
    },

    /**
     * Etapa 12: "uso do comparador" e "faixa de faturamento" para o GA4.
     * 1200ms de silencio depois da ultima mudanca — bem mais longo que o
     * debounce do calculo — porque aqui o que importa e "a pessoa chegou a
     * um resultado e parou", nao cada arrasto de slider.
     */
    agendarAnalytics() {
      window.clearTimeout(this.temporizadorAnalytics);
      this.temporizadorAnalytics = window.setTimeout(() => this.registrarUso(), 1200);
    },

    registrarUso() {
      if (this.resultado === null) {
        return;
      }

      window.gtag?.('event', 'uso_comparador', { segmento: this.segmento });
      window.gtag?.('event', 'faixa_faturamento', { faixa: this.faixaDeFaturamento });
    },

    /** Faixas fixas, para o relatorio contar ocorrencia por faixa, nao por valor exato. */
    get faixaDeFaturamento() {
      const f = this.faturamento;

      if (f < 2000) return 'ate_2_mil';
      if (f < 5000) return '2_a_5_mil';
      if (f < 10000) return '5_a_10_mil';
      if (f < 20000) return '10_a_20_mil';
      if (f < 50000) return '20_a_50_mil';

      return 'acima_de_50_mil';
    },

    /**
     * Etapa 20 (bloco B): a marca tem cupom com desconto real aplicado a
     * este item? A mesma checagem que ja decide "Sem cupom disponível hoje"
     * no custo inicial (regra 5) - reaproveitada aqui para decidir entre o
     * CTA "Contratar com desconto" (via /ir/, rastreado) e "Ir para o site
     * da marca" (direto, sem parceria a rastrear).
     */
    temCupomParaContratar(item) {
      return Boolean(item.comparacao?.custo_inicial?.tem_cupom && item.cupom);
    },

    textoContratar(item) {
      if (! this.temCupomParaContratar(item)) {
        return `Ir para o site da ${item.marca.nome}`;
      }

      const cupom = item.cupom;

      if (cupom.tipo_desconto === 'percentual' && cupom.valor !== null) {
        return `Contratar com ${percentual(Number(cupom.valor))} de desconto`;
      }

      return 'Contratar com desconto';
    },

    /** A versao curta do texto, para a barra fixa do celular — sem espaço para a frase inteira. */
    textoContratarCurto(item) {
      return this.temCupomParaContratar(item) ? 'Contratar' : `Ir para ${item.marca.nome}`;
    },

    hrefContratar(item) {
      if (! this.temCupomParaContratar(item)) {
        return item.marca.site_url;
      }

      return `/ir/${item.marca.slug}?origem=comparador&cupom=${encodeURIComponent(item.cupom.codigo)}`;
    },

    /**
     * O evento de GA4 do clique de saida (a rota /ir/ ja grava o clique de
     * verdade em eventos_cupom, do lado do servidor - isto e so o espelho no
     * GA4, no mesmo nome que o resto do site ja usa para saida de afiliado).
     * Sem cupom nao ha o que rastrear: e navegacao direta para o site oficial.
     */
    registrarCliqueContratar(item) {
      if (! this.temCupomParaContratar(item)) {
        return;
      }

      window.gtag?.('event', 'clique_afiliado', {
        marca: item.marca.slug,
        cupom: item.cupom.codigo,
        pagina_origem: 'comparador',
      });
    },

    calcular() {
      if (this.catalogo === null) {
        return;
      }

      const cenario = this.cenario;

      // Sem nenhuma linha de venda nao ha o que comparar, e o motor recusa a
      // entrada de proposito. Aqui isso e estado de tela, nao excecao.
      if (cenario.vendas.length === 0) {
        this.resultado = null;

        return;
      }

      const escolhidas = this.marcas;
      const catalogo =
        escolhidas === TODAS_AS_MARCAS
          ? this.catalogo
          : { ...this.catalogo, marcas: this.catalogo.marcas.filter((m) => escolhidas.includes(m.slug)) };

      this.resultado = resumir(calcular(catalogo, cenario));
    },

    gravarUrl() {
      // O padrao do mix, das parcelas e do ticket e o preset do segmento
      // escolhido — assim `?s=oficina` sozinho ja significa a oficina inteira.
      // O segmento em si continua comparado com o de fabrica, senao ele nunca
      // diferiria de si mesmo e jamais entraria na URL.
      const preset = presetDoSegmento(this.segmento) ?? {};
      const padrao = { ...estadoInicial(), ...preset };

      window.history.replaceState(null, '', paraUrl(this, padrao));
      this.copiado = false;
      this.erroAoCopiar = false;
    },

    /**
     * A Clipboard API so existe em contexto seguro (HTTPS, ou `localhost`
     * exatamente - um `.test` do Herd em HTTP nao conta). Sem ela o botao
     * ficava clicavel e nao fazia nada visivel, o que parece defeito. O
     * fallback com `<textarea>` selecionado + `execCommand('copy')`
     * (descontinuado, mas ainda funciona nos navegadores atuais) cobre esse
     * caso; se os dois falharem, a tela avisa em vez de ficar muda.
     */
    async copiarLink() {
      const link = window.location.href;

      if (navigator.clipboard?.writeText) {
        try {
          await navigator.clipboard.writeText(link);
          this.copiado = true;
          this.erroAoCopiar = false;

          return;
        } catch (e) {
          // Cai no fallback abaixo.
        }
      }

      const copiou = this.copiarComFallback(link);
      this.copiado = copiou;
      this.erroAoCopiar = !copiou;
    },

    copiarComFallback(texto) {
      try {
        const campo = document.createElement('textarea');
        campo.value = texto;
        campo.style.position = 'fixed';
        campo.style.opacity = '0';
        document.body.appendChild(campo);
        campo.focus();
        campo.select();

        const copiou = document.execCommand('copy');
        document.body.removeChild(campo);

        return copiou;
      } catch (e) {
        return false;
      }
    },

    // -----------------------------------------------------------------
    // Leitura do resultado
    // -----------------------------------------------------------------

    itensNoEstado(estado) {
      return this.resultado === null
        ? []
        : this.resultado.itens.filter((item) => item.estado === estado);
    },

    /**
     * Nunca favorecer o plano promocional (decisao do Everton, 17/09/2026): o
     * numero que disputa posicao e sempre o do plano permanente, e a promocao
     * vira selo + modal em cima do cartao dele — nunca um cartao proprio, que
     * ganharia a comparacao com um preco que dura 30 dias. `find` porque cada
     * marca tem no maximo um plano promocional elegivel por vez.
     */
    promocaoDaMarca(slug) {
      return this.itensNoEstado('promocional').find((item) => item.marca.slug === slug) ?? null;
    },

    /**
     * A excecao: quando a marca NAO tem nenhum plano permanente para o
     * cenario (so o promocional foi avaliado), ela nao pode sumir do
     * resultado (regra 4) nem virar um selo sem cartao nenhum por baixo — aí a
     * promocao aparece como cartao proprio mesmo, no bloco de baixo.
     */
    get promocionaisOrfas() {
      return this.itensNoEstado('promocional').filter(
        (promo) => ! this.resultado.itens.some((outro) => outro.marca.slug === promo.marca.slug && outro.estado !== 'promocional'),
      );
    },

    abrirModalPromocao(slug) {
      this.promocaoAberta = slug;
    },

    fecharModalPromocao() {
      this.promocaoAberta = null;
    },

    get itemDaPromocaoAberta() {
      return this.promocaoAberta === null ? null : this.promocaoDaMarca(this.promocaoAberta);
    },

    /** O plano permanente desta marca no resultado de hoje, se houver. */
    itemPermanenteDaMarca(slug) {
      return this.resultado?.itens.find((item) => item.marca.slug === slug && item.estado !== 'promocional') ?? null;
    },

    /**
     * Etapa 20 (bloco B): a tabela promocional x regular do modal — cada
     * linha da tabela de entrada pareada com a mesma linha (forma de
     * pagamento, grupo de bandeiras, parcelas) do plano permanente da marca.
     * Sem plano permanente no cenario (a excecao das promocionais orfas), a
     * coluna regular fica nula — nunca um numero inventado.
     */
    linhasComparadasDaPromocao(promo) {
      if (! promo) {
        return [];
      }

      const permanente = this.itemPermanenteDaMarca(promo.marca.slug);

      return promo.vendas
        .filter((linha) => ! linha.falta)
        .map((linha) => {
          const par = permanente?.vendas.find(
            (outra) =>
              ! outra.falta &&
              outra.venda.tipo_operacao === linha.venda.tipo_operacao &&
              outra.venda.parcelas === linha.venda.parcelas &&
              outra.venda.grupo === linha.venda.grupo,
          );

          return {
            rotulo: this.rotuloDaVenda(linha.venda),
            promocional: linha.percentual_formatado ?? 'não publicada',
            regular: permanente ? (par?.percentual_formatado ?? 'não publicada') : null,
          };
        });
    },

    get temAlgumResultado() {
      return this.resultado !== null && this.resultado.itens.length > 0;
    },

    /** O item e o melhor (ou o pior) do bloco ranqueavel? */
    ehMelhor(item) {
      const melhor = this.resultado?.resumo?.melhor;

      return melhor != null && melhor.plano_id === item.plano?.id && melhor.marca === item.marca.nome;
    },

    ehPior(item) {
      const pior = this.resultado?.resumo?.pior;

      return pior != null && pior.plano_id === item.plano?.id && pior.marca === item.marca.nome;
    },

    /**
     * O numero de um item pelo nome-base da chave, resolvendo o sufixo que o
     * estado impos (`_promocional`, `_parcial`, `_promocional_parcial`).
     *
     * Faixa reportada devolve nulo aqui de proposito: la o sufixo e `_faixa`,
     * que nao existe como chave. E a mesma defesa do schema, agora na tela -
     * quem quiser exibir uma faixa tem de pedir as tres pontas pelo nome, e
     * nao ha caminho por onde uma mediana de relatos escorregue para o lugar
     * de um numero publicado.
     */
    campo(item, base) {
      const c = item.comparacao;

      return c == null ? null : (c[base + c.chave] ?? null);
    },

    /**
     * O numero deste item fecha, ou falta peca?
     *
     * Um total parcial que aparece na tela com o rotulo de um total fechado e
     * exatamente o que o motor evita ao trocar o nome da chave. A tela tem de
     * fazer o mesmo em portugues, senao a defesa para no JSON.
     */
    ehParcial(item) {
      return item.comparacao != null && item.comparacao.chave.includes('_parcial');
    },

    /** O mesmo, ja formatado em pt-BR pelo motor (regra 11). */
    campoTexto(item, base) {
      const c = item.comparacao;

      return c == null ? null : (c.formatado[base + c.chave] ?? null);
    },

    /**
     * Regra 4: quantos lojistas sustentam a faixa. Sai como intervalo quando as
     * linhas usadas tem contagens diferentes - uma media de relatos seria mais
     * um numero inventado.
     */
    relatosDe(item) {
      const contagens = item.vendas
        .filter((linha) => linha.n_relatos != null)
        .map((linha) => linha.n_relatos);

      if (contagens.length === 0) {
        return null;
      }

      const menor = Math.min(...contagens);
      const maior = Math.max(...contagens);

      return menor === maior
        ? `${formatarNumero(menor, 0)} relatos`
        : `de ${formatarNumero(menor, 0)} a ${formatarNumero(maior, 0)} relatos`;
    },

    /** O prazo pelo nome de exibicao da dimensao, e nao pelo codigo. */
    nomeDoPrazo(codigo) {
      return this.catalogo?.prazos?.[codigo]?.nome ?? codigo;
    },

    nomeDoGrupo(codigo) {
      return this.catalogo?.grupos?.[codigo]?.nome ?? codigo;
    },

    /**
     * So os prazos que alguma marca de fato oferece hoje (pedido do Everton,
     * 17/09/2026) — "Em 30 dias" e "Conforme as parcelas" existem como
     * dimensao da chave (regra 1), mas nenhum plano publicado usa nenhum dos
     * dois ainda, e uma opcao no seletor que nunca muda nada e so confunde.
     */
    get listaDePrazos() {
      const prazos = this.catalogo?.prazos ?? {};
      const usados = new Set();

      for (const marca of this.catalogo?.marcas ?? []) {
        for (const plano of marca.planos ?? []) {
          for (const taxa of plano.taxas ?? []) {
            if (taxa.prazo) {
              usados.add(taxa.prazo);
            }
          }
        }
      }

      return Object.values(prazos)
        .filter((prazo) => usados.has(prazo.codigo))
        .sort((a, b) => a.ordem - b.ordem);
    },

    /** A linha de venda pelo nome que o lojista reconhece. */
    rotuloDaVenda(venda) {
      const nomes = {
        debito: 'Débito',
        credito_avista: 'Crédito à vista',
        credito_parcelado: `Crédito em ${venda.parcelas}x`,
        pix: 'Pix',
      };

      const base = nomes[venda.tipo_operacao] ?? venda.tipo_operacao;

      return venda.tipo_operacao === 'pix' ? base : `${base} — ${this.nomeDoGrupo(venda.grupo)}`;
    },

    /**
     * Etapa 20 (bloco B): o rotulo do chip de forma de pagamento no cartao —
     * curto de proposito ("Débito", "3x", "Pix"), sem o grupo de bandeiras
     * que rotuloDaVenda() carrega para a tabela detalhada. O grupo continua
     * disponivel em "Ver simulação" para quem quiser a conta aberta.
     */
    rotuloCurtoDaVenda(venda) {
      if (venda.tipo_operacao === 'credito_parcelado') {
        return `${venda.parcelas}x`;
      }

      return { debito: 'Débito', credito_avista: 'Crédito', pix: 'Pix' }[venda.tipo_operacao] ?? venda.tipo_operacao;
    },

    /** O item de menor custo do bloco calculado — o CTA fixo do celular usa este. */
    get melhorItem() {
      return this.itensNoEstado('calculado')[0] ?? null;
    },

    real,
    percentual,
  };
}

Alpine.data('comparador', comparador);
window.Alpine = Alpine;
Alpine.start();

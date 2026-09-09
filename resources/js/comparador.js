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
import {
  LISTA_DE_SEGMENTOS,
  percentualEmDinheiro as calcularDinheiroEmEspecie,
  SEGMENTOS,
  SEGMENTO_PADRAO,
  vendasDoMix,
} from './comparador/segmentos.mjs';

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
    marcas: TODAS_AS_MARCAS,
    horizonte: 12,
    antecipacao: false,
    saques: 0,
    teds: 0,
    pixEnvios: 0,
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
    temporizador: null,

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

      this.$watch('assinatura', () => this.agendar());

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
        this.horizonte,
        this.antecipacao,
        this.saques,
        this.teds,
        this.pixEnvios,
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
    },

    /**
     * Ajustar um controle fino tira a tela do preset - e o rotulo tem de dizer.
     *
     * A faixa para no espaco que sobrou, em vez de deixar a soma passar de
     * 100%. As duas alternativas eram piores: somar mais de 100 apaga o
     * resultado inteiro num arrasto de slider, e reequilibrar as outras faixas
     * sozinho mexeria em numeros que a pessoa nao pediu para mexer. Assim,
     * para subir uma faixa e preciso baixar outra - que e a verdade do
     * problema.
     */
    ajustarMix(chave, valor) {
      const outras = Object.entries(this.mix)
        .filter(([outra]) => outra !== chave)
        .reduce((soma, [, percentual]) => soma + percentual, 0);

      const teto = Math.max(0, 100 - outras);
      const pedido = Math.max(0, Number(valor) || 0);

      this.mix = { ...this.mix, [chave]: Math.min(teto, pedido) };
    },

    get percentualEmDinheiro() {
      return calcularDinheiroEmEspecie(this.mix);
    },

    get mixExcedido() {
      return this.percentualEmDinheiro < 0;
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

    get todasAsMarcas() {
      return this.catalogo === null ? [] : this.catalogo.marcas;
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

    /** O cenario no formato de App\Motor\Cenario::deArray. */
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
        antecipacao_avulsa: this.antecipacao,
        horizonte_meses: this.horizonte,
        saques_mensais: this.saques,
        teds_mensais: this.teds,
        pix_envios_mensais: this.pixEnvios,
        aplicar_cupom: this.aplicarCupom,
        hoje: new Date().toISOString().slice(0, 10),
      };
    },

    agendar() {
      window.clearTimeout(this.temporizador);
      this.temporizador = window.setTimeout(() => this.calcular(), 120);
      this.gravarUrl();
    },

    calcular() {
      if (this.catalogo === null) {
        return;
      }

      const cenario = this.cenario;

      // Sem nenhuma linha de venda nao ha o que comparar, e o motor recusa a
      // entrada de proposito. Aqui isso e estado de tela, nao excecao.
      if (cenario.vendas.length === 0 || this.mixExcedido) {
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
    },

    async copiarLink() {
      try {
        await navigator.clipboard.writeText(window.location.href);
        this.copiado = true;
      } catch (e) {
        // Sem permissao de area de transferencia (ou sem HTTPS): o link esta
        // na barra de enderecos, entao da para copiar a mao.
        this.copiado = false;
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

    get listaDePrazos() {
      const prazos = this.catalogo?.prazos ?? {};

      return Object.values(prazos).sort((a, b) => a.ordem - b.ordem);
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

    real,
    percentual,
  };
}

Alpine.data('comparador', comparador);
window.Alpine = Alpine;
Alpine.start();

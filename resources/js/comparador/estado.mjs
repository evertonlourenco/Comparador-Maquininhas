// O estado do comparador na barra de enderecos (etapa 07).
//
// O pedido e simples de dizer e facil de errar: o lojista tem de poder mandar
// o resultado no WhatsApp. Isso quer dizer que toda entrada que muda um numero
// na tela precisa caber na URL, e que abrir a URL tem de reconstruir a tela
// inteira - inclusive as marcas escolhidas e os controles finos que ele
// ajustou.
//
// Tres decisoes que este arquivo carrega:
//
// - Chaves curtas, valores legiveis. `?f=10000&s=padaria&mix=34-20-2-19` cabe
//   numa mensagem sem quebrar de linha e ainda da para ler no olho. Base64 de
//   JSON caberia tambem, mas um link opaco nao da para conferir nem corrigir a
//   mao.
// - Numero na URL e cru, com ponto decimal - nao e texto de tela. A regra 11
//   vale para o que a pessoa digita e para o que ela le; a URL nao e nem um
//   nem outro, e "10.000,00" ali viraria percent-encoding e ambiguidade.
// - Faltou parametro, vale o padrao. Uma URL cortada pelo aplicativo de
//   mensagem abre num estado valido em vez de quebrar.
//
// A lista de marcas usa `*` para "todas", que e o que o botao "Escolha por
// mim" deixa - assim o link continua valendo quando uma marca nova entrar no
// catalogo, em vez de congelar as nove de hoje. O padrao de fabrica (decisao
// do Everton, 17/09/2026) e nenhuma marca marcada, e por isso precisa de um
// segundo sinal `0` para "nenhuma" - sem ele, `m=` vazio na URL nao teria como
// distinguir "nenhuma marca" de "parametro ausente, use o padrao", que hoje
// tambem e nenhuma, mas amanha pode nao ser.

export const TODAS_AS_MARCAS = '*';
const NENHUMA_MARCA = '0';
const MODOS = ['debito', 'credito_avista', 'credito_parcelado', 'avancado'];

/**
 * Le a barra de enderecos por cima dos padroes.
 *
 * A ordem importa: o segmento entra antes do resto, porque escolher um
 * segmento e o que carrega mix, parcelas e ticket. Assim `?s=oficina&px=10`
 * quer dizer "a oficina, mas eu parcelo em 10x" - o preset vem primeiro e o
 * ajuste fino escreve por cima.
 *
 * @param {(chave: string) => object|null} presetDoSegmento
 */
export function daUrl(busca, padrao, presetDoSegmento) {
  const p = new URLSearchParams(busca);
  const estado = { ...padrao, mix: { ...padrao.mix } };

  numero(p, 'f', (v) => (estado.faturamento = v));

  // Links antigos (segmento, mix, parcelas ou bandeiras na URL) so fazem
  // sentido na simulacao avancada; `pg` explicito vale por cima.
  if (['s', 'mix', 'px', 'vm'].some((chave) => p.has(chave))) {
    estado.modo = 'avancado';
  }

  if (p.has('pg') && MODOS.includes(p.get('pg'))) {
    estado.modo = p.get('pg');
  }

  texto(p, 's', (chave) => {
    const preset = presetDoSegmento(chave);

    if (preset === null) {
      return;
    }

    estado.segmento = chave;
    estado.mix = { ...preset.mix };
    estado.parcelas = preset.parcelas;
    estado.ticket = preset.ticket;
  });

  const mix = p.get('mix');

  if (mix) {
    const partes = mix.split('-').map((parte) => Number(parte));

    if (partes.length === 4 && partes.every((parte) => Number.isFinite(parte))) {
      const [debito, avista, parcelado, pix] = partes;
      estado.mix = {
        debito,
        credito_avista: avista,
        credito_parcelado: parcelado,
        pix,
      };
    }
  }

  numero(p, 'px', (v) => (estado.parcelas = Math.round(v)));
  numero(p, 't', (v) => (estado.ticket = v));
  numero(p, 'vm', (v) => (estado.visaMaster = v));

  // Prazo vazio na URL e "o mais barato que cada plano oferecer", que e
  // exatamente o `prazo: null` do cenario.
  if (p.has('pz')) {
    estado.prazo = p.get('pz') ?? '';
  }

  if (p.has('c')) {
    estado.aplicarCupom = p.get('c') === '1';
  }

  if (p.has('m')) {
    const marcas = p.get('m') ?? '';

    if (marcas === TODAS_AS_MARCAS) {
      estado.marcas = TODAS_AS_MARCAS;
    } else if (marcas === '' || marcas === NENHUMA_MARCA) {
      estado.marcas = [];
    } else {
      estado.marcas = marcas.split(',');
    }
  }

  return estado;
}

/**
 * O caminho + query que representa este estado. So entra o que difere do
 * padrao: um link de resultado com quinze parametros iguais aos de fabrica
 * assusta e nao diz nada a mais.
 *
 * `padrao` aqui ja e o padrao *deste segmento* - quem chama resolve o preset
 * antes. E o que faz `?s=oficina` sozinho continuar significando a oficina
 * inteira, e nao a oficina com o mix de fabrica colado atras.
 */
export function paraUrl(estado, padrao, caminho = window.location.pathname) {
  const p = new URLSearchParams();

  if (estado.faturamento !== padrao.faturamento) {
    p.set('f', formatarNumero(estado.faturamento));
  }

  if (estado.modo !== padrao.modo) {
    p.set('pg', estado.modo);
  }

  // Segmento, mix, parcelas e bandeiras so contam na simulacao avancada; fora
  // dela ficariam na URL e reabririam o link como avancado.
  if (estado.modo === 'avancado') {
    if (estado.segmento !== padrao.segmento) {
      p.set('s', estado.segmento);
    }

    if (!mixIgual(estado.mix, padrao.mix)) {
      p.set(
        'mix',
        [
          estado.mix.debito,
          estado.mix.credito_avista,
          estado.mix.credito_parcelado,
          estado.mix.pix,
        ]
          .map(formatarNumero)
          .join('-'),
      );
    }

    parDiferente(p, 'px', estado.parcelas, padrao.parcelas);
    parDiferente(p, 't', estado.ticket, padrao.ticket);
    parDiferente(p, 'vm', estado.visaMaster, padrao.visaMaster);

  }

  if (estado.prazo !== padrao.prazo) {
    p.set('pz', estado.prazo);
  }

  if (estado.aplicarCupom !== padrao.aplicarCupom) {
    p.set('c', estado.aplicarCupom ? '1' : '0');
  }

  if (!marcasIguais(estado.marcas, padrao.marcas)) {
    if (estado.marcas === TODAS_AS_MARCAS) {
      p.set('m', TODAS_AS_MARCAS);
    } else if (estado.marcas.length === 0) {
      p.set('m', NENHUMA_MARCA);
    } else {
      p.set('m', [...estado.marcas].sort().join(','));
    }
  }

  const busca = p.toString();

  return busca === '' ? caminho : `${caminho}?${busca}`;
}

function mixIgual(a, b) {
  return (
    a.debito === b.debito &&
    a.credito_avista === b.credito_avista &&
    a.credito_parcelado === b.credito_parcelado &&
    a.pix === b.pix
  );
}

function marcasIguais(a, b) {
  if (a === TODAS_AS_MARCAS || b === TODAS_AS_MARCAS) {
    return a === b;
  }

  const sa = [...a].sort();
  const sb = [...b].sort();

  return sa.length === sb.length && sa.every((valor, indice) => valor === sb[indice]);
}

function parDiferente(p, chave, valor, padrao) {
  if (valor !== padrao) {
    p.set(chave, formatarNumero(valor));
  }
}

/** Ponto decimal e sem separador de milhar: a URL nao e texto de tela. */
function formatarNumero(valor) {
  return String(Number(valor));
}

function numero(p, chave, aplicar) {
  if (!p.has(chave)) {
    return;
  }

  const valor = Number(p.get(chave));

  if (Number.isFinite(valor)) {
    aplicar(valor);
  }
}

function texto(p, chave, aplicar) {
  if (p.has(chave)) {
    aplicar(p.get(chave) ?? '');
  }
}

import { convert as htmlParaTexto } from 'html-to-text';

const USER_AGENT =
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

const TIMEOUT_MS = 30_000;

const SINAIS_DE_BLOQUEIO = [
  'attention required',
  'access denied',
  'checking your browser',
  'cloudflare ray id',
  'unusual traffic',
  'captcha',
  'request blocked',
];

class ErroDeColeta extends Error {}

function pareceBloqueado(texto, status) {
  if (status === 403 || status === 429 || status === 503) return true;
  const amostra = texto.slice(0, 4000).toLowerCase();
  return SINAIS_DE_BLOQUEIO.some((sinal) => amostra.includes(sinal));
}

async function coletarViaFetch(url) {
  const controle = new AbortController();
  const timeout = setTimeout(() => controle.abort(), TIMEOUT_MS);

  let resposta;
  try {
    resposta = await fetch(url, {
      headers: { 'User-Agent': USER_AGENT, Accept: 'text/html,*/*' },
      redirect: 'follow',
      signal: controle.signal,
    });
  } catch (erro) {
    throw new ErroDeColeta(`Falha de rede ao buscar ${url}: ${erro.message}`);
  } finally {
    clearTimeout(timeout);
  }

  if (!resposta.ok && resposta.status !== 304) {
    throw new ErroDeColeta(`HTTP ${resposta.status} ao buscar ${url}`);
  }

  const html = await resposta.text();

  if (pareceBloqueado(html, resposta.status)) {
    throw new ErroDeColeta(
      `Provável bloqueio de WAF/CDN ao buscar ${url} (HTTP ${resposta.status}, conteúdo com sinal de desafio).`,
    );
  }

  return htmlParaTexto(html, {
    wordwrap: false,
    selectors: [
      { selector: 'script', format: 'skip' },
      { selector: 'style', format: 'skip' },
      { selector: 'nav', format: 'skip' },
      { selector: 'footer', format: 'skip' },
      { selector: 'a', options: { ignoreHref: true } },
      { selector: 'img', format: 'skip' },
    ],
  });
}

async function coletarViaNavegador(url) {
  // Import tardio: so paga o custo de carregar o Playwright quando alguma
  // fonte realmente precisa de JavaScript renderizado.
  const { chromium } = await import('playwright');

  const navegador = await chromium.launch();
  try {
    const pagina = await navegador.newPage({ userAgent: USER_AGENT });
    pagina.setDefaultTimeout(TIMEOUT_MS);

    let resposta;
    try {
      resposta = await pagina.goto(url, { waitUntil: 'networkidle' });
    } catch (erro) {
      throw new ErroDeColeta(`Falha ao carregar ${url} no navegador: ${erro.message}`);
    }

    const status = resposta?.status() ?? 0;
    const texto = await pagina.evaluate(() => document.body?.innerText ?? '');

    if (pareceBloqueado(texto, status)) {
      throw new ErroDeColeta(
        `Provável bloqueio de WAF/CDN ao renderizar ${url} (HTTP ${status}, conteúdo com sinal de desafio).`,
      );
    }

    if (status >= 400) {
      throw new ErroDeColeta(`HTTP ${status} ao renderizar ${url}`);
    }

    return texto;
  } finally {
    await navegador.close();
  }
}

async function coletarPdf(url) {
  const controle = new AbortController();
  const timeout = setTimeout(() => controle.abort(), TIMEOUT_MS);

  let resposta;
  try {
    resposta = await fetch(url, {
      headers: { 'User-Agent': USER_AGENT, Accept: 'application/pdf' },
      redirect: 'follow',
      signal: controle.signal,
    });
  } catch (erro) {
    throw new ErroDeColeta(`Falha de rede ao buscar PDF ${url}: ${erro.message}`);
  } finally {
    clearTimeout(timeout);
  }

  if (!resposta.ok) {
    throw new ErroDeColeta(`HTTP ${resposta.status} ao buscar PDF ${url}`);
  }

  const buffer = Buffer.from(await resposta.arrayBuffer());

  // Import tardio pelo mesmo motivo do Playwright acima.
  const { default: pdfParse } = await import('pdf-parse');
  const { text } = await pdfParse(buffer);

  return text;
}

/**
 * Devolve o texto bruto (ainda nao normalizado) de uma fonte. Lanca
 * ErroDeColeta em qualquer falha — inclusive quando o conteudo parece um
 * desafio de Cloudflare/Akamai em vez da pagina de verdade, porque isso e
 * exatamente o tipo de falha que nao pode passar em silencio.
 */
export async function coletar(fonte) {
  if (fonte.tipo_conteudo === 'pdf') {
    return coletarPdf(fonte.url);
  }

  if (fonte.requer_navegador) {
    return coletarViaNavegador(fonte.url);
  }

  return coletarViaFetch(fonte.url);
}

export { ErroDeColeta };

/**
 * Chama a API gratuita do Gemini para resumir, em português, um trecho já
 * reduzido às linhas que mudaram (ver src/diff.mjs — nunca a página
 * inteira). O prompt pede objetividade e proíbe inventar número que não
 * esteja no trecho, no mesmo espírito da regra 6 do produto: campo vazio é
 * honesto, número errado é risco.
 */
const TENTATIVAS = 2;
const ESPERA_ENTRE_TENTATIVAS_MS = 4000;

/** HTTP 503/429 do Gemini costuma ser sobrecarga passageira do modelo gratuito — vale uma segunda tentativa antes de virar alerta de falha. */
function valeTentarDeNovo(status) {
  return status === 503 || status === 429;
}

export async function resumirMudanca({ apiKey, modelo, marcaNome, categoria, url, trecho }) {
  const endpoint = `https://generativelanguage.googleapis.com/v1beta/models/${modelo}:generateContent?key=${apiKey}`;

  const prompt = [
    'Você resume, em português do Brasil, o que mudou num trecho de página web relacionada a taxas, equipamentos ou cupons de maquininhas de cartão.',
    `Marca: ${marcaNome || 'não identificada'}. Categoria monitorada: ${categoria}. URL: ${url}.`,
    'O trecho abaixo já foi reduzido às linhas que mudaram entre a coleta anterior e a atual — linhas com "-" saíram, linhas com "+" entraram, linhas sem sinal são contexto.',
    'Escreva um resumo curto (1 a 3 frases), objetivo, em português, dizendo o que mudou. NUNCA invente um número que não apareça literalmente no trecho. Se o trecho não permitir entender o que mudou de fato (por exemplo, só formatação), diga isso explicitamente em vez de adivinhar.',
    '',
    'Trecho:',
    trecho,
  ].join('\n');

  let ultimoErro;

  for (let tentativa = 1; tentativa <= TENTATIVAS; tentativa++) {
    const resposta = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        contents: [{ parts: [{ text: prompt }] }],
        // 1024 e nao 300: o gemini-3.6-flash gasta uma fatia do orcamento
        // "pensando" antes de responder (thoughtsTokenCount no retorno da
        // API) — testado em 14/09/2026, 300 cortava a resposta na metade.
        generationConfig: { temperature: 0.2, maxOutputTokens: 1024 },
      }),
    });

    if (resposta.ok) {
      const dados = await resposta.json();
      const texto = dados?.candidates?.[0]?.content?.parts?.map((p) => p.text).join('').trim();

      if (!texto) {
        throw new Error('Gemini não devolveu texto no resumo.');
      }

      return texto;
    }

    const corpo = await resposta.text().catch(() => '');
    ultimoErro = new Error(`Gemini respondeu HTTP ${resposta.status}: ${corpo.slice(0, 300)}`);

    if (tentativa < TENTATIVAS && valeTentarDeNovo(resposta.status)) {
      await new Promise((resolve) => setTimeout(resolve, ESPERA_ENTRE_TENTATIVAS_MS));
      continue;
    }

    throw ultimoErro;
  }

  throw ultimoErro;
}

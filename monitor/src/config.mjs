// Le as variaveis de ambiente que o monitor precisa. Em producao (GitHub
// Actions ou cron no Mac) elas vem do ambiente de verdade — nao ha .env
// carregado aqui de proposito, para nao esconder um secret faltando atras
// de um arquivo. Para rodar local, exporte as variaveis antes (ver README).

function obrigatoria(nome) {
  const valor = process.env[nome];
  if (!valor) {
    throw new Error(`Variavel de ambiente obrigatoria ausente: ${nome}`);
  }
  return valor;
}

/**
 * @param {object} [opcoes]
 * @param {boolean} [opcoes.exigirGemini] o resumo semanal (bin/resumo-semanal.mjs)
 *   nao chama o Gemini nenhuma vez — nao faz sentido travar essa Action por
 *   falta de uma chave que ela nao usa.
 */
export function carregarConfig({ exigirGemini = true } = {}) {
  return {
    apiLaravel: {
      baseUrl: obrigatoria('MONITOR_API_URL').replace(/\/+$/, ''),
      token: obrigatoria('MONITOR_API_TOKEN'),
    },
    telegram: {
      botToken: obrigatoria('TELEGRAM_BOT_TOKEN'),
      chatId: obrigatoria('TELEGRAM_CHAT_ID'),
    },
    gemini: {
      apiKey: exigirGemini ? obrigatoria('GEMINI_API_KEY') : process.env.GEMINI_API_KEY,
      modelo: process.env.GEMINI_MODEL || 'gemini-2.0-flash',
    },
  };
}

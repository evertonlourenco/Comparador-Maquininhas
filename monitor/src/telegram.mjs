/**
 * Um único ponto de envio ao Telegram. Texto puro (sem parse_mode) de
 * propósito — Markdown do Telegram exige escapar caracteres que aparecem o
 * tempo todo em taxa/preço (., -, (), etc.), e um erro de escape silencioso
 * vale menos do que uma mensagem sempre legível. O Telegram já autolinka
 * URLs em texto puro.
 */
export async function enviarTelegram({ botToken, chatId, texto }) {
  const resposta = await fetch(`https://api.telegram.org/bot${botToken}/sendMessage`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      chat_id: chatId,
      text: texto,
      disable_web_page_preview: true,
    }),
  });

  if (!resposta.ok) {
    const corpo = await resposta.text().catch(() => '');
    throw new Error(`Telegram respondeu HTTP ${resposta.status}: ${corpo.slice(0, 300)}`);
  }
}

export function mensagemMudanca({ fonte, resumo, linkAdmin }) {
  const linhas = [
    `🔔 Mudança detectada — ${fonte.marca_nome || fonte.marca_slug || fonte.id}`,
    `Categoria: ${fonte.categoria}`,
    '',
    resumo,
    '',
    `Fonte: ${fonte.url}`,
  ];
  if (linkAdmin) linhas.push(`Editar no admin: ${linkAdmin}`);
  return linhas.join('\n');
}

export function mensagemFalha({ fonte, mensagemErro, falhasConsecutivas, linkAdmin }) {
  const linhas = [
    `⚠️ Falha ao coletar — ${fonte.marca_nome || fonte.marca_slug || fonte.id}`,
    `Categoria: ${fonte.categoria}`,
    `Falhas seguidas nesta fonte: ${falhasConsecutivas}`,
    '',
    mensagemErro,
    '',
    `Fonte: ${fonte.url}`,
  ];
  if (linkAdmin) linhas.push(`Marca no admin: ${linkAdmin}`);
  return linhas.join('\n');
}

export function mensagemFalhaDoMonitor({ contexto, erro }) {
  return [
    '🛑 O monitor de mudanças falhou em si mesmo',
    `Contexto: ${contexto}`,
    '',
    String(erro?.stack || erro?.message || erro),
  ].join('\n');
}

export function mensagemResumoSemanal({ taxasSemVerificacao30Dias, cuponsVencendo7Dias }) {
  const linhas = [
    '📋 Resumo semanal do Máquina Certa',
    '',
    `Taxas sem verificação há mais de 30 dias: ${taxasSemVerificacao30Dias}`,
    '',
  ];

  if (cuponsVencendo7Dias.length === 0) {
    linhas.push('Nenhum cupom vencendo nos próximos 7 dias.');
  } else {
    linhas.push('Cupons vencendo nos próximos 7 dias:');
    for (const cupom of cuponsVencendo7Dias) {
      linhas.push(`- ${cupom.marca}: ${cupom.codigo} (até ${cupom.valido_ate})`);
    }
  }

  return linhas.join('\n');
}

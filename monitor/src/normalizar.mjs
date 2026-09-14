// Normaliza o texto visivel de uma pagina antes do hash, para que o monitor
// nao dispare alerta por causa de coisa que muda sozinha a cada visita e
// nao e "mudanca de taxa" nenhuma: data/hora, contador de visualizacao,
// token de sessao. E heuristico por natureza — a lista cresce quando um
// falso positivo aparecer na pratica, nao tenta prever tudo de antemao.

const MESES_PT = [
  'janeiro', 'fevereiro', 'março', 'marco', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];

const PADROES = [
  // ISO 8601: 2026-09-14, 2026-09-14T10:00:00Z
  /\b\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)?\b/gi,
  // dd/mm/aaaa, dd-mm-aaaa, dd.mm.aaaa
  /\b\d{1,2}[/.\-]\d{1,2}[/.\-]\d{2,4}\b/g,
  // "14 de setembro de 2026", "14 de set. de 2026"
  new RegExp(`\\b\\d{1,2}\\s+de\\s+(?:${MESES_PT.join('|')})[a-zçã.]*\\s+de\\s+\\d{4}\\b`, 'gi'),
  // Horario solto: 10:32, 10:32:05
  /\b\d{1,2}:\d{2}(?::\d{2})?\b/g,
  // "ha 3 minutos", "3 min atras", "2 dias atras"
  /\b(?:há|ha)\s+\d+\s+\w+(?:\s+atrás|\s+atras)?\b/gi,
  /\b\d+\s+\w+\s+atr[áa]s\b/gi,
  // Contadores: "1.234 visualizações", "56 pessoas vendo", "12 unidades vendidas"
  /\b[\d.,]+\s*(?:visualiza[cç][oõ]es|acessos|pessoas?\s+(?:vendo|visualizando|online)|unidades?\s+vendid[ao]s?|em\s+estoque)\b/gi,
  // Tokens longos que parecem sessao/CSRF/UUID (hex/base64-ish com 20+ chars)
  /\b[A-Za-z0-9_-]{20,}\b/g,
  // Parametros de URL de rastreio comuns, quando sobram em texto de link copiado
  /\b(?:utm_[a-z]+|fbclid|gclid|sessionid|session_id)=[^\s&]+/gi,
];

export function normalizar(textoBruto) {
  let texto = textoBruto;

  for (const padrao of PADROES) {
    texto = texto.replace(padrao, ' ');
  }

  return texto
    .replace(/\r\n?/g, '\n')
    .split('\n')
    .map((linha) => linha.replace(/[ \t]+/g, ' ').trim())
    .filter((linha) => linha.length > 0)
    .join('\n');
}

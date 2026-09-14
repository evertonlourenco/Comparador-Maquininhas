import { diffLines } from 'diff';

const LINHAS_DE_CONTEXTO = 2;
const LIMITE_CARACTERES = 6000;

/**
 * Reduz duas versoes de texto normalizado a um trecho pequeno com só as
 * linhas que mudaram (mais um pouco de contexto ao redor) — é isso, e não
 * a pagina inteira, que vai para o Gemini resumir. Mantém o prompt barato e
 * o resumo focado no que de fato mudou.
 */
export function calcularTrechoAlterado(textoAnterior, textoNovo) {
  const partes = diffLines(textoAnterior, textoNovo);
  const linhas = [];

  partes.forEach((parte, indice) => {
    if (parte.added || parte.removed) {
      const marcador = parte.added ? '+' : '-';
      for (const linha of parte.value.split('\n').filter(Boolean)) {
        linhas.push(`${marcador}${linha}`);
      }
      return;
    }

    // Contexto: só as linhas coladas numa mudança, não o texto inteiro que
    // não mudou.
    const vizinhaAntes = partes[indice - 1];
    const vizinhaDepois = partes[indice + 1];
    if (vizinhaAntes?.added || vizinhaAntes?.removed || vizinhaDepois?.added || vizinhaDepois?.removed) {
      const linhasDaParte = parte.value.split('\n').filter(Boolean);
      const inicio = vizinhaAntes?.added || vizinhaAntes?.removed ? linhasDaParte.slice(0, LINHAS_DE_CONTEXTO) : [];
      const fim = vizinhaDepois?.added || vizinhaDepois?.removed ? linhasDaParte.slice(-LINHAS_DE_CONTEXTO) : [];
      for (const linha of new Set([...inicio, ...fim])) {
        linhas.push(` ${linha}`);
      }
    }
  });

  const trecho = linhas.join('\n');
  return trecho.length > LIMITE_CARACTERES ? trecho.slice(0, LIMITE_CARACTERES) + '\n[...trecho cortado...]' : trecho;
}

export function houveMudancaRelevante(trecho) {
  return trecho.trim().length > 0;
}

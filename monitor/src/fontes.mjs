import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const CAMINHO_FONTES = path.join(__dirname, '..', 'fontes.json');

/**
 * Carrega fontes.json — a lista de URLs monitoradas, versionada neste
 * repositorio (nao num banco: ver a nota na migration de deteccoes_de_mudanca
 * do repositorio principal). Uma fonte so entra na execucao se tiver
 * `ativo: true` e uma `url` preenchida — as duas coisas juntas é o que
 * distingue uma fonte pronta de um lembrete de pendencia.
 */
export async function carregarFontes() {
  const bruto = await readFile(CAMINHO_FONTES, 'utf8');
  const fontes = JSON.parse(bruto);
  return fontes.filter((f) => f.ativo === true && typeof f.url === 'string' && f.url.length > 0);
}

/**
 * @param {object} opcoes
 * @param {string} [opcoes.categoria] filtra por categoria; omitido = todas
 * @param {boolean} [opcoes.somenteMac] quando true, so fontes com roda_no_mac=true;
 *   quando false (padrao), exclui essas — elas nunca devem rodar no GitHub Actions
 */
export async function filtrarFontes({ categoria, somenteMac = false } = {}) {
  const fontes = await carregarFontes();

  return fontes.filter((f) => {
    if (categoria && f.categoria !== categoria) return false;
    if (somenteMac) return f.roda_no_mac === true;
    return f.roda_no_mac !== true;
  });
}

import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DIR_ESTADO = path.join(__dirname, '..', 'estado');

/**
 * O estado de cada fonte (hash + texto normalizado da ultima coleta) mora
 * em estado/<fonte_id>.json, commitado pelo proprio workflow do GitHub
 * Actions ao final da execucao. Isso e o que da memoria a um runner efemero
 * sem precisar de banco nem de API de leitura no Laravel — e de brinde, o
 * `git log` desses arquivos vira um historico de quando cada pagina mudou.
 */
function caminhoDe(fonteId) {
  return path.join(DIR_ESTADO, `${fonteId}.json`);
}

export async function lerEstado(fonteId) {
  try {
    const bruto = await readFile(caminhoDe(fonteId), 'utf8');
    return JSON.parse(bruto);
  } catch (erro) {
    if (erro.code === 'ENOENT') return null;
    throw erro;
  }
}

export async function salvarEstado(fonteId, estado) {
  await mkdir(DIR_ESTADO, { recursive: true });
  await writeFile(caminhoDe(fonteId), JSON.stringify(estado, null, 2) + '\n', 'utf8');
}

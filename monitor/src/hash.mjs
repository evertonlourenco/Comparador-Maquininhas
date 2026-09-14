import { createHash } from 'node:crypto';

export function hashDeTexto(texto) {
  return createHash('sha256').update(texto, 'utf8').digest('hex');
}

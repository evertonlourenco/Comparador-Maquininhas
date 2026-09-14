import { test } from 'node:test';
import assert from 'node:assert/strict';
import { hashDeTexto } from '../src/hash.mjs';

test('mesmo texto produz o mesmo hash', () => {
  assert.equal(hashDeTexto('Débito: 1,99%'), hashDeTexto('Débito: 1,99%'));
});

test('texto diferente produz hash diferente', () => {
  assert.notEqual(hashDeTexto('Débito: 1,99%'), hashDeTexto('Débito: 2,10%'));
});

test('hash tem 64 caracteres hexadecimais (sha256)', () => {
  assert.match(hashDeTexto('qualquer coisa'), /^[a-f0-9]{64}$/);
});

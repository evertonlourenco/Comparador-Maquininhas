import { test } from 'node:test';
import assert from 'node:assert/strict';
import { calcularTrechoAlterado, houveMudancaRelevante } from '../src/diff.mjs';

test('captura a linha que mudou de valor, com contexto ao redor', () => {
  const anterior = 'Débito à vista: 1,99%\nCrédito em 12x: 4,50%';
  const novo = 'Débito à vista: 2,10%\nCrédito em 12x: 4,50%';

  const trecho = calcularTrechoAlterado(anterior, novo);

  assert.match(trecho, /-Débito à vista: 1,99%/);
  assert.match(trecho, /\+Débito à vista: 2,10%/);
  // linha vizinha da mudança entra como contexto (prefixo de espaço)
  assert.match(trecho, / Crédito em 12x: 4,50%/);
});

test('linha distante da mudança nao entra como contexto', () => {
  const anterior = ['Débito à vista: 1,99%', 'linha 2', 'linha 3', 'linha 4', 'linha 5', 'linha distante'].join('\n');
  const novo = ['Débito à vista: 2,10%', 'linha 2', 'linha 3', 'linha 4', 'linha 5', 'linha distante'].join('\n');

  const trecho = calcularTrechoAlterado(anterior, novo);

  assert.doesNotMatch(trecho, /linha distante/);
});

test('texto identico nao gera trecho', () => {
  const texto = 'Débito à vista: 1,99%';
  const trecho = calcularTrechoAlterado(texto, texto);
  assert.equal(houveMudancaRelevante(trecho), false);
});

test('trecho muito grande e cortado', () => {
  const anterior = 'linha original';
  const novo = 'x'.repeat(10000);
  const trecho = calcularTrechoAlterado(anterior, novo);
  assert.ok(trecho.length <= 6100);
  assert.match(trecho, /\[\.\.\.trecho cortado\.\.\.\]/);
});

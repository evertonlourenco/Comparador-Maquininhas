import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizar } from '../src/normalizar.mjs';

test('remove data no formato dd/mm/aaaa', () => {
  const resultado = normalizar('Tabela verificada em 14/09/2026.');
  assert.doesNotMatch(resultado, /14\/09\/2026/);
});

test('remove data por extenso em portugues', () => {
  const resultado = normalizar('Atualizado em 14 de setembro de 2026.');
  assert.doesNotMatch(resultado, /14 de setembro de 2026/);
});

test('remove contador de visualizacoes', () => {
  const resultado = normalizar('Débito à vista: 1,99%. 1.234 visualizações esta semana.');
  assert.doesNotMatch(resultado, /visualiza/i);
});

test('remove token longo tipo sessao', () => {
  const resultado = normalizar('id da sessao abc123def456ghi789jklXYZ na pagina');
  assert.doesNotMatch(resultado, /abc123def456ghi789jklXYZ/);
});

test('preserva o conteudo de taxa que importa', () => {
  const resultado = normalizar('Débito à vista: 1,99%\nCrédito em 12x: 4,50%');
  assert.match(resultado, /Débito à vista: 1,99%/);
  assert.match(resultado, /Crédito em 12x: 4,50%/);
});

test('duas coletas com so a data mudando normalizam igual', () => {
  const ontem = normalizar('Verificado em 13/09/2026. Débito: 1,99%.');
  const hoje = normalizar('Verificado em 14/09/2026. Débito: 1,99%.');
  assert.equal(ontem, hoje);
});

test('colapsa espacos e remove linhas vazias', () => {
  const resultado = normalizar('linha 1   com   espaço\n\n\nlinha 2');
  assert.equal(resultado, 'linha 1 com espaço\nlinha 2');
});

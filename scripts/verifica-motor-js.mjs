#!/usr/bin/env node
//
// Compara o motor em JavaScript com o motor em PHP sobre os mesmos casos.
//
// Recebe o caminho de um arquivo JSON produzido por
// tests/Feature/Motor/ParidadeDoMotorTest.php, com os catalogos, os cenarios e
// o resultado que o PHP produziu para cada um. Roda o motor daqui sobre a
// mesma entrada e compara campo a campo.
//
//   node scripts/verifica-motor-js.mjs caminho/para/casos.json
//
// Sai com 0 quando tudo bate e 1 na primeira divergencia, imprimindo o caminho
// do campo, o valor do PHP e o valor do JavaScript.

import { readFileSync } from 'node:fs';
import { arredondar, numero } from '../resources/js/comparador/dinheiro.mjs';
import { calcular } from '../resources/js/comparador/motor.mjs';

const caminho = process.argv[2];

if (!caminho) {
  console.error('Uso: node scripts/verifica-motor-js.mjs <casos.json>');
  process.exit(2);
}

// Regra 11 manda usar Intl.NumberFormat('pt-BR'). Num Node compilado sem ICU
// completo o pt-BR cai em en-US silenciosamente e todo valor formatado
// divergiria - o que seria lido como bug do motor. Melhor dizer o que e.
const amostra = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(1234.5);

if (amostra !== '1.234,50') {
  console.error(
    `Este Node nao formata pt-BR (Intl deu "${amostra}", esperado "1.234,50"). ` +
      'Provavelmente foi compilado sem ICU completo. O teste de paridade nao pode rodar assim.',
  );
  process.exit(2);
}

const { catalogos, casos, centavos } = JSON.parse(readFileSync(caminho, 'utf8'));
const divergencias = [];

// O primitivo antes dos cenarios. Um cenario so encosta no desempate de meio
// centavo por acaso; esta tabela encosta de proposito, e e ela que pega a
// troca de arredondar() por Math.round().
for (const { valor, casas, esperado, formatado } of centavos ?? []) {
  const doJs = arredondar(valor, casas);

  if (doJs !== esperado) {
    divergencias.push(`arredondar(${valor}, ${casas}): PHP ${esperado} != JS ${doJs}`);
  }

  const formatadoJs = numero(valor, casas);

  if (formatadoJs !== formatado) {
    divergencias.push(`numero(${valor}, ${casas}): PHP ${JSON.stringify(formatado)} != JS ${JSON.stringify(formatadoJs)}`);
  }
}

for (const caso of casos) {
  const catalogo = catalogos[caso.catalogo];

  if (!catalogo) {
    divergencias.push(`${caso.nome}: catalogo "${caso.catalogo}" nao veio no arquivo de casos.`);
    continue;
  }

  let doJs;

  try {
    doJs = calcular(catalogo, caso.cenario);
  } catch (erro) {
    divergencias.push(`${caso.nome}: o motor em JavaScript lancou "${erro.message}".`);
    continue;
  }

  comparar(caso.esperado, doJs, caso.nome, divergencias);
}

if (divergencias.length > 0) {
  console.error(`${divergencias.length} divergência(s) entre o motor em PHP e o motor em JavaScript:\n`);
  // Mais de umas poucas linhas viram ruido: a primeira ja diz onde olhar.
  divergencias.slice(0, 20).forEach((linha) => console.error(`  - ${linha}`));

  if (divergencias.length > 20) {
    console.error(`  ... e mais ${divergencias.length - 20}.`);
  }

  process.exit(1);
}

console.log(`OK: ${casos.length} caso(s) conferem entre PHP e JavaScript.`);

function comparar(php, js, caminhoDoCampo, saida) {
  if (vazio(php) && vazio(js)) {
    // O PHP serializa array vazio como [] e objeto vazio como {}. Aqui os dois
    // significam "nenhum item", entao a diferenca de forma nao e divergencia.
    return;
  }

  if (php === null || js === null || typeof php !== 'object' || typeof js !== 'object') {
    if (php !== js) {
      saida.push(`${caminhoDoCampo}: PHP ${formatar(php)} != JS ${formatar(js)}`);
    }

    return;
  }

  if (Array.isArray(php) !== Array.isArray(js)) {
    saida.push(`${caminhoDoCampo}: PHP ${Array.isArray(php) ? 'lista' : 'objeto'} != JS ${Array.isArray(js) ? 'lista' : 'objeto'}`);

    return;
  }

  if (Array.isArray(php)) {
    if (php.length !== js.length) {
      saida.push(`${caminhoDoCampo}: PHP tem ${php.length} item(ns), JS tem ${js.length}`);

      return;
    }

    php.forEach((item, i) => comparar(item, js[i], `${caminhoDoCampo}[${i}]`, saida));

    return;
  }

  const chaves = new Set([...Object.keys(php), ...Object.keys(js)]);

  for (const chave of chaves) {
    if (!(chave in php)) {
      saida.push(`${caminhoDoCampo}.${chave}: só existe no JS (${formatar(js[chave])})`);
      continue;
    }

    if (!(chave in js)) {
      saida.push(`${caminhoDoCampo}.${chave}: só existe no PHP (${formatar(php[chave])})`);
      continue;
    }

    comparar(php[chave], js[chave], `${caminhoDoCampo}.${chave}`, saida);
  }
}

function vazio(valor) {
  return (
    valor !== null &&
    typeof valor === 'object' &&
    (Array.isArray(valor) ? valor.length === 0 : Object.keys(valor).length === 0)
  );
}

function formatar(valor) {
  return typeof valor === 'string' ? JSON.stringify(valor) : String(valor);
}

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
//
// A camada de resumo da etapa 07 tem o verificador irmao ao lado:
// scripts/verifica-resumo-js.mjs. Os dois dividem scripts/lib/comparar.mjs.

import { readFileSync } from 'node:fs';
import { arredondar, numero } from '../resources/js/comparador/dinheiro.mjs';
import { calcular } from '../resources/js/comparador/motor.mjs';
import { comparar, exigirPtBr, relatar } from './lib/comparar.mjs';

const caminho = process.argv[2];

if (!caminho) {
  console.error('Uso: node scripts/verifica-motor-js.mjs <casos.json>');
  process.exit(2);
}

exigirPtBr();

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
    divergencias.push(
      `numero(${valor}, ${casas}): PHP ${JSON.stringify(formatado)} != JS ${JSON.stringify(formatadoJs)}`,
    );
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

process.exit(relatar(divergencias, casos.length, 'o motor'));

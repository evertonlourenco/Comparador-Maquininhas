#!/usr/bin/env node
//
// Compara a camada de resumo da etapa 07 em JavaScript com a de PHP, sobre os
// mesmos casos de borda da etapa 05.
//
// Recebe o caminho de um arquivo JSON produzido por
// tests/Feature/Comparador/ParidadeDoResumoTest.php, com os catalogos, os
// cenarios e o que App\Motor\ResumoDoComparador produziu para cada um. Aqui a
// cadeia inteira e refeita em JavaScript - motor e depois resumo -, porque e
// exatamente isso que o navegador faz: se o motor divergir, o resumo diverge
// junto, e o campo que aparecer na saida diz qual das duas camadas escorregou.
//
//   node scripts/verifica-resumo-js.mjs caminho/para/casos.json
//
// Sai com 0 quando tudo bate e 1 quando algo diverge.

import { readFileSync } from 'node:fs';
import { calcular } from '../resources/js/comparador/motor.mjs';
import { resumir } from '../resources/js/comparador/resumo.mjs';
import { comparar, exigirPtBr, relatar } from './lib/comparar.mjs';

const caminho = process.argv[2];

if (!caminho) {
  console.error('Uso: node scripts/verifica-resumo-js.mjs <casos.json>');
  process.exit(2);
}

exigirPtBr();

const { catalogos, casos } = JSON.parse(readFileSync(caminho, 'utf8'));
const divergencias = [];

for (const caso of casos) {
  const catalogo = catalogos[caso.catalogo];

  if (!catalogo) {
    divergencias.push(`${caso.nome}: catalogo "${caso.catalogo}" nao veio no arquivo de casos.`);
    continue;
  }

  let doJs;

  try {
    doJs = resumir(calcular(catalogo, caso.cenario));
  } catch (erro) {
    divergencias.push(`${caso.nome}: o resumo em JavaScript lancou "${erro.message}".`);
    continue;
  }

  comparar(caso.esperado, doJs, caso.nome, divergencias);
}

process.exit(relatar(divergencias, casos.length, 'o resumo'));

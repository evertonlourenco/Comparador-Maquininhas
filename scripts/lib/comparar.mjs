// A comparacao campo a campo usada pelos dois verificadores de paridade:
// scripts/verifica-motor-js.mjs (etapa 05) e scripts/verifica-resumo-js.mjs
// (etapa 07). Ela mora aqui porque a regra de leitura e a mesma nos dois - e
// duplicar o comparador do teste e o jeito mais discreto de fazer um dos dois
// passar a conferir menos que o outro.

/**
 * Percorre o resultado do PHP e o do JavaScript em paralelo, acumulando em
 * `saida` uma linha por divergencia, com o caminho do campo.
 */
export function comparar(php, js, caminhoDoCampo, saida) {
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
    saida.push(
      `${caminhoDoCampo}: PHP ${Array.isArray(php) ? 'lista' : 'objeto'} != JS ${Array.isArray(js) ? 'lista' : 'objeto'}`,
    );

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

/**
 * Regra 11: num Node compilado sem ICU completo o pt-BR cai em en-US
 * silenciosamente e todo valor formatado divergiria - o que seria lido como bug
 * do motor. Melhor dizer o que e, e nao rodar.
 */
export function exigirPtBr() {
  const amostra = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(1234.5);

  if (amostra !== '1.234,50') {
    console.error(
      `Este Node nao formata pt-BR (Intl deu "${amostra}", esperado "1.234,50"). ` +
        'Provavelmente foi compilado sem ICU completo. O teste de paridade nao pode rodar assim.',
    );
    process.exit(2);
  }
}

/** Imprime as divergencias e devolve o codigo de saida do processo. */
export function relatar(divergencias, quantosCasos, oQue) {
  if (divergencias.length > 0) {
    console.error(`${divergencias.length} divergência(s) entre ${oQue} em PHP e em JavaScript:\n`);
    // Mais de umas poucas linhas viram ruido: a primeira ja diz onde olhar.
    divergencias.slice(0, 20).forEach((linha) => console.error(`  - ${linha}`));

    if (divergencias.length > 20) {
      console.error(`  ... e mais ${divergencias.length - 20}.`);
    }

    return 1;
  }

  console.log(`OK: ${quantosCasos} caso(s) conferem entre PHP e JavaScript.`);

  return 0;
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

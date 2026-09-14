#!/usr/bin/env node
import { parseArgs } from 'node:util';
import { carregarConfig } from '../src/config.mjs';
import { executar } from '../src/monitorar.mjs';
import { enviarTelegram, mensagemFalhaDoMonitor } from '../src/telegram.mjs';

const { values } = parseArgs({
  options: {
    categoria: { type: 'string' },
    'somente-mac': { type: 'boolean', default: false },
  },
});

const categoriasValidas = ['tabela_taxas', 'equipamento_cupom', 'contrato_credenciamento'];
if (values.categoria && !categoriasValidas.includes(values.categoria)) {
  console.error(`--categoria precisa ser uma de: ${categoriasValidas.join(', ')}`);
  process.exit(1);
}

let config;
try {
  config = carregarConfig();
} catch (erro) {
  // Sem config nem dá para avisar no Telegram — isso só pode aparecer no
  // log da Action mesmo, e é por isso que o workflow também falha (exit 1).
  console.error(erro.message);
  process.exit(1);
}

try {
  const { totalFalhas } = await executar({
    categoria: values.categoria,
    somenteMac: values['somente-mac'],
    config,
  });

  if (totalFalhas > 0) {
    // Cada falha individual já avisou no Telegram dentro de executar(); o
    // exit code diferente de zero é só para a Action também aparecer
    // vermelha, como um segundo sinal.
    process.exit(1);
  }
} catch (erro) {
  console.error(erro);
  try {
    await enviarTelegram({
      botToken: config.telegram.botToken,
      chatId: config.telegram.chatId,
      texto: mensagemFalhaDoMonitor({ contexto: `monitorar --categoria=${values.categoria || '(todas)'}`, erro }),
    });
  } catch (erroTelegram) {
    console.error(`Nem o aviso de falha do monitor foi enviado: ${erroTelegram.message}`);
  }
  process.exit(1);
}

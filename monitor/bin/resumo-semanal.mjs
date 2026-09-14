#!/usr/bin/env node
import { carregarConfig } from '../src/config.mjs';
import { executarResumoSemanal } from '../src/resumoSemanal.mjs';
import { enviarTelegram, mensagemFalhaDoMonitor } from '../src/telegram.mjs';

let config;
try {
  config = carregarConfig({ exigirGemini: false });
} catch (erro) {
  console.error(erro.message);
  process.exit(1);
}

try {
  await executarResumoSemanal(config);
} catch (erro) {
  console.error(erro);
  try {
    await enviarTelegram({
      botToken: config.telegram.botToken,
      chatId: config.telegram.chatId,
      texto: mensagemFalhaDoMonitor({ contexto: 'resumo-semanal', erro }),
    });
  } catch (erroTelegram) {
    console.error(`Nem o aviso de falha do monitor foi enviado: ${erroTelegram.message}`);
  }
  process.exit(1);
}

import { buscarResumoSemanal } from './apiLaravel.mjs';
import { enviarTelegram, mensagemResumoSemanal } from './telegram.mjs';

export async function executarResumoSemanal(config) {
  const resumo = await buscarResumoSemanal({
    baseUrl: config.apiLaravel.baseUrl,
    token: config.apiLaravel.token,
  });

  await enviarTelegram({
    botToken: config.telegram.botToken,
    chatId: config.telegram.chatId,
    texto: mensagemResumoSemanal({
      taxasSemVerificacao30Dias: resumo.taxas_sem_verificacao_30_dias,
      cuponsVencendo7Dias: resumo.cupons_vencendo_7_dias,
    }),
  });
}

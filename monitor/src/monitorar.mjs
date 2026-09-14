import { filtrarFontes } from './fontes.mjs';
import { lerEstado, salvarEstado } from './estado.mjs';
import { coletar, ErroDeColeta } from './coletor.mjs';
import { normalizar } from './normalizar.mjs';
import { hashDeTexto } from './hash.mjs';
import { calcularTrechoAlterado, houveMudancaRelevante } from './diff.mjs';
import { resumirMudanca } from './gemini.mjs';
import { registrarDeteccao } from './apiLaravel.mjs';
import { enviarTelegram, mensagemMudanca, mensagemFalha } from './telegram.mjs';

/**
 * Processa uma fonte só. Nunca deixa uma exceção subir sem antes tentar
 * avisar no Telegram — "falha nunca pode ser silenciosa" vale também para
 * um bug neste script, não só para a fonte estar fora do ar.
 *
 * @returns {{fonteId: string, resultado: 'sem_mudanca'|'baseline'|'mudanca'|'falha'}}
 */
export async function processarFonte(fonte, config) {
  const agora = new Date().toISOString();

  try {
    const textoBruto = await coletar(fonte);
    const textoNormalizado = normalizar(textoBruto);
    const hashNovo = hashDeTexto(textoNormalizado);
    const estadoAnterior = await lerEstado(fonte.id);

    if (!estadoAnterior) {
      await salvarEstado(fonte.id, {
        hash: hashNovo,
        texto_normalizado: textoNormalizado,
        verificado_em: agora,
        falhas_consecutivas: 0,
      });
      console.log(`[baseline] ${fonte.id}: primeira coleta, nada a comparar ainda.`);
      return { fonteId: fonte.id, resultado: 'baseline' };
    }

    if (estadoAnterior.hash === hashNovo) {
      await salvarEstado(fonte.id, { ...estadoAnterior, verificado_em: agora, falhas_consecutivas: 0 });
      console.log(`[sem mudança] ${fonte.id}`);
      return { fonteId: fonte.id, resultado: 'sem_mudanca' };
    }

    const trecho = calcularTrechoAlterado(estadoAnterior.texto_normalizado, textoNormalizado);

    if (!houveMudancaRelevante(trecho)) {
      // Hash mudou mas o diff de linhas não achou nada de fato (ex.: só
      // reordenação). Atualiza o estado e não incomoda ninguém no Telegram.
      await salvarEstado(fonte.id, { hash: hashNovo, texto_normalizado: textoNormalizado, verificado_em: agora, falhas_consecutivas: 0 });
      console.log(`[mudança sem trecho relevante] ${fonte.id}`);
      return { fonteId: fonte.id, resultado: 'sem_mudanca' };
    }

    const resumo = await resumirMudanca({
      apiKey: config.gemini.apiKey,
      modelo: config.gemini.modelo,
      marcaNome: fonte.marca_nome,
      categoria: fonte.categoria,
      url: fonte.url,
      trecho,
    });

    let linkAdmin = null;
    try {
      const { link_admin: link } = await registrarDeteccao({
        baseUrl: config.apiLaravel.baseUrl,
        token: config.apiLaravel.token,
        corpo: {
          fonte_id: fonte.id,
          marca_slug: fonte.marca_slug,
          categoria: fonte.categoria,
          tipo: 'mudanca',
          url: fonte.url,
          resumo,
          trecho_alterado: trecho,
          hash_anterior: estadoAnterior.hash,
          hash_novo: hashNovo,
        },
      });
      linkAdmin = link;
    } catch (erroApi) {
      console.error(`[erro ao registrar no admin] ${fonte.id}: ${erroApi.message}`);
    }

    await enviarTelegram({
      botToken: config.telegram.botToken,
      chatId: config.telegram.chatId,
      texto: mensagemMudanca({ fonte, resumo, linkAdmin }),
    });

    await salvarEstado(fonte.id, { hash: hashNovo, texto_normalizado: textoNormalizado, verificado_em: agora, falhas_consecutivas: 0 });
    console.log(`[mudança] ${fonte.id}: ${resumo}`);
    return { fonteId: fonte.id, resultado: 'mudanca' };
  } catch (erro) {
    const estadoAnterior = await lerEstado(fonte.id).catch(() => null);
    const falhasConsecutivas = (estadoAnterior?.falhas_consecutivas ?? 0) + 1;

    await salvarEstado(fonte.id, {
      ...(estadoAnterior || {}),
      verificado_em: agora,
      falhas_consecutivas: falhasConsecutivas,
    });

    const mensagemErro = erro instanceof ErroDeColeta ? erro.message : `Erro inesperado: ${erro.message}`;

    let linkAdmin = null;
    try {
      const { link_admin: link } = await registrarDeteccao({
        baseUrl: config.apiLaravel.baseUrl,
        token: config.apiLaravel.token,
        corpo: {
          fonte_id: fonte.id,
          marca_slug: fonte.marca_slug,
          categoria: fonte.categoria,
          tipo: 'falha',
          url: fonte.url,
          mensagem_erro: mensagemErro,
        },
      });
      linkAdmin = link;
    } catch (erroApi) {
      console.error(`[erro ao registrar falha no admin] ${fonte.id}: ${erroApi.message}`);
    }

    try {
      await enviarTelegram({
        botToken: config.telegram.botToken,
        chatId: config.telegram.chatId,
        texto: mensagemFalha({ fonte, mensagemErro, falhasConsecutivas, linkAdmin }),
      });
    } catch (erroTelegram) {
      // Se nem o Telegram responde, o log da Action é o último recurso —
      // por isso o console.error, e por isso o processo sai com código != 0
      // no fim de executar().
      console.error(`[erro ao avisar no Telegram] ${fonte.id}: ${erroTelegram.message}`);
    }

    console.error(`[falha] ${fonte.id}: ${mensagemErro}`);
    return { fonteId: fonte.id, resultado: 'falha' };
  }
}

export async function executar({ categoria, somenteMac = false, config }) {
  const fontes = await filtrarFontes({ categoria, somenteMac });

  if (fontes.length === 0) {
    console.log('Nenhuma fonte ativa para os filtros informados.');
    return { totalFalhas: 0, resultados: [] };
  }

  const resultados = [];
  for (const fonte of fontes) {
    resultados.push(await processarFonte(fonte, config));
    // Politeza: um respiro entre requisições, mesmo fontes diferentes.
    await new Promise((resolve) => setTimeout(resolve, 2000));
  }

  const totalFalhas = resultados.filter((r) => r.resultado === 'falha').length;
  return { totalFalhas, resultados };
}

/**
 * Os dois únicos endpoints que este monitor chama no app Laravel (ver
 * routes/api.php e App\Http\Controllers\Api\MonitorController no
 * repositório principal). Sem endpoint de leitura de configuração — este
 * repositório é autossuficiente sobre o que monitorar (fontes.json) e sobre
 * o estado entre execuções (estado/*.json, commitado pela própria Action).
 */
export async function registrarDeteccao({ baseUrl, token, corpo }) {
  const resposta = await fetch(`${baseUrl}/api/monitor/deteccoes`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(corpo),
  });

  if (!resposta.ok) {
    const texto = await resposta.text().catch(() => '');
    throw new Error(`API do admin respondeu HTTP ${resposta.status} em /api/monitor/deteccoes: ${texto.slice(0, 300)}`);
  }

  return resposta.json();
}

export async function buscarResumoSemanal({ baseUrl, token }) {
  const resposta = await fetch(`${baseUrl}/api/monitor/resumo-semanal`, {
    headers: { Authorization: `Bearer ${token}` },
  });

  if (!resposta.ok) {
    const texto = await resposta.text().catch(() => '');
    throw new Error(`API do admin respondeu HTTP ${resposta.status} em /api/monitor/resumo-semanal: ${texto.slice(0, 300)}`);
  }

  return resposta.json();
}

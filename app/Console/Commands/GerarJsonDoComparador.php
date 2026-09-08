<?php

namespace App\Console\Commands;

use App\Motor\CatalogoDoComparador;
use App\Support\Dinheiro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Regra 9: o comparador roda no navegador sobre um JSON estatico gerado por
 * comando artisan. Sem consulta ao banco por visita - a carga vem em picos de
 * video, nao constante.
 *
 * Regra 10: por padrao so entra taxa publicada. --rascunhos existe para
 * conferir em localhost antes de aprovar, e carimba contem_rascunhos = true no
 * proprio arquivo, para que um arquivo de conferencia nunca passe por
 * definitivo em producao.
 */
class GerarJsonDoComparador extends Command
{
    protected $signature = 'comparador:gerar-json
        {--rascunhos : Inclui taxas em rascunho. Só para conferência em localhost (regra 10).}
        {--caminho= : Caminho do arquivo de saída. Padrão: public/dados/comparador.json}';

    protected $description = 'Gera o JSON estático que alimenta o comparador no navegador (regra 9).';

    public function handle(CatalogoDoComparador $catalogo): int
    {
        $incluirRascunhos = (bool) $this->option('rascunhos');
        $caminho = $this->option('caminho') ?: public_path('dados/comparador.json');

        $dados = $catalogo->montar($incluirRascunhos);

        File::ensureDirectoryExists(dirname($caminho));
        File::put($caminho, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $this->resumo($dados, $caminho, $incluirRascunhos);

        return self::SUCCESS;
    }

    private function resumo(array $dados, string $caminho, bool $incluirRascunhos): void
    {
        $planos = 0;
        $taxas = 0;
        $faixas = 0;
        $semDado = [];

        foreach ($dados['marcas'] as $marca) {
            $doMarca = 0;

            foreach ($marca['planos'] as $plano) {
                $planos++;
                $taxas += count($plano['taxas']);
                $faixas += count($plano['faixas']);
                $doMarca += count($plano['taxas']) + count($plano['faixas']);
            }

            // Regra 4: marca sem dado nao some. Ela aparece no JSON e aparece
            // aqui, para que o estado seja visivel antes de ir ao ar.
            if ($doMarca === 0) {
                $semDado[] = $marca['nome'];
            }
        }

        $this->table(['', ''], [
            ['Arquivo', $caminho],
            ['Tamanho', Dinheiro::numero(File::size($caminho) / 1024, 1).' KB'],
            ['Marcas', Dinheiro::numero(count($dados['marcas']), 0)],
            ['Planos', Dinheiro::numero($planos, 0)],
            ['Taxas divulgadas', Dinheiro::numero($taxas, 0)],
            ['Faixas reportadas', Dinheiro::numero($faixas, 0)],
        ]);

        if ($semDado !== []) {
            $this->warn('Sem taxa nem faixa: '.implode(', ', $semDado)
                .'. Elas continuam no JSON, com estado "sem dado publicado" (regra 4).');
        }

        if ($incluirRascunhos) {
            $this->warn('Arquivo gerado COM rascunhos (contem_rascunhos = true). Não publique este arquivo.');

            return;
        }

        if ($taxas === 0 && $faixas === 0) {
            $this->warn('Nenhuma taxa publicada: o arquivo saiu sem número nenhum. '
                .'Aprove as taxas no painel (regra 10) ou use --rascunhos para conferir em localhost.');
        }
    }
}

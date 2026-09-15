<?php

namespace Database\Seeders;

use App\Enums\FonteTipo;
use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Base das cargas por marca (etapa 04).
 *
 * Regra 6: nenhuma taxa entra sem fonte e data de verificacao, entao todo
 * metodo que grava taxa aqui exige o array de fonte - nao ha caminho curto
 * que pule isso.
 *
 * Regra 10: o status padrao e rascunho. Publicar e decisao humana no admin.
 */
abstract class SeederDeMarca extends Seeder
{
    /** Data em que as tabelas oficiais foram lidas nesta carga. */
    public const VERIFICADO_EM = '2026-09-08';

    private array $grupos = [];

    private array $prazos = [];

    protected function marca(string $slug): Marca
    {
        return Marca::where('slug', $slug)->sole();
    }

    /**
     * Etapa 17, achado em produção: reseed sobrescrevia `status` e `nome` de
     * quem já existia, desfazendo aprovação e rename feitos no painel. `nome`
     * e `status` só valem na criação - num plano que já existe, quem decide
     * os dois é o admin (Tabela do Plano renomeia; o painel normal pausa),
     * nunca uma releitura da fonte. Os demais atributos (mensalidade, taxa de
     * antecipação etc.) continuam vindo do seeder sempre: são fato de
     * domínio, não decisão administrativa.
     */
    protected function plano(Marca $marca, string $nome, array $atributos = []): Plano
    {
        $chave = ['marca_id' => $marca->getKey(), 'slug' => Str::slug($nome)];
        $valores = ['nome' => $nome, ...$atributos];

        if (Plano::where($chave)->exists()) {
            unset($valores['nome'], $valores['status']);
        }

        return Plano::updateOrCreate($chave, $valores);
    }

    /**
     * @param  string|null  $condicao  Etapa 05: o que o lojista precisa fazer
     *                                 para a taxa publicada valer - ativar a
     *                                 chave Pix no aplicativo, por exemplo.
     *                                 Diferente de observacao, que e nota
     *                                 interna: a condicao aparece junto do
     *                                 numero, sempre.
     */
    protected function fonte(
        string $url,
        ?string $observacao = null,
        FonteTipo $tipo = FonteTipo::SiteOficial,
        ?string $condicao = null,
        ?string $dataVerificacao = null,
    ): array {
        return [
            'url_fonte' => $url,
            'fonte_tipo' => $tipo,
            'data_verificacao' => $dataVerificacao ?? self::VERIFICADO_EM,
            'status' => StatusPublicacao::Rascunho,
            'observacao' => $observacao,
            'condicao' => $condicao,
        ];
    }

    /**
     * Achado em produção em 15/09/2026: rodar um seeder de novo (para pegar
     * marca nova ou corrigir um número) revalidava a chave de TODA taxa já
     * existente e reescrevia `status` para "rascunho" - desfazendo aprovação
     * que o Everton já tinha feito no painel, silenciosamente. `fonte()`
     * sempre devolve status rascunho porque é o padrão certo pra taxa NOVA
     * (regra 10); numa que já existe, quem decide o status é o admin, nunca
     * o reseed. Célula que já existe: número, fonte e data continuam vindo
     * do seeder (são fato, releitura corrige); status nunca é tocado.
     */
    protected function taxa(
        Plano $plano,
        TipoOperacao $tipo,
        string $grupo,
        string $prazo,
        int $parcelas,
        float $percentual,
        array $fonte,
    ): void {
        $chave = [
            'plano_id' => $plano->getKey(),
            'tipo_operacao' => $tipo->value,
            'grupo_bandeira_id' => $this->grupo($grupo),
            'parcelas' => $parcelas,
            'prazo_recebimento_id' => $this->prazo($prazo),
        ];

        $valores = [...$fonte, ...['percentual' => $percentual, 'valor_fixo' => 0]];

        if (TaxaDivulgada::where($chave)->exists()) {
            unset($valores['status']);
        }

        TaxaDivulgada::updateOrCreate($chave, $valores);
    }

    protected function debito(Plano $plano, string $grupo, string $prazo, float $percentual, array $fonte): void
    {
        $this->taxa($plano, TipoOperacao::Debito, $grupo, $prazo, 1, $percentual, $fonte);
    }

    /**
     * Etapa 05, decisao 1: o Pix nao tem bandeira e por isso nao tinha grupo
     * onde entrar. Agora tem um grupo tecnico proprio, e o helper e o unico
     * caminho para grava-lo - assim nenhuma carga cai na tentacao de escolher
     * visa_master "porque a coluna e obrigatoria".
     *
     * Pix nao parcela: parcelas e sempre 1.
     */
    protected function pix(Plano $plano, string $prazo, float $percentual, array $fonte): void
    {
        $this->taxa($plano, TipoOperacao::Pix, GrupoBandeira::PIX, $prazo, 1, $percentual, $fonte);
    }

    /**
     * Uma tabela de credito inteira, do jeito que a marca publica: o indice 0
     * do array e 1x (credito a vista) e os seguintes sao 2x, 3x... (credito
     * parcelado). Regra 2: cada parcela vira uma linha propria, nunca faixa.
     *
     * @param  list<float>  $percentuais
     */
    protected function serieDeCredito(Plano $plano, string $grupo, string $prazo, array $percentuais, array $fonte): void
    {
        foreach (array_values($percentuais) as $i => $percentual) {
            $parcelas = $i + 1;

            $this->taxa(
                $plano,
                $parcelas === 1 ? TipoOperacao::CreditoAvista : TipoOperacao::CreditoParcelado,
                $grupo,
                $prazo,
                $parcelas,
                $percentual,
                $fonte,
            );
        }
    }

    /** Marca que publica um unico percentual para toda a faixa de parcelas. */
    protected function creditoParceladoConstante(
        Plano $plano,
        string $grupo,
        string $prazo,
        int $de,
        int $ate,
        float $percentual,
        array $fonte,
    ): void {
        foreach (range($de, $ate) as $parcelas) {
            $this->taxa($plano, TipoOperacao::CreditoParcelado, $grupo, $prazo, $parcelas, $percentual, $fonte);
        }
    }

    private function grupo(string $codigo): int
    {
        return $this->grupos[$codigo] ??= GrupoBandeira::where('codigo', $codigo)->value('id');
    }

    private function prazo(string $codigo): int
    {
        return $this->prazos[$codigo] ??= PrazoRecebimento::where('codigo', $codigo)->value('id');
    }
}

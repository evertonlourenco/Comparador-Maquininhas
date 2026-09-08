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

    protected function plano(Marca $marca, string $nome, array $atributos = []): Plano
    {
        return Plano::updateOrCreate(
            ['marca_id' => $marca->getKey(), 'slug' => Str::slug($nome)],
            [...['nome' => $nome], ...$atributos],
        );
    }

    protected function fonte(string $url, ?string $observacao = null, FonteTipo $tipo = FonteTipo::SiteOficial): array
    {
        return [
            'url_fonte' => $url,
            'fonte_tipo' => $tipo,
            'data_verificacao' => self::VERIFICADO_EM,
            'status' => StatusPublicacao::Rascunho,
            'observacao' => $observacao,
        ];
    }

    protected function taxa(
        Plano $plano,
        TipoOperacao $tipo,
        string $grupo,
        string $prazo,
        int $parcelas,
        float $percentual,
        array $fonte,
    ): void {
        TaxaDivulgada::updateOrCreate(
            [
                'plano_id' => $plano->getKey(),
                'tipo_operacao' => $tipo->value,
                'grupo_bandeira_id' => $this->grupo($grupo),
                'parcelas' => $parcelas,
                'prazo_recebimento_id' => $this->prazo($prazo),
            ],
            [...$fonte, ...['percentual' => $percentual, 'valor_fixo' => 0]],
        );
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

<?php

namespace App\Support\Marcas;

use App\Models\Marca;

/**
 * "Vantagens e características" da página individual (etapa 08, seção 6).
 *
 * Cada frase vem de um campo verificado do catálogo — nunca um palpite de
 * marketing. É o mesmo espírito da regra 6 (nenhuma taxa sem fonte) aplicado
 * a esta seção: só afirma o que o cadastro sustenta, e um equipamento ou
 * plano sem o campo preenchido simplesmente não gera a frase.
 *
 * Espera 'planos.equipamentos' (pivot ativo) já carregado.
 */
final class VantagensDaMarca
{
    /** @return list<string> */
    public static function listar(Marca $marca): array
    {
        $equipamentos = $marca->planos->flatMap->equipamentos->unique('id');

        $vantagens = [];

        if ($equipamentos->contains(fn ($e) => $e->tem_chip_gratis)) {
            $vantagens[] = 'Chip grátis em pelo menos um modelo de maquininha.';
        }

        if ($equipamentos->contains(fn ($e) => $e->aceita_nfc)) {
            $vantagens[] = 'Aceita pagamento por aproximação (NFC) em pelo menos um modelo.';
        }

        if ($equipamentos->contains(fn ($e) => $e->imprime_comprovante)) {
            $vantagens[] = 'Ao menos um modelo imprime o comprovante na hora.';
        }

        if ($equipamentos->isNotEmpty() && $equipamentos->every(fn ($e) => ! $e->exige_celular)) {
            $vantagens[] = 'Funciona sem depender de celular pareado.';
        }

        $parcelasAdesao = $marca->planos
            ->flatMap(fn ($p) => $p->equipamentos->pluck('pivot.parcelas_adesao'))
            ->filter()
            ->max();

        if ($parcelasAdesao) {
            $vantagens[] = "Parcela a adesão em até {$parcelasAdesao}x sem juros.";
        }

        if ($marca->planos->contains(fn ($p) => $p->mensalidade !== null && (float) $p->mensalidade === 0.0)) {
            $vantagens[] = 'Tem plano permanente sem mensalidade.';
        }

        foreach ($marca->planos->pluck('condicao_isencao')->filter()->unique() as $condicao) {
            $vantagens[] = $condicao;
        }

        return $vantagens;
    }
}

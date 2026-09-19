<?php

namespace App\Support\Saude;

use App\Enums\TipoOperacao;
use App\Models\Equipamento;
use App\Models\Marca;
use App\Models\TaxaDivulgada;
use App\Motor\CatalogoDoComparador;
use App\Motor\Cenario;
use App\Motor\MotorDeCalculo;
use App\Motor\VendaDoCenario;

/**
 * A trava da marca (etapa 19, 17/09/2026).
 *
 * Nasceu de um erro real: a etapa 17 foi dada como "completa" para seis
 * marcas olhando só a contagem de taxas publicadas (534/534, 117/117...),
 * e várias delas tinham `mensalidade` nula — dado que o motor sempre trata
 * como "falta", nunca como zero (regra 4). O site teria ido ao ar mostrando
 * "falta dado" pra visitante em marca que o painel dizia estar pronta.
 *
 * A resposta não podia ser "eu confiro com mais cuidado da próxima vez" — é
 * exatamente o tipo de coisa que passa despercebida numa conferência manual.
 * Tinha que ser o próprio motor conferindo, porque ele já sabe, campo a
 * campo, o que faz um plano fechar a conta: é o mesmo `faltando` que aparece
 * pro visitante como "Falta dado para fechar esta conta". Rodar essa mesma
 * verificação contra um cenário de referência - um que usa todas as quatro
 * formas de pagamento, pra nenhum campo escapar por não ter sido exercitado -
 * e agregar o resultado de todos os planos da marca é o que esta classe faz.
 * Zero regra nova: é a mesma regra do motor, aplicada mais cedo.
 *
 * O que ela cobra, por marca:
 *   - logo cadastrado e nota do Reclame Aqui (com data da consulta);
 *   - todo plano tem pelo menos um equipamento vinculado, e nenhum deles está
 *     sem foto (`imagem_path`) — isso o motor não cobra, porque preço e foto
 *     são coisas diferentes pra ele; aqui são as duas;
 *   - todo plano com taxa publicada fecha a conta sem "falta dado" nenhum,
 *     rodando o cenário de referência através do próprio `MotorDeCalculo`.
 *
 * O que ela NÃO cobra, de propósito: tarifa de saque, TED e Pix (recebido ou
 * enviado), e antecipação avulsa. Decisão do Everton em 17/09/2026: esses são
 * custos da CONTA DIGITAL da adquirente, e o lojista não é obrigado a usá-la
 * — pode receber o que a maquininha processa na conta do próprio banco. O
 * Máquina Certa compara só o que é inescapável pra quem usa a maquininha:
 * taxa de venda, custo de adesão/aluguel do aparelho e mensalidade (quando
 * existe). `MotorDeCalculo::custoDaConta()` parou de ler esses campos
 * inteiramente (não é só um "não bloquear" aqui — o motor não soma mais
 * esse custo nenhum), e como esta classe roda o mesmo método, a mudança já
 * vale aqui de graça, sem nada pra tocar.
 */
final class CompletudeDaMarca
{
    /** @return array{completa: bool, pendencias: list<string>} */
    public static function avaliar(Marca $marca): array
    {
        $marcaArr = app(CatalogoDoComparador::class)->paraMarca($marca->getKey());

        if ($marcaArr === null) {
            return ['completa' => false, 'pendencias' => ['Marca inativa ou não encontrada.']];
        }

        return self::avaliarArray($marcaArr);
    }

    /**
     * A mesma avaliação, a partir do array já montado (o formato de
     * `CatalogoDoComparador::montar()`) — usada pelo próprio catálogo ao
     * decidir quem entra no JSON público, sem reconsultar o banco por marca.
     *
     * @param  array<string, mixed>  $marcaArr
     * @return array{completa: bool, pendencias: list<string>}
     */
    public static function avaliarArray(array $marcaArr): array
    {
        $pendencias = [];

        if (($marcaArr['logo_url'] ?? null) === null) {
            $pendencias[] = 'Logo da marca não cadastrado.';
        }

        // Regra 8 (revista em 19/09/2026): nota do Reclame Aqui e manual, mensal,
        // e toda marca so vai ao ar com ela preenchida e com a data da consulta.
        $ra = $marcaArr['reclame_aqui'] ?? [];

        if (($ra['nota'] ?? null) === null) {
            $pendencias[] = 'Nota do Reclame Aqui não preenchida.';
        } elseif (($ra['consultado_em'] ?? null) === null) {
            $pendencias[] = 'Data da consulta da nota do Reclame Aqui não preenchida.';
        }

        $planos = $marcaArr['planos'] ?? [];

        if ($planos === []) {
            $pendencias[] = 'Nenhum plano cadastrado.';

            return ['completa' => false, 'pendencias' => $pendencias];
        }

        $catalogo = app(CatalogoDoComparador::class);
        $motor = new MotorDeCalculo();
        $catalogoMinimo = [
            'prazos' => $catalogo->prazos(),
            'grupos' => $catalogo->grupos(),
            'marcas' => [$marcaArr],
            'dias_ate_degradar' => TaxaDivulgada::DIAS_ATE_DEGRADAR,
        ];
        $cenario = self::cenarioDeReferencia();
        $idsDeEquipamento = [];

        foreach ($planos as $plano) {
            $rotulo = $plano['nome'];

            foreach ($plano['equipamentos'] as $equipamento) {
                $idsDeEquipamento[$equipamento['id']] = $equipamento['nome'];
            }

            // Regra 4, classe B: plano so com faixa reportada nao tem "conta"
            // nem "aparelho" no sentido que o motor cobra pra taxa divulgada -
            // a faixa e o dado ali, e ele ja vem com o numero de relatos junto
            // (curadoria manual da propria tela de relatos, nao desta trava).
            // `avaliarPlanoParaCompletude()` nunca roda pra este ramo, entao
            // e o unico lugar que ainda cobra equipamento pra um plano assim.
            if ($plano['taxas'] === []) {
                if ($plano['faixas'] === []) {
                    $pendencias[] = "{$rotulo}: nenhuma taxa nem faixa publicada.";
                } elseif ($plano['equipamentos'] === []) {
                    $pendencias[] = "{$rotulo}: nenhum equipamento vinculado.";
                }

                continue;
            }

            // A partir daqui o plano tem taxa divulgada, e
            // avaliarPlanoParaCompletude() (via custoDoAparelho()) ja cobra
            // "equipamento vinculado a este plano" sozinho quando faltar -
            // checar de novo aqui so duplicaria a mesma mensagem.
            $resultado = $motor->avaliarPlanoParaCompletude($catalogoMinimo, $marcaArr, $plano, $cenario);

            foreach ($resultado['faltando'] as $falta) {
                $pendencias[] = "{$rotulo}: {$falta}.";
            }
        }

        if ($idsDeEquipamento !== []) {
            $semFoto = Equipamento::query()
                ->whereIn('id', array_keys($idsDeEquipamento))
                ->whereNull('imagem_path')
                ->pluck('nome');

            foreach ($semFoto as $nome) {
                $pendencias[] = "Equipamento \"{$nome}\": sem foto.";
            }
        }

        return ['completa' => $pendencias === [], 'pendencias' => $pendencias];
    }

    /**
     * O cenário que exercita as quatro formas de pagamento de uma vez, pra
     * nenhum campo (mensalidade, tarifa de Pix recebido, taxa de cada tipo de
     * venda...) escapar da checagem por não ter sido usado. Prazo em branco
     * ("tanto faz"): a marca não pode ser reprovada por não publicar Pix num
     * prazo que Pix não usa por natureza (nenhuma marca publica Pix em "1 dia
     * útil" - achado registrado no CLAUDE.md em 17/09/2026).
     */
    private static function cenarioDeReferencia(): Cenario
    {
        return new Cenario(
            faturamentoMensal: 10_000.0,
            vendas: [
                new VendaDoCenario(TipoOperacao::Debito, 'visa_master', 1, 3_000.0, 100),
                new VendaDoCenario(TipoOperacao::CreditoAvista, 'visa_master', 1, 3_000.0, 100),
                new VendaDoCenario(TipoOperacao::CreditoParcelado, 'visa_master', 3, 2_000.0, 50),
                new VendaDoCenario(TipoOperacao::Pix, 'pix', 1, 2_000.0, 50),
            ],
            prazo: null,
            hoje: now()->toDateString(),
        );
    }
}

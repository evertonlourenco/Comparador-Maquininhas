<?php

namespace App\Motor;

use App\Support\Dinheiro;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * O que o lojista informa ao comparador. Objeto de entrada do motor.
 *
 * Regra 11: deArray() aceita dinheiro em pt-BR ("10.000,00"), porque e o que
 * vem de um campo de formulario. A conversao para float acontece aqui, na
 * fronteira, e daqui para dentro tudo ja e float.
 *
 * hoje e parametro, e nao Carbon::today() la dentro: o frescor da regra 8 e
 * calculado sobre ele, e um motor que le o relogio sozinho nao da para testar
 * nem para comparar com a implementacao em JavaScript.
 */
final readonly class Cenario
{
    /** Amortizacao da adesao: 12 meses. Ver a nota em MotorDeCalculo. */
    public const HORIZONTE_PADRAO = 12;

    /**
     * @param  list<VendaDoCenario>  $vendas
     * @param  string|null  $prazo  Codigo do prazo desejado, ou null para "o
     *                              mais barato que cada plano oferecer".
     */
    public function __construct(
        public float $faturamentoMensal,
        public array $vendas,
        public ?string $prazo = null,
        public bool $antecipacaoAvulsa = false,
        public int $horizonteMeses = self::HORIZONTE_PADRAO,
        public int $saquesMensais = 0,
        public int $tedsMensais = 0,
        public int $pixEnviosMensais = 0,
        public bool $aplicarCupom = true,
        public ?int $equipamentoId = null,
        public string $hoje = '',
    ) {
        if ($this->horizonteMeses < 1) {
            throw new InvalidArgumentException('O horizonte de amortizacao da adesao precisa ser de ao menos 1 mes.');
        }

        if ($this->vendas === []) {
            throw new InvalidArgumentException('Um cenario sem nenhuma venda nao tem o que comparar.');
        }
    }

    /**
     * Entrada crua - de formulario, de JSON, de teste. Dinheiro pode vir em
     * pt-BR; datas em Y-m-d.
     */
    public static function deArray(array $dados): self
    {
        return new self(
            faturamentoMensal: Dinheiro::doUsuario($dados['faturamento_mensal'] ?? null) ?? 0.0,
            vendas: array_map(VendaDoCenario::deArray(...), array_values($dados['vendas'] ?? [])),
            prazo: $dados['prazo'] ?? null,
            antecipacaoAvulsa: (bool) ($dados['antecipacao_avulsa'] ?? false),
            horizonteMeses: (int) ($dados['horizonte_meses'] ?? self::HORIZONTE_PADRAO),
            saquesMensais: (int) ($dados['saques_mensais'] ?? 0),
            tedsMensais: (int) ($dados['teds_mensais'] ?? 0),
            pixEnviosMensais: (int) ($dados['pix_envios_mensais'] ?? 0),
            aplicarCupom: (bool) ($dados['aplicar_cupom'] ?? true),
            equipamentoId: isset($dados['equipamento_id']) ? (int) $dados['equipamento_id'] : null,
            hoje: $dados['hoje'] ?? Carbon::today()->toDateString(),
        );
    }

    public function paraArray(): array
    {
        return [
            'faturamento_mensal' => $this->faturamentoMensal,
            'vendas' => array_map(fn (VendaDoCenario $v) => $v->paraArray(), $this->vendas),
            'prazo' => $this->prazo,
            'antecipacao_avulsa' => $this->antecipacaoAvulsa,
            'horizonte_meses' => $this->horizonteMeses,
            'saques_mensais' => $this->saquesMensais,
            'teds_mensais' => $this->tedsMensais,
            'pix_envios_mensais' => $this->pixEnviosMensais,
            'aplicar_cupom' => $this->aplicarCupom,
            'equipamento_id' => $this->equipamentoId,
            'hoje' => $this->hoje,
        ];
    }
}

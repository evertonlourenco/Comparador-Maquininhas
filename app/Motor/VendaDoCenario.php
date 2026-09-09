<?php

namespace App\Motor;

use App\Enums\TipoOperacao;
use App\Models\GrupoBandeira;
use App\Support\Dinheiro;

/**
 * Uma linha do mix de vendas do mes. Carrega as quatro dimensoes da chave da
 * regra 1 que dependem do lojista - a quinta, o prazo, e escolhida no cenario
 * inteiro ou resolvida pelo motor.
 *
 * quantidadeMensal e opcional de proposito: quase toda taxa carregada ate aqui
 * tem valor_fixo = 0, e nesse caso o numero de transacoes nao muda nada. Mas
 * quando ha valor fixo por transacao (ou tarifa de Pix recebido), sem a
 * quantidade o motor nao estima: ele declara que falta.
 */
final readonly class VendaDoCenario
{
    public function __construct(
        public TipoOperacao $tipoOperacao,
        public string $grupo,
        public int $parcelas,
        public float $valorMensal,
        public ?int $quantidadeMensal = null,
    ) {}

    public static function deArray(array $dados): self
    {
        $tipo = $dados['tipo_operacao'] instanceof TipoOperacao
            ? $dados['tipo_operacao']
            : TipoOperacao::from($dados['tipo_operacao']);

        return new self(
            tipoOperacao: $tipo,
            // Pix nao tem bandeira: o grupo dele e sempre o grupo tecnico
            // (etapa 05, decisao 1), entao nao ha o que perguntar ao lojista.
            grupo: $tipo === TipoOperacao::Pix
                ? GrupoBandeira::PIX
                : ($dados['grupo'] ?? GrupoBandeira::VISA_MASTER),
            parcelas: (int) ($dados['parcelas'] ?? $tipo->parcelaMinima()),
            valorMensal: Dinheiro::doUsuario($dados['valor_mensal'] ?? null) ?? 0.0,
            quantidadeMensal: isset($dados['quantidade_mensal']) && $dados['quantidade_mensal'] !== null
                ? (int) $dados['quantidade_mensal']
                : null,
        );
    }

    /**
     * Como a linha aparece no resultado e nos avisos de falta.
     *
     * Os grupos entram por parametro porque este rotulo sai direto na tela do
     * lojista (etapa 07): "Débito (Visa e Mastercard)" e uma frase, "debito
     * (visa_master)" e um identificador de banco. Sem a dimensao em maos, o
     * codigo continua sendo o melhor que da para dizer.
     *
     * @param  array<string, array{nome: string}>|null  $grupos
     */
    public function rotulo(?array $grupos = null): string
    {
        $base = $this->tipoOperacao->getLabel();

        if ($this->tipoOperacao === TipoOperacao::CreditoParcelado) {
            $base .= " em {$this->parcelas}x";
        }

        if ($this->tipoOperacao === TipoOperacao::Pix) {
            return $base;
        }

        return "{$base} (".($grupos[$this->grupo]['nome'] ?? $this->grupo).')';
    }

    public function paraArray(): array
    {
        return [
            'tipo_operacao' => $this->tipoOperacao->value,
            'grupo' => $this->grupo,
            'parcelas' => $this->parcelas,
            'valor_mensal' => $this->valorMensal,
            'quantidade_mensal' => $this->quantidadeMensal,
        ];
    }
}

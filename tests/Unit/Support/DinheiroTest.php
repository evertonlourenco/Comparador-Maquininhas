<?php

namespace Tests\Unit\Support;

use App\Support\Dinheiro;
use PHPUnit\Framework\TestCase;

/**
 * Regra 11. Nenhum numero magico aqui: todo esperado vem ou da propria regra
 * ("1.234.567,89", "R$ 1.234,56", "2,49%") ou de uma conta escrita ao lado.
 */
class DinheiroTest extends TestCase
{
    /**
     * O caso que separa round() do PHP de Math.round() do JS. 2.675 nao existe
     * em ponto flutuante: o double mais proximo e 2,674999999999999822...
     * A regra do comparador e meio centavo para cima, nos dois idiomas.
     */
    public function test_meio_centavo_vai_para_cima(): void
    {
        $this->assertSame(2.68, Dinheiro::arredondar(2.675));
        $this->assertSame(1.01, Dinheiro::arredondar(1.005));
        $this->assertSame(0.15, Dinheiro::arredondar(0.145));
        $this->assertSame(-2.68, Dinheiro::arredondar(-2.675));
    }

    public function test_arredondamento_nao_mexe_no_que_ja_esta_no_centavo(): void
    {
        $this->assertSame(31.5, Dinheiro::arredondar(31.5));
        $this->assertSame(0.0, Dinheiro::arredondar(0.0));
        // 1234,56 x 2,49% = 30,740544
        $this->assertSame(30.74, Dinheiro::arredondar(1234.56 * 2.49 / 100));
    }

    public function test_arredondamento_com_outras_casas(): void
    {
        // Percentuais sao decimal(6,4) no banco.
        $this->assertSame(3.1416, Dinheiro::arredondar(3.14159265, 4));
        $this->assertSame(3.0, Dinheiro::arredondar(2.5, 0));
    }

    public function test_decimal_do_banco_vira_float_so_aqui(): void
    {
        // O cast decimal:4 do Eloquent entrega string, para nao perder centavo.
        $this->assertSame(3.15, Dinheiro::doBanco('3.1500'));
        $this->assertSame(0.0, Dinheiro::doBanco('0.0000'));
        // Ausente continua ausente: dado que falta nao e zero (regra 4).
        $this->assertNull(Dinheiro::doBanco(null));
        $this->assertNull(Dinheiro::doBanco(''));
    }

    public function test_entrada_em_pt_br(): void
    {
        // Regra 11: o usuario digita 10.000,00 e nao 10000.00.
        $this->assertSame(10000.0, Dinheiro::doUsuario('10.000,00'));
        $this->assertSame(1234567.89, Dinheiro::doUsuario('1.234.567,89'));
        $this->assertSame(1234.56, Dinheiro::doUsuario('R$ 1.234,56'));
        $this->assertSame(0.57, Dinheiro::doUsuario('0,57'));
        $this->assertSame(-1500.0, Dinheiro::doUsuario('-1.500,00'));

        // Sem virgula: ponto separando grupo de 3 digitos e milhar...
        $this->assertSame(10500.0, Dinheiro::doUsuario('10.500'));
        // ...e em qualquer outro caso e decimal.
        $this->assertSame(10.5, Dinheiro::doUsuario('10.5'));

        $this->assertNull(Dinheiro::doUsuario(''));
        $this->assertNull(Dinheiro::doUsuario(null));
        $this->assertNull(Dinheiro::doUsuario('R$'));
    }

    public function test_saida_em_pt_br(): void
    {
        $this->assertSame('1.234.567,89', Dinheiro::numero(1234567.89));
        $this->assertSame('R$ 1.234,56', Dinheiro::real(1234.56));
        $this->assertSame('R$ 0,00', Dinheiro::real(0.0));

        // Regra 11: sempre 2 casas. Nunca 2,5% nem 2,4900%.
        $this->assertSame('2,49%', Dinheiro::percentual(2.49));
        $this->assertSame('2,50%', Dinheiro::percentual(2.5));
        $this->assertSame('0,00%', Dinheiro::percentual(0.0));

        $this->assertSame('08/09/2026', Dinheiro::data('2026-09-08'));
        $this->assertNull(Dinheiro::data(null));
    }

    public function test_ida_e_volta_do_que_o_usuario_digita(): void
    {
        $this->assertSame('R$ 10.000,00', Dinheiro::real(Dinheiro::doUsuario('10.000,00')));
    }
}

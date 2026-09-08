<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Regra 11, em um lugar so.
 *
 * Tres responsabilidades, e a fronteira entre elas e o ponto da regra:
 *
 * 1. arredondar()  - o centavo. Deve dar exatamente o mesmo resultado que a
 *                    implementacao em JavaScript do comparador (regra 9), por
 *                    isso nao usa round() do PHP nem Math.round() do JS.
 * 2. doBanco()     - decimal (string) -> float. E aqui, e so aqui, que o valor
 *                    guardado vira ponto flutuante. Antes disto e string.
 *    doUsuario()   - o que a pessoa digitou em pt-BR -> float.
 * 3. real(), numero(), percentual(), data() - a volta para pt-BR na saida.
 *
 * O espelho em JavaScript e resources/js/comparador/dinheiro.mjs, e o teste
 * ParidadeDoMotorTest cobra que os dois concordem centavo a centavo.
 */
final class Dinheiro
{
    /**
     * Meio centavo vai para cima, e o erro binario de escalar por 100 nao
     * decide o desempate.
     *
     * Nem round() do PHP nem Math.round() do JS servem, porque discordam entre
     * si. Um valor com meio centavo nao existe exato em ponto flutuante: o
     * double mais proximo de 1,005 e 1,00499999999999989..., e multiplicar por
     * 100 da 100.49999999999999. Math.round dessa conta e 100, entao o JS
     * escreveria R$ 1,00 onde o PHP - que corrige o erro dentro do round() -
     * escreve R$ 1,01. Idem para 0,145: JS 0,14 contra PHP 0,15.
     *
     * (Nem todo meio centavo diverge, e por isso o teste de paridade nao pode
     * depender de um valor sorteado: 2,675 x 100 arredonda para 267,5 exatos,
     * e os dois idiomas concordam sozinhos em 2,68. Os casos que separam os
     * dois estao tabelados em ParidadeDoMotorTest::tabelaDeCentavos.)
     *
     * A correcao aqui e explicita e igual nos dois lados: reduz o valor
     * escalado a 15 digitos significativos - a precisao que um double IEEE-754
     * garante representar de volta sem ambiguidade - e so entao desempata.
     * 1,005 x 100 = 100.49999999999999 vira 100,5, que sobe para 101.
     */
    public static function arredondar(float $valor, int $casas = 2): float
    {
        if (! is_finite($valor)) {
            return $valor;
        }

        $fator = 10 ** $casas;
        $escalado = self::comQuinzeDigitos($valor * $fator);
        $inteiro = floor(abs($escalado) + 0.5);

        return ($escalado < 0 ? -$inteiro : $inteiro) / $fator;
    }

    /**
     * A fronteira do calculo: o decimal do banco (que o cast entrega como
     * string, para nao perder centavo) vira float aqui e em nenhum outro
     * lugar. Nulo continua nulo - dado ausente nao e zero (regra 4).
     */
    public static function doBanco(string|float|int|null $valor): ?float
    {
        return $valor === null || $valor === '' ? null : (float) $valor;
    }

    /**
     * O que a pessoa digitou, em pt-BR: "10.000,00", "R$ 1.234,56", "0,57".
     *
     * A regra de leitura, para nao depender de adivinhacao:
     * - havendo virgula, ela e o separador decimal e todo ponto e milhar;
     * - sem virgula, o ponto e milhar quando separa um grupo de exatamente 3
     *   digitos ("10.500" = dez mil e quinhentos, que e o que um brasileiro
     *   quer dizer) e decimal em qualquer outro caso ("10.5" = dez e meio).
     *
     * Nao use isto para ler valor vindo do banco: "10.000" ali e dez, nao dez
     * mil. Para isso existe doBanco().
     */
    public static function doUsuario(string|float|int|null $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_float($valor) || is_int($valor)) {
            return (float) $valor;
        }

        $limpo = preg_replace('/[^0-9,.\-]/', '', $valor) ?? '';

        if ($limpo === '' || $limpo === '-') {
            return null;
        }

        if (str_contains($limpo, ',')) {
            $limpo = str_replace('.', '', $limpo);
            $limpo = str_replace(',', '.', $limpo);

            return (float) $limpo;
        }

        $partes = explode('.', $limpo);

        if (count($partes) > 1 && strlen(end($partes)) === 3) {
            return (float) implode('', $partes);
        }

        return (float) $limpo;
    }

    /** "1.234.567,89" — milhar com ponto, decimal com virgula. */
    public static function numero(float $valor, int $casas = 2): string
    {
        return number_format(self::arredondar($valor, $casas), $casas, ',', '.');
    }

    /** "R$ 1.234,56", com espaco depois do R$. */
    public static function real(float $valor): string
    {
        return 'R$ '.self::numero($valor, 2);
    }

    /** "2,49%" — sempre duas casas, nunca "2,5%" nem "2,4900%". */
    public static function percentual(float $valor): string
    {
        return self::numero($valor, 2).'%';
    }

    /** "08/09/2026". */
    public static function data(Carbon|string|null $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }

        return ($data instanceof Carbon ? $data : Carbon::parse($data))->format('d/m/Y');
    }

    /**
     * 15 digitos significativos: o maximo que um double IEEE-754 sempre
     * representa e reconstroi sem ambiguidade. sprintf com %.15G no PHP e
     * Number.prototype.toPrecision(15) no JS sao ambos definidos como
     * arredondamento correto para essa precisao, entao os dois devolvem o
     * mesmo double.
     */
    private static function comQuinzeDigitos(float $valor): float
    {
        return (float) sprintf('%.15G', $valor);
    }
}

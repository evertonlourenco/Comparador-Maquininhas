<?php

namespace Tests\Support;

/**
 * Catalogo sintetico com numeros redondos, para que todo valor esperado nos
 * testes possa ser conferido a mao. Nao e a carga real de proposito: o motor
 * precisa ser cobrado por casos de valor conhecido, e a carga real muda quando
 * uma marca mexe na tabela.
 *
 * O mesmo array alimenta o motor em PHP e o motor em JavaScript no teste de
 * paridade - e por isso ele nao pode ter nada que so o PHP entenda.
 *
 * As cinco marcas cobrem os quatro estados da regra 4 e o enquadramento da
 * regra 3:
 *   Alfa     - tabela completa, cupom vigente, aparelho comprado. CALCULADO.
 *   Beta     - so recebe em 1 dia util e nao publica parcelado. INCOMPLETO
 *              quando o cenario pede o que ela nao tem.
 *   Gama     - nao publica tabela, tem faixa reportada. FAIXA_REPORTADA.
 *   Delta    - nao publica tabela e nao tem faixa. SEM_DADO_PUBLICADO.
 *   Epsilon  - plano de enquadramento escolhido: aparece em qualquer
 *              faturamento, porque quem decide e o lojista (regra 3).
 */
final class CatalogoDeTeste
{
    /** Data de verificacao de todas as taxas do catalogo sintetico. */
    public const VERIFICADO_EM = '2026-09-01';

    public static function montar(): array
    {
        return [
            'versao' => 1,
            'gerado_em' => '2026-09-08T09:00:00-03:00',
            'contem_rascunhos' => false,
            'dias_ate_degradar' => 45,
            'prazos' => [
                'na_hora' => ['codigo' => 'na_hora', 'nome' => 'Na hora', 'dias' => 0, 'antecipacao_embutida' => true, 'ordem' => 0],
                'd_1' => ['codigo' => 'd_1', 'nome' => 'Em 1 dia útil', 'dias' => 1, 'antecipacao_embutida' => true, 'ordem' => 1],
                'd_30' => ['codigo' => 'd_30', 'nome' => 'Em 30 dias', 'dias' => 30, 'antecipacao_embutida' => false, 'ordem' => 3],
                'parcela_a_parcela' => ['codigo' => 'parcela_a_parcela', 'nome' => 'Conforme as parcelas', 'dias' => null, 'antecipacao_embutida' => false, 'ordem' => 4],
            ],
            'grupos' => [
                'visa_master' => ['codigo' => 'visa_master', 'nome' => 'Visa e Mastercard', 'de_pix' => false, 'ordem' => 0],
                'demais' => ['codigo' => 'demais', 'nome' => 'Demais bandeiras', 'de_pix' => false, 'ordem' => 1],
                'pix' => ['codigo' => 'pix', 'nome' => 'Pix', 'de_pix' => true, 'ordem' => 3],
            ],
            'marcas' => [
                self::alfa(),
                self::beta(),
                self::gama(),
                self::delta(),
                self::epsilon(),
            ],
        ];
    }

    /** Tabela completa. Aparelho de R$ 199,00 a vista, cupom de R$ 50,00. */
    private static function alfa(): array
    {
        return [
            ...self::marca(1, 'Alfa', true),
            'cupons' => [[
                'codigo' => 'ALFA50',
                'descricao' => 'R$ 50,00 de desconto na adesão.',
                'tipo_desconto' => 'valor',
                'incide_sobre' => 'adesao',
                'valor' => 50.0,
                'valido_de' => '2026-09-01',
                'valido_ate' => '2026-09-30',
                'equipamento_id' => null,
                'link_afiliado' => 'https://alfa.test/?cupom=ALFA50',
            ]],
            'planos' => [[
                ...self::plano(10, 'Plano Único', 'automatico'),
                'conta' => self::conta(mensalidade: 0.0, saque: 5.0, ted: 2.5, pixRecebido: 0.0, pixEnviado: 1.0, antecipacao: 2.0),
                'equipamentos' => [
                    // Aparelho comprado: aluguel nulo com adesao preenchida e a
                    // marca dizendo que aluguel nao existe aqui.
                    self::equipamento(100, 'Aparelho A', adesao: 199.0, promocional: null, aluguel: null),
                ],
                'taxas' => [
                    self::taxa('debito', 'visa_master', 1, 'd_1', 1.0),
                    self::taxa('credito_avista', 'visa_master', 1, 'd_1', 3.15),
                    self::taxa('credito_avista', 'visa_master', 1, 'd_30', 2.0),
                    // A unica taxa do catalogo com valor fixo por transacao.
                    self::taxa('credito_parcelado', 'visa_master', 6, 'parcela_a_parcela', 4.0, valorFixo: 0.5),
                    self::taxa('debito', 'demais', 1, 'd_1', 2.5),
                    self::taxa('pix', 'pix', 1, 'na_hora', 0.0),
                ],
                'faixas' => [],
            ]],
        ];
    }

    /** So recebe em 1 dia util, e nao publica parcelado nem Pix. */
    private static function beta(): array
    {
        return [
            ...self::marca(2, 'Beta', true),
            'cupons' => [],
            'planos' => [[
                ...self::plano(20, 'Beta até 5 mil', 'automatico', min: null, max: 5000.0),
                'conta' => self::conta(mensalidade: 10.0),
                'equipamentos' => [
                    self::equipamento(200, 'Aparelho B', adesao: null, promocional: null, aluguel: 30.0),
                ],
                'taxas' => [
                    self::taxa('debito', 'visa_master', 1, 'd_1', 0.9),
                    self::taxa('credito_avista', 'visa_master', 1, 'd_1', 2.8),
                ],
                'faixas' => [],
            ]],
        ];
    }

    /** Regra 4, classe B: mediana, minimo e maximo, nunca numero unico. */
    private static function gama(): array
    {
        return [
            ...self::marca(3, 'Gama', false),
            'cupons' => [],
            'planos' => [[
                ...self::plano(30, 'Gama padrão', 'negociado'),
                'conta' => self::conta(mensalidade: 0.0),
                'equipamentos' => [
                    self::equipamento(300, 'Aparelho G', adesao: 0.0, promocional: null, aluguel: 40.0),
                ],
                'taxas' => [],
                'faixas' => [
                    self::faixa('debito', 'visa_master', 1, 'd_1', mediana: 1.5, minimo: 1.2, maximo: 2.1),
                    self::faixa('credito_avista', 'visa_master', 1, 'd_30', mediana: 3.5, minimo: 2.9, maximo: 4.4),
                ],
            ]],
        ];
    }

    /** Marca sem nenhum dado. Regra 4: aparece, com o motivo, e sem numero. */
    private static function delta(): array
    {
        return [...self::marca(4, 'Delta', false), 'cupons' => [], 'planos' => []];
    }

    /** Regra 3: enquadramento escolhido nao filtra por faturamento. */
    private static function epsilon(): array
    {
        return [
            ...self::marca(5, 'Epsilon', true),
            'cupons' => [],
            'planos' => [[
                ...self::plano(50, 'Epsilon Pro', 'escolhido'),
                'conta' => self::conta(mensalidade: 49.9),
                'equipamentos' => [
                    self::equipamento(500, 'Aparelho E', adesao: 120.0, promocional: 60.0, aluguel: null),
                ],
                'taxas' => [
                    self::taxa('debito', 'visa_master', 1, 'd_1', 0.5),
                    self::taxa('credito_avista', 'visa_master', 1, 'd_1', 2.0),
                ],
                'faixas' => [],
            ]],
        ];
    }

    private static function marca(int $id, string $nome, bool $publicaTabela): array
    {
        return [
            'id' => $id,
            'nome' => $nome,
            'slug' => strtolower($nome),
            'site_url' => 'https://'.strtolower($nome).'.test',
            'publica_tabela' => $publicaTabela,
            'adquirente' => ['nome' => 'Adquirente '.$nome, 'slug' => 'adq-'.strtolower($nome)],
            'reclame_aqui' => ['nota' => null, 'url' => null, 'consultado_em' => null],
        ];
    }

    private static function plano(int $id, string $nome, string $enquadramento, ?float $min = null, ?float $max = null): array
    {
        return [
            'id' => $id,
            'nome' => $nome,
            'slug' => str_replace(' ', '-', strtolower($nome)),
            'tipo_enquadramento' => $enquadramento,
            'faturamento_min' => $min,
            'faturamento_max' => $max,
            'compromisso' => null,
        ];
    }

    private static function conta(
        ?float $mensalidade = null,
        ?float $saque = null,
        ?float $ted = null,
        ?float $pixRecebido = null,
        ?float $pixEnviado = null,
        ?float $antecipacao = null,
    ): array {
        return [
            'mensalidade' => $mensalidade,
            'tarifa_saque' => $saque,
            'tarifa_ted' => $ted,
            'tarifa_pix_recebimento' => $pixRecebido,
            'tarifa_pix_envio' => $pixEnviado,
            'taxa_antecipacao_mensal' => $antecipacao,
        ];
    }

    private static function equipamento(int $id, string $nome, ?float $adesao, ?float $promocional, ?float $aluguel): array
    {
        return [
            'id' => $id,
            'nome' => $nome,
            'slug' => str_replace(' ', '-', strtolower($nome)),
            'tipo' => 'pos',
            'preco_adesao' => $adesao,
            'preco_adesao_promocional' => $promocional,
            'aluguel_mensal' => $aluguel,
        ];
    }

    private static function taxa(
        string $tipo,
        string $grupo,
        int $parcelas,
        string $prazo,
        float $percentual,
        float $valorFixo = 0.0,
    ): array {
        return [
            'tipo_operacao' => $tipo,
            'grupo' => $grupo,
            'parcelas' => $parcelas,
            'prazo' => $prazo,
            'percentual' => $percentual,
            'valor_fixo' => $valorFixo,
            'data_verificacao' => self::VERIFICADO_EM,
            'url_fonte' => 'https://exemplo.test/taxas',
        ];
    }

    private static function faixa(
        string $tipo,
        string $grupo,
        int $parcelas,
        string $prazo,
        float $mediana,
        float $minimo,
        float $maximo,
    ): array {
        return [
            'tipo_operacao' => $tipo,
            'grupo' => $grupo,
            'parcelas' => $parcelas,
            'prazo' => $prazo,
            'mediana' => $mediana,
            'minimo' => $minimo,
            'maximo' => $maximo,
            'n_relatos' => 37,
            'periodo_inicio' => '2026-06-01',
            'periodo_fim' => '2026-08-31',
            'data_verificacao' => self::VERIFICADO_EM,
            'fonte_descricao' => 'Relatos de lojistas no canal.',
        ];
    }
}

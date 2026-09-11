<?php

namespace Tests\Feature\Idioma;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O Filament embarca pt_BR; o Laravel nao.
 *
 * O sintoma apareceu ao testar o "Esqueceu sua senha?" em producao (etapa 11):
 * a mesma notificacao trazia o titulo em ingles ("We have emailed your password
 * reset link.") sobre um corpo em portugues. E o e-mail de redefinicao saia
 * inteiro em ingles, num site brasileiro.
 *
 * As traducoes vivem em lang/pt_BR/passwords.php (chaves de arquivo, usadas
 * pelo password broker) e lang/pt_BR.json (chaves-frase, que e como a
 * notificacao e o layout de e-mail do Laravel pedem as suas).
 */
class TraducoesDoLaravelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_suite_roda_em_pt_br(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function frasesDoBroker(): array
    {
        return [
            'sent' => ['passwords.sent', 'We have emailed your password reset link.'],
            'reset' => ['passwords.reset', 'Your password has been reset.'],
            'throttled' => ['passwords.throttled', 'Please wait before retrying.'],
            'token' => ['passwords.token', 'This password reset token is invalid.'],
            'user' => ['passwords.user', "We can't find a user with that email address."],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('frasesDoBroker')]
    public function test_o_password_broker_fala_portugues(string $chave, string $ingles): void
    {
        $traduzida = __($chave);

        $this->assertNotSame($ingles, $traduzida, "[$chave] ainda esta em ingles.");
        $this->assertNotSame($chave, $traduzida, "[$chave] nao tem traducao e caiu na propria chave.");
    }

    public function test_o_email_de_redefinicao_sai_em_portugues(): void
    {
        ResetPassword::createUrlUsing(fn () => 'https://maquinacerta.com.br/admin/password-reset/reset?token=x');

        $mensagem = (new ResetPassword('token-de-teste'))
            ->toMail(new \App\Models\User(['email' => 'lojista@exemplo.com']));

        $this->assertSame('Redefinição de senha', $mensagem->subject);
        $this->assertSame('Redefinir senha', $mensagem->actionText);

        $corpo = implode(' ', [...$mensagem->introLines, ...$mensagem->outroLines]);

        $this->assertStringContainsString('recebemos um pedido de redefinição', $corpo);
        $this->assertStringContainsString('expira em 60 minutos', $corpo);
        $this->assertStringNotContainsString('You are receiving', $corpo);
        $this->assertStringNotContainsString('password reset', $corpo);
    }

    /**
     * O layout de e-mail do Laravel usa @lang() para estas. Sem traducao, um
     * e-mail em portugues termina com "Regards," — que e o tipo de detalhe que
     * ninguem revisa depois que o assunto ja esta certo.
     *
     * @return array<string, array{string}>
     */
    public static function frasesDoLayout(): array
    {
        return [
            'saudacao' => ['Hello!'],
            'despedida' => ['Regards,'],
            'erro' => ['Whoops!'],
            'rodape' => ['All rights reserved.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('frasesDoLayout')]
    public function test_o_layout_do_email_fala_portugues(string $ingles): void
    {
        $this->assertNotSame(
            $ingles,
            __($ingles),
            "A frase [$ingles] do layout de e-mail do Laravel nao tem traducao em lang/pt_BR.json.",
        );
    }

    /**
     * Guarda o proprio arquivo de traducao: uma chave copiada sem traduzir
     * passaria despercebida, porque `__()` devolve a chave e nada quebra.
     */
    public function test_nenhuma_frase_do_json_ficou_por_traduzir(): void
    {
        $json = json_decode(file_get_contents(lang_path('pt_BR.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertNotEmpty($json);

        foreach ($json as $ingles => $portugues) {
            $this->assertNotSame($ingles, $portugues, "A frase [$ingles] foi copiada sem traduzir.");
            $this->assertNotSame('', trim($portugues), "A frase [$ingles] tem traducao vazia.");
        }
    }
}

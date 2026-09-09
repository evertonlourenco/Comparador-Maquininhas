<?php

namespace App\Support\Antispam;

use Illuminate\Http\Request;

/**
 * Etapa 10: sem CAPTCHA de terceiro, como pedido — honeypot mais um tempo
 * minimo de preenchimento. Os dois formularios publicos novos
 * (/enviar-proposta e "reportar taxa errada") checam isto antes de gravar
 * qualquer coisa, e respondem sucesso mesmo quando o envio parece
 * automatizado — nada e persistido, mas o bot nao aprende qual das duas
 * regras o pegou nem que foi barrado.
 */
final class FormularioProtegido
{
    /** Um humano preenchendo o formulario nao termina em menos que isto. */
    private const SEGUNDOS_MINIMOS = 3;

    public static function pareceAutomatizado(Request $request, string $campoHoneypot = 'confirmar_contato'): bool
    {
        if (filled($request->input($campoHoneypot))) {
            return true;
        }

        $carregadoEm = (int) $request->input('carregado_em', 0);

        if ($carregadoEm <= 0) {
            return true;
        }

        return (time() - $carregadoEm) < self::SEGUNDOS_MINIMOS;
    }
}

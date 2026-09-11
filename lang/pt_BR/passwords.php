<?php

/*
| Traducoes do fluxo de senha do proprio Laravel (etapa 11).
|
| O Filament embarca pt_BR e o projeto roda em APP_LOCALE=pt_BR, entao quase
| tudo do painel ja saia em portugues. Estas cinco frases nao: elas vem do
| password broker do Laravel, que so traz ingles. O sintoma apareceu ao testar
| o "Esqueceu sua senha?" em producao — titulo em ingles ("We have emailed your
| password reset link.") sobre um corpo em portugues, na mesma notificacao.
|
| As frases do CORPO do e-mail ficam em lang/pt_BR.json, porque a notificacao
| do Laravel as pede por chave-frase (Lang::get('Reset your password')) e nao
| por chave-de-arquivo.
*/

return [

    'reset' => 'Sua senha foi redefinida.',
    'sent' => 'Enviamos o link de redefinição para o seu e-mail.',
    'throttled' => 'Aguarde um momento antes de tentar de novo.',
    'token' => 'Este link de redefinição é inválido ou já expirou.',
    'user' => 'Não encontramos nenhuma conta com esse e-mail.',

];

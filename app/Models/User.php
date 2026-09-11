<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Regra 4 do Filament, e a unica que nao se ve em local: o middleware
     * `Filament\Http\Middleware\Authenticate` aborta com 403 quando o model
     * de usuario NAO implementa `FilamentUser` e `APP_ENV` nao e `local`.
     *
     *     abort_if(
     *         $user instanceof FilamentUser
     *             ? (! $user->canAccessPanel($panel))
     *             : (config('app.env') !== 'local'),
     *         403,
     *     );
     *
     * Por isso o painel funcionou das etapas 03 a 10 e recusou o login no
     * primeiro acesso em producao (etapa 11). Os testes tambem nao pegavam:
     * eles usam `Livewire::test($pagina)`, que instancia o componente direto e
     * nao passa pela pilha de middlewares HTTP. O teste de regressao que pega
     * isso e uma requisicao HTTP de verdade — ver
     * `tests/Feature/Admin/AcessoAoPainelTest.php`.
     *
     * A porta aqui e "existir na tabela `users`", e isso e suficiente **hoje**
     * porque nao ha rota publica de cadastro: usuario so nasce por
     * `php artisan make:filament-user`, a mao. O 2FA obrigatorio
     * (`AdminPanelProvider::multiFactorAuthentication(..., isRequired: true)`)
     * e a segunda tranca.
     *
     * ATENCAO para a etapa 15 (programa de parceiros): se o projeto ganhar
     * cadastro publico, todo cadastrado passa a entrar no /admin. Nesse dia
     * este metodo precisa de um criterio de verdade — coluna, papel ou lista.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}

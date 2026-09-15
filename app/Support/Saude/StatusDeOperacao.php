<?php

namespace App\Support\Saude;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Etapa 16, prioridade 3: as seis contas do cartão "Operação" do painel de
 * saúde. Cada método devolve `sem_dado: true` com o motivo quando a fonte
 * não existe ou não responde — nunca um zero ou um "ok" inventado. A
 * widget (App\Filament\Widgets\OperacaoWidget) só decide cor e ícone; a
 * conta mora aqui para poder ser testada sem montar um componente Livewire.
 */
class StatusDeOperacao
{
    /**
     * @return array{sem_dado: bool, motivo: ?string, status: ?string, quando: ?Carbon, detalhe: ?string}
     */
    public static function ultimoBackup(): array
    {
        $caminho = config('saude.backup_log_path');

        if (blank($caminho)) {
            return self::semDadoBackup('BACKUP_LOG_PATH não configurado no .env');
        }

        if (! is_readable($caminho)) {
            return self::semDadoBackup("arquivo não encontrado ou sem permissão de leitura: {$caminho}");
        }

        $linhas = collect(file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);

        if ($linhas->isEmpty()) {
            return self::semDadoBackup('log vazio — o backup ainda não rodou nenhuma vez');
        }

        $indiceUltimoInicio = $linhas->keys()->last(fn (int $i) => str_contains($linhas[$i], '--- inicio'));

        if ($indiceUltimoInicio === null) {
            return self::semDadoBackup('log não tem o formato esperado (sem linha "--- inicio")');
        }

        $bloco = $linhas->slice($indiceUltimoInicio)->values();
        $primeira = $bloco->first();
        $ultima = $bloco->last();

        $quando = self::extrairCarimbo($primeira);
        $tamanho = $bloco->first(fn (string $linha) => str_contains($linha, 'banco:'));
        $detalhe = $tamanho ? trim(str($tamanho)->after('banco:')->toString()) : null;

        $status = match (true) {
            str_contains($ultima, '--- fim, ok ---') => 'ok',
            str_contains($ultima, 'FALHOU') => 'falha',
            default => 'incompleto',
        };

        $motivo = $status === 'falha' ? trim(str($ultima)->after('FALHOU:')->toString()) : null;

        return [
            'sem_dado' => false,
            'motivo' => $motivo,
            'status' => $status,
            'quando' => $quando,
            'detalhe' => $detalhe,
        ];
    }

    private static function semDadoBackup(string $motivo): array
    {
        return ['sem_dado' => true, 'motivo' => $motivo, 'status' => null, 'quando' => null, 'detalhe' => null];
    }

    private static function extrairCarimbo(string $linha): ?Carbon
    {
        if (! preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $linha, $m)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $m[1]);
    }

    /** @return array{pendentes: int, falhos: int} */
    public static function jobsNaFila(): array
    {
        return [
            'pendentes' => DB::table('jobs')->count(),
            'falhos' => DB::table('failed_jobs')->count(),
        ];
    }

    /**
     * @return array{sem_dado: bool, motivo: ?string, dias_restantes: ?int, valido_ate: ?Carbon}
     */
    public static function certificadoSsl(): array
    {
        return Cache::remember('saude:certificado-ssl', now()->addHours(6), function (): array {
            $host = config('saude.dominio_ssl');

            if (blank($host)) {
                return ['sem_dado' => true, 'motivo' => 'domínio não configurado (APP_URL ou SAUDE_DOMINIO_SSL)', 'dias_restantes' => null, 'valido_ate' => null];
            }

            $contexto = stream_context_create([
                'ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $conexao = @stream_socket_client(
                "ssl://{$host}:443",
                $codigoErro,
                $mensagemErro,
                5,
                STREAM_CLIENT_CONNECT,
                $contexto,
            );

            if ($conexao === false) {
                return ['sem_dado' => true, 'motivo' => $mensagemErro ?: 'conexão TLS falhou', 'dias_restantes' => null, 'valido_ate' => null];
            }

            try {
                $parametros = stream_context_get_params($conexao);
                $certificado = openssl_x509_parse($parametros['options']['ssl']['peer_certificate']);

                if (! $certificado || ! isset($certificado['validTo_time_t'])) {
                    return ['sem_dado' => true, 'motivo' => 'não consegui ler o certificado', 'dias_restantes' => null, 'valido_ate' => null];
                }

                $validoAte = Carbon::createFromTimestamp($certificado['validTo_time_t']);

                return [
                    'sem_dado' => false,
                    'motivo' => null,
                    'dias_restantes' => (int) Carbon::now()->diffInDays($validoAte, false),
                    'valido_ate' => $validoAte,
                ];
            } catch (Throwable $e) {
                return ['sem_dado' => true, 'motivo' => str($e->getMessage())->limit(150)->toString(), 'dias_restantes' => null, 'valido_ate' => null];
            } finally {
                fclose($conexao);
            }
        });
    }

    /** @return array{sem_dado: bool, octal: ?string, seguro: bool} */
    public static function permissaoEnv(): array
    {
        $caminho = base_path('.env');

        if (! is_readable($caminho)) {
            return ['sem_dado' => true, 'octal' => null, 'seguro' => false];
        }

        $octal = substr(sprintf('%o', fileperms($caminho)), -3);

        // Regra do próprio backup (scripts/backup-comparador.sh): 600, dono
        // só. Qualquer bit de grupo ou de outros é permissão frouxa demais
        // para um arquivo que carrega a APP_KEY. octdec() é necessário aqui:
        // "600" como string decimal não é o mesmo número que 0600 octal.
        $seguro = (octdec($octal) & 0077) === 0;

        return ['sem_dado' => false, 'octal' => $octal, 'seguro' => $seguro];
    }

    public static function usuariosSemDoisFatores(): int
    {
        return User::query()->whereNull('app_authentication_secret')->count();
    }
}

<?php

namespace App\Support\Uploads;

use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Upload de imagem (logo de marca, foto de equipamento) com duas garantias:
 *
 * 1. O tipo do arquivo e verificado pelos bytes reais (finfo), nunca pela
 *    extensao informada pelo navegador - um .jpg com magic bytes de outra
 *    coisa e rejeitado.
 * 2. Toda imagem aceita e convertida para WebP antes de ir para o disco,
 *    independente do formato original.
 *
 * salvar() recebe o caminho absoluto do arquivo, nao o objeto de upload do
 * Livewire (etapa 10) — assim App\Support\Uploads\AnexoDeProposta, que lida
 * com upload HTTP comum fora do Filament, reaproveita o mesmo metodo em vez
 * de duplicar a conversao para WebP.
 */
final class ImagemSeguraWebp
{
    /** @var array<string, string> Mime real => funcao GD que le esse formato. */
    private const CRIADORES = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/gif' => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
    ];

    public static function tipoReal(string $caminhoAbsoluto): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $tipo = $finfo ? (finfo_file($finfo, $caminhoAbsoluto) ?: null) : null;

        if ($finfo) {
            finfo_close($finfo);
        }

        return $tipo;
    }

    /** Regra de validacao do form: falha se os bytes do arquivo nao forem de uma imagem aceita. */
    public static function regraDeValidacao(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof TemporaryUploadedFile) {
                return;
            }

            $tipo = self::tipoReal($value->getRealPath());

            if (! isset(self::CRIADORES[$tipo])) {
                $fail('O arquivo enviado não é uma imagem válida (JPEG, PNG, GIF ou WebP).');
            }
        };
    }

    /** Se o mime real informado e um dos formatos de imagem aceitos. */
    public static function ehImagemAceita(?string $tipo): bool
    {
        return isset(self::CRIADORES[$tipo]);
    }

    /**
     * Converte o arquivo no caminho informado para WebP e grava no disco.
     * Retorna o caminho relativo gravado, ou null se o tipo real nao for aceito.
     */
    public static function salvar(string $caminhoAbsoluto, string $diretorio, string $disco = 'public'): ?string
    {
        $tipo = self::tipoReal($caminhoAbsoluto);
        $criador = self::CRIADORES[$tipo] ?? null;

        if ($criador === null) {
            return null;
        }

        $imagem = @$criador($caminhoAbsoluto);

        if ($imagem === false) {
            return null;
        }

        imagepalettetotruecolor($imagem);
        imagealphablending($imagem, true);
        imagesavealpha($imagem, true);

        $caminhoTemporario = tempnam(sys_get_temp_dir(), 'webp_');
        imagewebp($imagem, $caminhoTemporario, 82);
        imagedestroy($imagem);

        $caminhoRelativo = trim($diretorio, '/').'/'.Str::ulid().'.webp';
        Storage::disk($disco)->put($caminhoRelativo, file_get_contents($caminhoTemporario));
        unlink($caminhoTemporario);

        return $caminhoRelativo;
    }
}

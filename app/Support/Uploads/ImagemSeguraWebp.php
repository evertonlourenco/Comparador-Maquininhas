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

    /** Mesma checagem de tipoReal(), mas sobre bytes em memoria — usada pela busca de imagem externa (etapa 15), que nunca grava o candidato em disco antes da aprovacao. */
    public static function tipoRealDosBytes(string $bytes): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $tipo = $finfo ? (finfo_buffer($finfo, $bytes) ?: null) : null;

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
        $bytesOriginais = file_get_contents($caminhoAbsoluto);
        $bytesWebp = $bytesOriginais === false ? null : self::converterParaWebp($bytesOriginais);

        return $bytesWebp === null ? null : self::gravar($bytesWebp, $diretorio, $disco);
    }

    /**
     * Converte bytes de imagem (JPEG, PNG, GIF ou WebP, verificados pelo
     * conteudo real) para bytes WebP, sem tocar em disco. E a peca que a
     * busca de imagem externa (etapa 15) usa para converter o candidato
     * antes de mostrar a pre-visualizacao — a aprovacao humana acontece
     * antes de qualquer gravacao, nunca depois.
     */
    public static function converterParaWebp(string $bytesOriginais): ?string
    {
        $tipo = self::tipoRealDosBytes($bytesOriginais);

        if (! self::ehImagemAceita($tipo)) {
            return null;
        }

        $imagem = @imagecreatefromstring($bytesOriginais);

        if ($imagem === false) {
            return null;
        }

        imagepalettetotruecolor($imagem);
        imagealphablending($imagem, true);
        imagesavealpha($imagem, true);

        ob_start();
        imagewebp($imagem, quality: 82);
        $bytesWebp = ob_get_clean();
        imagedestroy($imagem);

        return $bytesWebp === false || $bytesWebp === '' ? null : $bytesWebp;
    }

    /**
     * Grava bytes ja em WebP no disco, com nome novo. Reverifica o tipo por
     * conta propria — defesa em profundidade contra um campo oculto
     * adulterado entre a busca e a aprovacao (etapa 15).
     */
    public static function gravar(string $bytesWebp, string $diretorio, string $disco = 'public'): ?string
    {
        if (self::tipoRealDosBytes($bytesWebp) !== 'image/webp') {
            return null;
        }

        $caminhoRelativo = trim($diretorio, '/').'/'.Str::ulid().'.webp';
        Storage::disk($disco)->put($caminhoRelativo, $bytesWebp);

        return $caminhoRelativo;
    }
}

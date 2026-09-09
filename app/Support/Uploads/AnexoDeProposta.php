<?php

namespace App\Support\Uploads;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Etapa 10: o anexo de /enviar-proposta — foto ou PDF da tabela que o
 * lojista recebeu. Mesma garantia de ImagemSeguraWebp (tipo pelos bytes
 * reais, nunca pela extensao), mais o caso do PDF, que essa classe nao
 * cobre. Disco 'local' (privado): e documento comercial do lojista, sem
 * motivo para ganhar URL publica.
 */
final class AnexoDeProposta
{
    public static function regraDeValidacao(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $tipo = ImagemSeguraWebp::tipoReal($value->getRealPath());

            if (! ImagemSeguraWebp::ehImagemAceita($tipo) && $tipo !== 'application/pdf') {
                $fail('O anexo precisa ser uma imagem (JPEG, PNG, GIF ou WebP) ou um PDF.');
            }
        };
    }

    /**
     * @return array{caminho: string, mime: string}|null null quando o tipo
     * real nao e nenhum dos aceitos.
     */
    public static function salvar(UploadedFile $file, string $diretorio, string $disco = 'local'): ?array
    {
        $tipo = ImagemSeguraWebp::tipoReal($file->getRealPath());

        if (ImagemSeguraWebp::ehImagemAceita($tipo)) {
            $caminho = ImagemSeguraWebp::salvar($file->getRealPath(), $diretorio, $disco);

            return $caminho ? ['caminho' => $caminho, 'mime' => 'image/webp'] : null;
        }

        if ($tipo === 'application/pdf') {
            $caminhoRelativo = trim($diretorio, '/').'/'.Str::ulid().'.pdf';
            Storage::disk($disco)->putFileAs($diretorio, $file, basename($caminhoRelativo));

            return ['caminho' => $caminhoRelativo, 'mime' => 'application/pdf'];
        }

        return null;
    }
}

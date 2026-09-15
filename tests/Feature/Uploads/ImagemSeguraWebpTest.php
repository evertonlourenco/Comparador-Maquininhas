<?php

namespace Tests\Feature\Uploads;

use App\Support\Uploads\ImagemSeguraWebp;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Etapa 15: a busca de imagem por URL reusa ImagemSeguraWebp para converter
 * o candidato antes de mostrar a pre-visualizacao (converterParaWebp) e para
 * gravar so depois da aprovacao (gravar) — os dois metodos que a etapa 10
 * (upload manual no Filament) ja usava por outro caminho (salvar()).
 */
class ImagemSeguraWebpTest extends TestCase
{
    private function pngValido(): string
    {
        $imagem = imagecreatetruecolor(4, 4);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 10, 20, 30));
        ob_start();
        imagepng($imagem);
        $bytes = ob_get_clean();
        imagedestroy($imagem);

        return $bytes;
    }

    public function test_bytes_de_imagem_valida_convertem_para_webp(): void
    {
        $webp = ImagemSeguraWebp::converterParaWebp($this->pngValido());

        $this->assertNotNull($webp);
        $this->assertSame('image/webp', ImagemSeguraWebp::tipoRealDosBytes($webp));
    }

    public function test_bytes_que_nao_sao_de_imagem_nenhuma_nao_convertem(): void
    {
        $this->assertNull(ImagemSeguraWebp::converterParaWebp('isto não é uma imagem, é texto puro'));
        $this->assertNull(ImagemSeguraWebp::converterParaWebp('<?php echo "um script disfarçado de imagem"; ?>'));
    }

    public function test_um_pdf_nao_converte_para_webp(): void
    {
        // %PDF- e o magic byte de um PDF de verdade — o mesmo tipo de
        // conteudo que um .jpg malicioso poderia carregar.
        $this->assertNull(ImagemSeguraWebp::converterParaWebp("%PDF-1.4\n%âãÏÓ\n"));
    }

    public function test_gravar_grava_bytes_webp_validos_no_disco_e_devolve_o_caminho(): void
    {
        Storage::fake('public');

        $webp = ImagemSeguraWebp::converterParaWebp($this->pngValido());
        $caminho = ImagemSeguraWebp::gravar($webp, 'marcas/logos');

        $this->assertNotNull($caminho);
        $this->assertStringStartsWith('marcas/logos/', $caminho);
        $this->assertStringEndsWith('.webp', $caminho);
        Storage::disk('public')->assertExists($caminho);
    }

    public function test_gravar_recusa_bytes_que_nao_sao_webp_de_verdade(): void
    {
        // Defesa em profundidade: mesmo que o campo oculto entre a busca e a
        // aprovacao (etapa 15) seja adulterado, gravar() reconfere o tipo
        // pelos bytes antes de escrever no disco.
        Storage::fake('public');

        $caminho = ImagemSeguraWebp::gravar('não é webp de jeito nenhum', 'marcas/logos');

        $this->assertNull($caminho);
        Storage::disk('public')->assertDirectoryEmpty('marcas/logos');
    }

    public function test_salvar_continua_funcionando_a_partir_de_um_arquivo_em_disco(): void
    {
        // Regressao do refactor da etapa 15: o upload manual do Filament
        // (MarcaForm, EquipamentoForm, BandeiraForm) chama salvar() com o
        // caminho de um arquivo temporario, nao com bytes em memoria.
        Storage::fake('public');

        $caminhoTemporario = tempnam(sys_get_temp_dir(), 'teste_png_');
        file_put_contents($caminhoTemporario, $this->pngValido());

        $caminho = ImagemSeguraWebp::salvar($caminhoTemporario, 'bandeiras');
        unlink($caminhoTemporario);

        $this->assertNotNull($caminho);
        Storage::disk('public')->assertExists($caminho);
        $this->assertSame(
            'image/webp',
            ImagemSeguraWebp::tipoRealDosBytes(Storage::disk('public')->get($caminho)),
        );
    }
}

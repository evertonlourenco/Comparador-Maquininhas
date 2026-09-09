<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 08: espaço para embutir um vídeo do canal na página de cada marca.
 * Guarda só o ID do vídeo do YouTube — não a URL inteira —, que é o que o
 * player embutido pede. Campo vazio é o estado normal até alguém gravar o
 * vídeo daquela marca (regra 6, mesmo espírito): sem ID, a seção some
 * sozinha em vez de mostrar um espaço em branco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->string('youtube_video_id', 20)->nullable()->after('descricao');
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn('youtube_video_id');
        });
    }
};

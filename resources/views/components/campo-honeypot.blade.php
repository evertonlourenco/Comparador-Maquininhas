{{--
    Etapa 10: par de campos ocultos que App\Support\Antispam\FormularioProtegido
    confere no servidor. sr-only + tabindex="-1" + autocomplete="off", nunca
    display:none — um leitor de tela nao anuncia isto (fora do fluxo visual
    e da ordem de tabulacao), e um bot que ignora CSS ainda o preenche.
--}}
<div class="sr-only" aria-hidden="true">
    <label for="confirmar_contato">Deixe este campo em branco</label>
    <input type="text" id="confirmar_contato" name="confirmar_contato" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="carregado_em" value="{{ now()->timestamp }}">

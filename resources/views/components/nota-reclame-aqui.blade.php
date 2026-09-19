{{-- A nota do Reclame Aqui do cartao de resultado, na mesma linha do "Link de
     parceiro" ou da adesao. Vive dentro de um <template x-for/x-if> que ja
     expoe `item` — por isso so funciona dentro do resultado-comparado. --}}
<span x-show="notaRaTexto(item)" x-cloak>
    <span aria-hidden="true">·</span>
    <a x-show="item.marca.reclame_aqui?.url" :href="item.marca.reclame_aqui?.url" target="_blank" rel="noopener noreferrer nofollow" class="numero underline underline-offset-2 hover:no-underline" x-text="notaRaTexto(item)"></a>
    <span x-show="! item.marca.reclame_aqui?.url" class="numero" x-text="notaRaTexto(item)"></span>
</span>

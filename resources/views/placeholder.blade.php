<picture
    class="object-center object-cover flex {{ $class }}"
>
    <source
        x-ref="webp"
        src="{{ $srcWebp }}"
        srcset="{{ $srcsetWebp }}"
        type="image/webp"
        {!! $attributeString !!}

    />

    <source
        x-ref="jpg"
        src="{{ $src }}"
        srcset="{{ $srcset }}"
        type="image/jpeg"
        {!! $attributeString !!}

    />

    <img
        onload="if(!(width=this.getBoundingClientRect().width))return;this.onload=null;this.sizes=Math.ceil(width/window.innerWidth*100)+'vw';"
        x-ref="img"
        src="{{ $src }}"
        srcset="{{ $srcset }}"
        class="object-center object-cover w-full"
        {!! $attributeString !!}
    />
</picture>

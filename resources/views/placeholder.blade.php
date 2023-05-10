<picture
    class="object-center object-cover flex {{ $position }}"
    width="{{ $width }}"
    height="{{ $height }}"
>
    <source
        x-ref="webp"
        src="{{ $srcWebp }}"
        width="{{ $width }}"
        height="{{ $height }}"
        srcset="{{ $srcsetWebp }}"
        class="object-center object-cover"
        type="image/webp"
        {!! $attributeString !!}
    >

    <img
        x-ref="img"
        src="{{ $src }}"
        srcset="{{ $srcset }}"
        width="{{ $width }}"
        height="{{ $height }}"
        class="object-center object-cover"
        {!! $attributeString !!}
    />
</picture>

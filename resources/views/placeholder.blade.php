@php
$placeholderSeed = ($blurHash ?? '').'|'.($src ?? '').'|'.($srcset ?? '').'|'.$width.'x'.$height;
$placeholderHash = substr(md5($placeholderSeed), 0, 13);
$canvasId = 'blurhash-' . $placeholderHash;
$imgId = 'img-' . $placeholderHash;
// A reserved box: cropped to cover (fit) or letterboxed (contain). The
// responsive layout sizes itself from the image's width/height attributes,
// which is box enough for the placeholder to paint behind it.
$isContained = $isContained ?? false;
$hasBox = $isBoxed || $isContained;
$canvasWidth = $canvasWidth ?? ($isBoxed ? $width : null);
$canvasHeight = $canvasHeight ?? ($isBoxed && $height !== 'null' ? $height : null);
$showBlurHash = $blurHash && $canvasWidth && $canvasHeight;
@endphp

<div class="relative w-full overflow-hidden image-resizer-container"
     style="--min-height: 0px; --lqip-color: {{ $lqipColor ?? '#f0f0f0' }};{{ $hasBox ? ' aspect-ratio: '.$width.' / '.$height.';' : '' }}"
     data-image-container>
    @if($showBlurHash)
        <!-- BlurHash Background -->
        <canvas 
            id="{{ $canvasId }}"
            width="{{ $canvasWidth }}" 
            height="{{ $canvasHeight }}"
            class="absolute inset-0 w-full h-full blurhash-canvas"
            data-blurhash="{{ $blurHash }}">
        </canvas>
    @elseif($hasBox)
        <!-- Fallback LQIP color background -->
        <div class="absolute inset-0 w-full h-full image-resizer-blurhash-bg" 
             id="{{ $canvasId }}"></div>
    @endif
    
    <!-- Main Image - On Top -->
    <picture class="{{ $hasBox ? 'absolute inset-0 w-full h-full' : 'image-resizer-picture-responsive' }}">
        @if($srcsetWebp)
            <source srcset="{{ $srcsetWebp }}" sizes="{{ $sizes }}" type="image/webp">
        @endif
        {{-- The image's own srcset already lists the fallback-format candidates;
             a second <source> for them only repeated the URLs. Load and error
             handling (placeholder removal, blank-on-error) lives in the package
             script, delegated, so no image carries a handler of its own. --}}
        <img
            id="{{ $imgId }}"
            src="{{ $src }}"
            @if($srcset)
                srcset="{{ $srcset }}"
            @endif
            class="{{ $class }} {{ $isBoxed ? 'w-full h-full object-cover' : ($isContained ? 'w-full h-full object-contain' : 'image-resizer-img-responsive') }}"
            {!! $attributeString !!}
        />
    </picture>
</div>

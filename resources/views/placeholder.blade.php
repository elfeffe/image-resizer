@php
$placeholderSeed = ($blurHash ?? '').'|'.($src ?? '').'|'.($srcset ?? '').'|'.$width.'x'.$height;
$placeholderHash = substr(md5($placeholderSeed), 0, 13);
$canvasId = 'blurhash-' . $placeholderHash;
$imgId = 'img-' . $placeholderHash;
@endphp

<div class="relative w-full h-full overflow-hidden image-resizer-container" 
     style="--min-height: {{ $height }}px; --lqip-color: {{ $lqipColor ?? '#f0f0f0' }};"
     data-image-container>
    @if($blurHash && $height !== 'null' && $height)
        <!-- BlurHash Background -->
        <canvas 
            id="{{ $canvasId }}"
            width="{{ $width }}" 
            height="{{ $height }}"
            class="absolute inset-0 w-full h-full blurhash-canvas"
            data-blurhash="{{ $blurHash }}">
        </canvas>
    @else
        <!-- Fallback LQIP color background -->
        <div class="absolute inset-0 w-full h-full image-resizer-blurhash-bg" 
             id="{{ $canvasId }}"></div>
    @endif
    
    <!-- Main Image - On Top -->
    <picture class="absolute inset-0 w-full h-full">
        <source srcset="{{ $srcsetWebp }}" type="image/webp">
        <source srcset="{{ $srcset }}" type="image/jpeg">
        <img
            id="{{ $imgId }}"
            src="{{ $src }}"
            srcset="{{ $srcset }}"
            class="{{ $class }} w-full h-full object-cover"
            onload="document.getElementById('{{ $canvasId }}')?.remove()"
            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'{{ $width }}\' height=\'{{ $height }}\'%3E%3Crect width=\'100%25\' height=\'100%25\' fill=\'{{ $lqipColor ?? "#f0f0f0" }}\'/%3E%3C/svg%3E';"
            {!! $attributeString !!}
        />
    </picture>
</div>

@if($blurHash && $height !== 'null' && $height)
<script>
// BlurHash rendering - immediate execution
(function() {
    const canvas = document.getElementById('{{ $canvasId }}');
    const blurHash = '{{ $blurHash }}';
    if (!canvas || !blurHash) return;
    
    // Use requestAnimationFrame for better performance
    requestAnimationFrame(() => {
        try {
            // BlurHash decoder (optimized for performance)
            const chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:=?@[]^_{|}~";
            const decode83 = str => { let v = 0; for (let i = 0; i < str.length; i++) v = v * 83 + chars.indexOf(str[i]); return v; };
            const sRGBToLinear = v => { v /= 255; return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
            const linearTosRGB = v => { v = Math.max(0, Math.min(1, v)); return v <= 0.0031308 ? Math.trunc(v * 12.92 * 255 + 0.5) : Math.trunc((1.055 * Math.pow(v, 1 / 2.4) - 0.055) * 255 + 0.5); };
            const signPow = (val, exp) => Math.sign(val) * Math.pow(Math.abs(val), exp);
            
            const sizeFlag = decode83(blurHash[0]);
            const numY = Math.floor(sizeFlag / 9) + 1, numX = (sizeFlag % 9) + 1;
            const quantizedMaximumValue = decode83(blurHash[1]);
            const maximumValue = (quantizedMaximumValue + 1) / 166;
            const colors = new Array(numX * numY);
            
            // Decode colors
            for (let i = 0; i < colors.length; i++) {
                if (i === 0) {
                    const value = decode83(blurHash.substring(2, 6));
                    colors[i] = [(value >> 16) & 255, (value >> 8) & 255, value & 255].map(sRGBToLinear);
                } else {
                    const value = decode83(blurHash.substring(4 + i * 2, 6 + i * 2));
                    const quantR = Math.floor(value / (19 * 19)), quantG = Math.floor(value / 19) % 19, quantB = value % 19;
                    colors[i] = [signPow((quantR - 9) / 9, 2.0) * maximumValue, signPow((quantG - 9) / 9, 2.0) * maximumValue, signPow((quantB - 9) / 9, 2.0) * maximumValue];
                }
            }
            
            // Render at optimal resolution for performance
            const renderWidth = Math.min({{ $width }}, 100);
            const renderHeight = Math.min({{ $height }}, 100);
            
            const pixels = new Uint8ClampedArray(renderWidth * renderHeight * 4);
            
            // Generate blur pattern
            for (let y = 0; y < renderHeight; y++) {
                for (let x = 0; x < renderWidth; x++) {
                    let r = 0, g = 0, b = 0;
                    for (let j = 0; j < numY; j++) {
                        const basisY = Math.cos((Math.PI * y * j) / renderHeight);
                        for (let i = 0; i < numX; i++) {
                            const basis = Math.cos((Math.PI * x * i) / renderWidth) * basisY;
                            const color = colors[i + j * numX];
                            r += color[0] * basis; g += color[1] * basis; b += color[2] * basis;
                        }
                    }
                    const pixelIndex = (y * renderWidth + x) * 4;
                    pixels[pixelIndex] = linearTosRGB(r);
                    pixels[pixelIndex + 1] = linearTosRGB(g);
                    pixels[pixelIndex + 2] = linearTosRGB(b);
                    pixels[pixelIndex + 3] = 255;
                }
            }
            
            // Render to canvas and scale
            canvas.width = renderWidth; 
            canvas.height = renderHeight;
            const ctx = canvas.getContext('2d');
            ctx.putImageData(new ImageData(pixels, renderWidth, renderHeight), 0, 0);
            
            // Scale canvas to final size using CSS for better performance
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.imageRendering = 'auto';
            
        } catch (e) {
            // Silent fail - BlurHash stays as background color
        }
    });
})();
</script>
@endif




<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Traits;

use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;

trait HasImageResizer
{
    private $responsiveSizes = [
        1000,
        836,
        700,
        585,
        489,
        409,
        342,
        32,
    ];

    public static function getFriendly($width, $height = 'null', $type = 'resize', $media = null, $name = null, $mimeConvert = null): ?string
    {
        $class = new self;

        return $class->getFriendlyImageUrl($width, $height, $type, $media, $name, $mimeConvert);
    }

    public function getThumbnailMedia($collection = 'default')
    {
        return blink()->once('getThumbnailMedia_'.$collection.$this->id, function () use ($collection) {
            return $this->getFinalMedia($collection)->first();
        });
    }

    public function getFinalMedia($collection = 'default')
    {
        return $this->getMedia($collection);
    }

    public function getFriendlyImageUrl($width, $height = 'null', $type = 'resize', $media = null, $name = null, $mimeConvert = null): ?string
    {
        $name = Str::slug($name ?: ($this->name ?? ''), '_') ?: 'img';

        if (! $media) {
            $media = $this->getThumbnailMedia();
        }

        if (! $media) {
            return null;
        }

        if (! $mimeConvert) {
            $mimeConvert = $media['mime_type'];
        }

        $ext = match ($mimeConvert) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $relative = '/image_resizer/'.$media->id.'/w/'.$width.'/h/'.$height.'/'.$type.'/'.$name.'.'.$ext;

        $serveUrl = (string) config('image-resizer.serve.url', '');

        if ($serveUrl !== '' && (string) config('image-resizer.serve.mode', 'cdn_origin') === 'cdn_origin') {
            return rtrim($serveUrl, '/').$relative;
        }

        return $relative;
    }

    public function getMediaUrl($media, $width, $height = null, $type = 'resize', $name = null, $mimeConvert = null): ?string
    {
        if ($height === null) {
            $height = 'null';
        }

        if ($type === null) {
            $type = 'null';
        }

        return $this->getFriendlyImageUrl($width, $height, $type, $media, $name, $mimeConvert);
    }

    /**
     * Normalize mime types to supported formats
     */
    private function normalizeMimeType(?string $mimeType): ?string
    {
        if (! $mimeType) {
            return null;
        }

        // Convert common variations to standard mime types
        return match (strtolower($mimeType)) {
            'image/jpg', 'image/jpeg', 'image/pjpeg' => 'image/jpeg',
            'image/png' => 'image/png',
            'image/webp' => 'image/webp',
            default => null, // Unsupported format
        };
    }

    /**
     * `$type` is the server operation — `resize` scales down keeping the
     * ratio, `fit` crops to cover the box — plus one layout mode of its own:
     * `contain` asks the server for `resize` within the box and lays the file
     * out letterboxed inside a box of exactly that ratio (product cut-outs,
     * logos: nothing may be cropped, the box must hold still while loading).
     */
    public function getMediaHtml($media, $width, $height, $type, $extraAttributes = [], $name = 'image', $class = null, $extraClass = null)
    {
        // Handle null media gracefully
        if (! $media) {
            return '<div class="bg-gray-200 flex items-center justify-center text-gray-500 text-sm" style="width: '.$width.'px; height: '.($height ?: $width).'px;">No image</div>';
        }

        $isContained = $type === 'contain';

        if ($isContained) {
            $type = 'resize';
        }

        if (! $class) {
            $class = 'justify-center items-center ';
        }

        $class .= ' '.$extraClass;

        $isResponsive = ! is_numeric($height) || (int) $height <= 0;

        if ($isResponsive) {
            $height = 'null';
        } else {
            $height = (int) $height;
        }

        if (! $type) {
            $type = 'null';
        }

        // Only the 'fit' type crops the source to the requested box (cover).
        // 'resize' scales down preserving the source ratio, so the rendered
        // file does not match the requested box and must size itself —
        // unless the caller asked for `contain`, which reserves the box.
        $isBoxed = ! $isResponsive && $type === 'fit';
        $isContained = $isContained && ! $isResponsive;

        // A null/empty/zero width builds an invalid "/image_resizer/{id}/w//..."
        // URL that 404s (e.g. plain image blocks rendered without an explicit
        // width). Default to a sensible content width so the resizer always
        // produces a valid, responsive URL, mirroring explicit-width callers.
        if (! is_numeric($width) || (int) $width <= 0) {
            $width = 1200;
        }

        $originalWidth = (int) $width;
        $originalHeight = $height;
        [$srcset, $srcsetWebp] = $this->getImageResizerResponsiveSrcsets(
            $media,
            $originalWidth,
            $originalHeight,
            $type,
            $name,
        );

        // Use full-size image for immediate loading instead of 32px placeholder
        $src = $this->getFriendlyImageUrl($originalWidth, $originalHeight, $type, $media, $name);
        if (! $src) {
            $src = $media->getUrl();
        }

        if (! $isResponsive && ($originalHeight === 'null' || ! $originalHeight)) {
            $originalHeight = $originalWidth;
        }

        [$renderedWidth, $renderedHeight] = $this->getImageResizerRenderedDimensions(
            $media,
            $originalWidth,
            $originalHeight,
            $type,
        );

        $attributes = array_filter([
            'loading' => 'lazy',
            'decoding' => 'async',
            'sizes' => '100vw',
            'width' => $renderedWidth,
            'height' => $renderedHeight,
        ], fn (mixed $value): bool => $value !== null);

        $attributeString = (string) new ComponentAttributeBag(array_merge($attributes, $extraAttributes));

        // Get LQIP color from media custom properties
        $lqipColor = '#f0f0f0'; // Default neutral color

        if ($media && $media->hasCustomProperty('lqip_color')) {
            $customLqipColor = $media->getCustomProperty('lqip_color');
            // Validate that it's a proper hex color
            if ($customLqipColor && preg_match('/^#[a-fA-F0-9]{6}$/', $customLqipColor)) {
                $lqipColor = $customLqipColor;
            }
        }

        // Get BlurHash from media custom properties
        $blurHash = null;

        if ($media && $media->hasCustomProperty('blurhash')) {
            $customBlurHash = $media->getCustomProperty('blurhash');
            // Basic validation for BlurHash format (should be a non-empty string)
            if ($customBlurHash && is_string($customBlurHash) && strlen($customBlurHash) > 0) {
                $blurHash = $customBlurHash;
            }
        }

        // The placeholder canvas needs a box to paint: the requested one when
        // the layout reserves it, else the size the file will render at.
        $canvasWidth = ($isBoxed || $isContained) ? $originalWidth : $renderedWidth;
        $canvasHeight = ($isBoxed || $isContained) ? $originalHeight : $renderedHeight;

        return view('resizer::placeholder', [
            'attributeString' => $attributeString,
            'canvasWidth' => is_numeric($canvasWidth) ? (int) $canvasWidth : null,
            'canvasHeight' => is_numeric($canvasHeight) ? (int) $canvasHeight : null,
            'isContained' => $isContained,
            'sizes' => $extraAttributes['sizes'] ?? '100vw',
            'srcset' => $srcset,
            'srcsetWebp' => $srcsetWebp,
            'src' => $src,
            'fallbackMimeType' => $this->normalizeMimeType($media->mime_type) ?? 'image/jpeg',
            'width' => $originalWidth,
            'height' => $originalHeight,
            'isResponsive' => $isResponsive,
            'isBoxed' => $isBoxed,
            'fallbackHeight' => $renderedHeight ?? ($isBoxed ? $originalHeight : $originalWidth),
            'class' => $class,
            'lqipColor' => $lqipColor,
            'blurHash' => $blurHash,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getImageResizerResponsiveSrcsets($media, int $width, int|string $height, string $type, ?string $name): array
    {
        $sourceWidth = (int) $media->getCustomProperty('image_resizer.width', 0);
        $sourceHeight = (int) $media->getCustomProperty('image_resizer.height', 0);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return ['', ''];
        }

        $candidateWidth = min($width * 2, $sourceWidth);
        $jpeg = [];
        $webp = [];

        while ($candidateWidth > 150) {
            $candidateHeight = is_numeric($height)
                ? max(1, (int) ceil($candidateWidth * (int) $height / $width))
                : 'null';

            [$renderedWidth] = $this->getImageResizerRenderedDimensions($media, $candidateWidth, $candidateHeight, $type);
            $descriptorWidth = $renderedWidth ?? $candidateWidth;
            $jpeg[$descriptorWidth] = $this->getFriendlyImageUrl($candidateWidth, $candidateHeight, $type, $media, $name).' '.$descriptorWidth.'w';
            $webp[$descriptorWidth] = $this->getFriendlyImageUrl($candidateWidth, $candidateHeight, $type, $media, $name, 'image/webp').' '.$descriptorWidth.'w';
            $candidateWidth = (int) ceil($candidateWidth / 2);
        }

        return [implode(', ', $jpeg), implode(', ', $webp)];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function getImageResizerRenderedDimensions($media, int $width, int|string $height, string $type): array
    {
        if ($type === 'fit' && is_numeric($height)) {
            return [$width, (int) $height];
        }

        $sourceWidth = (int) $media->getCustomProperty('image_resizer.width', 0);
        $sourceHeight = (int) $media->getCustomProperty('image_resizer.height', 0);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return [null, null];
        }

        if ($type !== 'resize') {
            return [$sourceWidth, $sourceHeight];
        }

        $scale = min(1, $width / $sourceWidth);

        if (is_numeric($height)) {
            $scale = min($scale, (int) $height / $sourceHeight);
        }

        return [
            max(1, (int) round($sourceWidth * $scale)),
            max(1, (int) round($sourceHeight * $scale)),
        ];
    }

    public function getThumbnailHtml($width, $height, $type, $extraAttributes = [], $name = null, $class = null, $extraClass = null)
    {
        $media = $this->getThumbnailMedia();

        return $this->getMediaHtml($media, $width, $height, $type, $extraAttributes, $name, $class, $extraClass);
    }
}

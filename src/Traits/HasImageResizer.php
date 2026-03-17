<?php

namespace Elfeffe\ImageResizer\Traits;

use Illuminate\Support\Str;

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

    public static function getFriendly($width, $height = 'null', $type = 'resize', $media = null, $name = null, $mimeConvert = null): string|null
    {
        $class = new self();
        return $class->getFriendlyImageUrl($width, $height, $type, $media, $name, $mimeConvert);
    }

    public function getThumbnailMedia($collection = 'default')
    {
        return blink()->once('getThumbnailMedia_' . $collection . $this->id, function() use ($collection)
        {
            return $this->getFinalMedia($collection)->first();
        });
    }

    public function getFinalMedia($collection = 'default')
    {
        return $this->getMedia($collection);
    }

    public function getFriendlyImageUrl($width, $height = 'null', $type = 'resize', $media = null, $name = null, $mimeConvert = null): string|null
    {
        if (!$name) {
            $name = $this->name;
        }

        if (!$media) {
            $media = $this->getThumbnailMedia();
        }

        if (!$media) {
            return null;
        }

        if(!$mimeConvert)
        {
            $mimeConvert = $media['mime_type'];
        }

        // Use relative URLs to avoid mixed content issues
        return match ($mimeConvert) {
            'image/jpg', 'image/jpeg' =>
                '/image_resizer/' . $media->id . '/w/' . $width . '/h/' . $height . '/' . $type . '/' . Str::slug($name, '_') . '.jpg',
            'image/png' =>
                '/image_resizer/' . $media->id . '/w/' . $width . '/h/' . $height . '/' . $type . '/' . Str::slug($name, '_') . '.png',
            'image/webp' =>
                '/image_resizer/' . $media->id . '/w/' . $width . '/h/' . $height . '/' . $type . '/' . Str::slug($name, '_') . '.webp',
            default => 
                '/image_resizer/' . $media->id . '/w/' . $width . '/h/' . $height . '/' . $type . '/' . Str::slug($name, '_') . '.jpg',
        };
    }

    public function getMediaUrl($media, $width, $height = null, $type = 'resize', $name = null, $mimeConvert = null): string|null
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
        if (!$mimeType) {
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

    public function getMediaHtml($media, $width, $height, $type, $extraAttributes = [], $name = 'image', $class = null, $extraClass = null)
    {
        // Handle null media gracefully
        if (!$media) {
            return '<div class="bg-gray-200 flex items-center justify-center text-gray-500 text-sm" style="width: ' . $width . 'px; height: ' . ($height ?: $width) . 'px;">No image</div>';
        }

        if(!$class)
        {
            $class = 'justify-center items-center ';
        }

        $class .= ' ' . $extraClass;

        if (!$height) {
            $height = 'null';
        }

        if (!$type) {
            $type = 'null';
        }

        $originalWidth = $width;
        $originalHeight = $height;

        $width = ceil($width * 2);

        $srcset = '';
        $srcsetWebp = '';
        
        // Build srcset with proper validation
        if (is_int($height)) {
            $height = ceil($height * 2);
            $jpegUrl = $this->getFriendlyImageUrl($width, $height, $type, $media, $name);
            $webpUrl = $this->getFriendlyImageUrl($width, $height, $type, $media, $name, 'image/webp');
            
            if ($jpegUrl) {
                $srcset = $jpegUrl . ' 2x, ';
            }
            if ($webpUrl) {
                $srcsetWebp = $webpUrl . ' 2x, ';
            }
        } else {
            $jpegUrl = $this->getFriendlyImageUrl($width, $height, $type, $media, $name);
            $webpUrl = $this->getFriendlyImageUrl($width, $height, $type, $media, $name, 'image/webp');
            
            if ($jpegUrl) {
                $srcset = $jpegUrl . ' 2x, ';
            }
            if ($webpUrl) {
                $srcsetWebp = $webpUrl . ' 2x, ';
            }
        }

        while ($width > 150) {
            if (is_int($height)) {
                $height = ceil($height * 0.5);
                $jpegUrl = $this->getFriendlyImageUrl($width, $height, $type, $media, $name);
                $webpUrl = $this->getFriendlyImageUrl($width, $height, $type, $media, $name, 'image/webp');
                
                if ($jpegUrl) {
                    $srcset .= $jpegUrl . ' ' . $width . 'w, ';
                }
                if ($webpUrl) {
                    $srcsetWebp .= $webpUrl . ' ' . $width . 'w, ';
                }
            } else {
                $jpegUrl = $this->getFriendlyImageUrl($width, $height, $type, $media, $name);
                $webpUrl = $this->getFriendlyImageUrl($width, $height, $type, $media, $name, 'image/webp');
                
                if ($jpegUrl) {
                    $srcset .= $jpegUrl . ' ' . $width . 'w, ';
                }
                if ($webpUrl) {
                    $srcsetWebp .= $webpUrl . ' ' . $width . 'w, ';
                }
            }

            $width = ceil($width * 0.5);
        }

        // Clean up trailing commas and spaces
        $srcset = rtrim($srcset, ', ');
        $srcsetWebp = rtrim($srcsetWebp, ', ');

        $attributeString = collect($extraAttributes)
            ->map(fn($value, $name) => $name . '="' . $value . '"')->implode(' ');

        $loadingAttributeValue = null;

        // Use full-size image for immediate loading instead of 32px placeholder
        $src = $this->getFriendlyImageUrl($originalWidth, $originalHeight, $type, $media, $name);
        $srcWebp = $this->getFriendlyImageUrl($originalWidth, $originalHeight, $type, $media, $name, 'image/webp');

        // Fallback to original media URL if image resizer fails
        if (!$src && $media) {
            $src = $media->getUrl();
        }

        if($originalHeight == 'null' || !$originalHeight)
        {
            $originalHeight = $originalWidth;
        }

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

        return view('resizer::placeholder', [
            'attributeString' => $attributeString,
            'loadingAttributeValue' => $loadingAttributeValue,
            'srcset' => $srcset,
            'srcsetWebp' => $srcsetWebp,
            'srcWebp' => $srcWebp,
            'src' => $src,
            'width' => $originalWidth,
            'height' => $originalHeight,
            'class' => $class,
            'lqipColor' => $lqipColor,
            'blurHash' => $blurHash,
        ]);
    }

    public function getThumbnailHtml($width, $height, $type, $extraAttributes = [], $name = null, $class = null, $extraClass = null)
    {
        $media = $this->getThumbnailMedia();

        return $this->getMediaHtml($media, $width, $height, $type, $extraAttributes, $name, $class, $extraClass);
    }
}


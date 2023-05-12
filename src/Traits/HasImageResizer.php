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

    public function getThumbnailMedia($collection = null)
    {
        return $this->getFinalMedia()->first();
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

        return match ($mimeConvert) {
            'image/jpg', 'image/jpeg' =>
                config('commerce.friendly_image_url') . '/image_resizer/' . $media->id . '/w/' . $width . '/h/' . $height . '/' . $type . '/' . Str::slug($name, '_') . '.jpg',
            'image/png' =>
                config('commerce.friendly_image_url') . '/image_resizer/' . $media->id . '/w/' . $width . '/h/' . $height . '/' . $type . '/' . Str::slug($name, '_') . '.png',
            'image/webp' =>
                config('commerce.friendly_image_url') . '/image_resizer/' . $media->id . '/w/' . $width . '/h/' . $height . '/' . $type . '/' . Str::slug($name, '_') . '.webp',
            default => 'unknown status code',
        };
    }

    public function getMediaHtml($media, $width, $height, $type, $extraAttributes = [], $name = 'image', $class = null, $extraClass = null)
    {
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

        if (is_int($height)) {
            $height = ceil($height * 2);
            $srcset = $this->getFriendlyImageUrl($width, $height, $type, $media, $name) . ' 2x, ';
            $srcsetWebp = $this->getFriendlyImageUrl($width, $height, $type, $media, $name, 'image/webp') . ' 2x, ';
        } else {
            $srcset = $this->getFriendlyImageUrl($width, $height, $type, $media, $name) . ' 2x, ';
            $srcsetWebp = $this->getFriendlyImageUrl($width, $height, $type, $media, $name, 'image/webp') . ' 2x, ';
        }

        while ($width > 32) {
            $width = ceil($width * 0.7);

            if (is_int($height)) {
                $height = ceil($height * 0.7);
                $srcset .= $this->getFriendlyImageUrl($width, $height, $type, $media, $name) . ' ' . $width . 'w, ';
                $srcsetWebp .= $this->getFriendlyImageUrl($width, $height, $type, $media, $name, 'image/webp') . ' ' . $width . 'w, ';
            } else {
                $srcset .= $this->getFriendlyImageUrl($width, $height, $type, $media, $name) . ' ' . $width . 'w, ';
                $srcsetWebp .= $this->getFriendlyImageUrl($width, $height, $type, $media, $name, 'image/webp') . ' ' . $width . 'w, ';
            }
        }

        $attributeString = collect($extraAttributes)
            ->map(fn($value, $name) => $name . '="' . $value . '"')->implode(' ');

        $loadingAttributeValue = null;

        $src = $this->getFriendlyImageUrl(10, 10, $type, $media, $name);
        $srcWebp = $this->getFriendlyImageUrl(10, 10, $type, $media, $name, 'image/webp');

        if($originalHeight == 'null' || !$originalHeight)
        {
            $originalHeight = $originalWidth;
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
        ]);
    }

    public function getThumbnailHtml($width, $height, $type, $extraAttributes = [], $name = null)
    {
        $media = $this->getThumbnailMedia();

        return $this->getMediaHtml($media, $width, $height, $type, $extraAttributes, $name);
    }
}


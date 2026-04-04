---
name: image-resizer-development
description: Build and use the elfeffe/image-resizer package for optimized media URLs, responsive HTML, BlurHash/LQIP generation, and package image delivery.
---

# Image Resizer Development

Use this skill when working on `elfeffe/image-resizer`, optimized media output, responsive image markup, or package image delivery routes.

## Main package pieces

| Class / file | Purpose |
|---|---|
| `Traits\HasImageResizer` | Model helpers for URLs, HTML, and srcsets |
| `Http\Controllers\ImageResizerController` | Dynamic resize endpoint |
| `Jobs\CalculateLqipJob` | Generates BlurHash and LQIP data |
| `routes/web.php` | Registers `image_resizer/...` route |
| `Console\Commands\InstallHtaccessCommand` | Apache optimization helper |

## Core usage

Add the trait to a model that already uses Spatie Media Library:

```php
use Elfeffe\ImageResizer\Traits\HasImageResizer;

class Product extends Model implements HasMedia
{
    use HasImageResizer;
    use InteractsWithMedia;
}
```

Common helpers:

```php
$url = $model->getMediaUrl($media, 800, 600);
$html = $model->getMediaHtml($media, 800, 600, 'resize', ['alt' => 'Description']);
$srcset = $model->getMediaSrcset($media, [400, 600, 800, 1200]);
```

## Frontend directives

Include the package directives once in the layout:

```blade
@imageResizerStyles
@imageResizerScripts
```

## Route and cache behavior

- The package serves images through `/image_resizer/{id}/w/{w}/h/{h}/{type}/{path}.{ext}`
- Generated files are cached on the `image_resizer` disk
- `image-resizer:install-htaccess` can configure direct Apache serving for cached files

## Best practices

- Prefer `getMediaHtml()` when you want package lazy-loading and presentation behavior
- Prefer package helpers instead of hand-building resized image URLs
- Keep parent containers dimensioned when the UI depends on predictable image layout
- Search Laravel, Spatie Media Library, and package docs before changing image generation or delivery behavior

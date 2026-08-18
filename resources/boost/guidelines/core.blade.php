## Image Resizer

This package provides optimized media URLs and HTML helpers, responsive image output, and lazy-loading support around Spatie Media Library.

### Use the package primitives

- Add `Elfeffe\ImageResizer\Traits\HasImageResizer` to media-aware models.
- Use `getMediaUrl()`, `getMediaHtml()`, and `getMediaSrcset()` instead of hand-building resized image URLs.
- Keep the package route and cache flow in place; do not replace it with ad-hoc image controllers.

@verbatim
<code-snippet name="Use HasImageResizer on a model" lang="php">
use Elfeffe\ImageResizer\Traits\HasImageResizer;

class Product extends Model implements HasMedia
{
    use HasImageResizer;
    use InteractsWithMedia;
}
</code-snippet>
@endverbatim

### Frontend

- Include `@imageResizerStyles` and `@imageResizerScripts` once in the layout.
- Prefer `getMediaHtml()` when you want the package lazy-loading and presentation behavior.

@verbatim
<code-snippet name="Render optimized media HTML" lang="php">
{!! $model->getMediaHtml($media, 800, 600, 'resize', ['alt' => 'Description']) !!}
</code-snippet>
@endverbatim

### Storage & serving

- Resized images live on the `image_resizer` disk; `config('image-resizer')` selects the driver (`local` default, `s3` for object storage).
- Default config reproduces the historical behaviour exactly — do not change defaults or the flat local cache layout (`{id}/{w}x{h}/{type}.{ext}`), which Apache/Nginx fast paths depend on.
- In s3 modes never write locally, never add fallbacks, and never redirect in `cdn_origin` mode (CDN loop).
- Object-storage failures must stay loud: `Log::error` + 500, never silent regeneration.
- Do not change the s3 key layout (`{id}/w/{w}/h/{h}/{type}/{file}` under the disk root) — serve URLs map to it 1:1.
- Absolute CDN URLs come from `serve.url` + `serve.mode=cdn_origin` in `getFriendlyImageUrl()`; do not build them elsewhere.

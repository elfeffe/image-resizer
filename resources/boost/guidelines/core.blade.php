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

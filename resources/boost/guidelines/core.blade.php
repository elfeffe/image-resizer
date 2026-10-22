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
- Default config reproduces the historical behaviour exactly — do not change defaults or the flat local cache layout (`{id}/{w}x{h}/{type}.{ext}`), which Apache/Nginx fast paths depend on (in a project that signs the `{id}` folder is `Access::folder()`'s token).
- In s3 modes never write locally, never add fallbacks, and never redirect in `cdn_origin` mode (CDN loop).
- Object-storage failures must stay loud: `Log::error` + 500, never silent regeneration.
- Do not change the s3 key layout (`{id}/w/{w}/h/{h}/{type}/{file}` under the disk root) — serve URLs map to it 1:1.
- Absolute CDN URLs come from `serve.url` + `serve.mode=cdn_origin` in `getFriendlyImageUrl()`; do not build them elsewhere.

### Access control

- The route takes any media id and asks for no login. A project that sets none of `image-resizer.signed` / `image-resizer.strict` is served exactly as before (do not break that: same URLs, same cache layout, same answers); only the route's own segments are read (a query-string `type`/`img` changes nothing).
- A strict project (`strict`, or `signed`, which includes it): `w`/`h` at most `image-resizer.max_dimension` (422), `type` one of `Access::TYPES` (422), `type=original` a 403 unless `image-resizer.allow_original`, `fit` never above the source. `image-resizer.throttle` (unset = no limit) sets the named limiter `image-resizer`, requests a minute per address.
- A project that sets `image-resizer.signed` has every URL end in `?s=`, the HMAC of `{id}|{w}|{h}|{type}|{name}.{ext}` (`Support\Access::signature()`); `ImageResizerController::admit()` answers 403 before any query or disk read, a cache hit included, and the cache lives under `Access::folder()`'s token.
- Build a URL only with `getMediaUrl()`, `getMediaHtml()` or `getFriendlyImageUrl()`: never by hand, never by rewriting a size in JavaScript. Keep `HasImageResizer` and `ImageResizerController::admit()` agreeing on every byte of the signed path, and the signature out of every cache path.
- `webwizo/laravel-shortcodes` is a suggestion: guard every use with `class_exists` and `$this->app->bound('shortcode')`.
- `image-resizer:prune` counts a file only once it is deleted; a host that queues it itself sets `image-resizer.prune.schedule` false.

### Preparing resizes before the first view

- A model opts in by defining `public function imageResizerWarmUrls(Media $media): array`, returning the resize URLs its views ask for (built with `getMediaUrl()`); `WarmResizesJob` then makes them right after the image is stored, from one read of the original. `HasImageResizer` has no default for it: a model without the method never gets a job.
- Keep the method in step with the sizes the views use, and run `image-resizer:warm` (`--model=` limits it) after changing them. `ImageResizerController::warm()` reads each URL through the route's own `admit()`: do not make a second path to the cache.

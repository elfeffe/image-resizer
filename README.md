# Image Resizer Package

A Laravel package that provides image resizing, optimization, and lazy loading with BlurHash support for smooth progressive image loading.

## Features

- **Image Resizing**: Automatic image resizing with WebP support
- **BlurHash Integration**: Smooth progressive loading with multi-color blur placeholders
- **Lazy Loading**: Smart lazy loading with IntersectionObserver
- **LQIP Support**: Low Quality Image Placeholders with dominant color extraction
- **Self-Contained**: No external dependencies, works independently
- **Mobile Optimized**: Responsive design with proper aspect ratio handling
- **SEO Friendly**: Optimized for search engines with proper image attributes
- **Google Indexing**: Real src attributes ensure images are properly indexed

## SEO & Google Indexing

🔍 **The package is fully SEO-optimized and Google-friendly!**

### ✅ **SEO Features:**

- **Real `src` attributes**: Images have actual URLs immediately, not placeholder data attributes
- **Proper `srcset` attributes**: Responsive image sets are visible to crawlers
- **Native lazy loading**: Uses browser's `loading="lazy"` attribute
- **NoScript fallback**: Images work even when JavaScript is disabled
- **Structured markup**: Proper `<picture>` elements with multiple formats
- **Image dimensions**: Width and height attributes for layout stability
- **Alt text support**: Full accessibility and SEO description support

### 🤖 **How Google Sees Your Images:**

```html
<!-- What Google's crawler sees immediately: -->
<picture>
  <source srcset="/images/nail-design-400.webp 400w, /images/nail-design-800.webp 800w" type="image/webp">
  <source srcset="/images/nail-design-400.jpg 400w, /images/nail-design-800.jpg 800w" type="image/jpeg">
  <img 
    src="/images/nail-design-800.jpg"
    srcset="/images/nail-design-400.jpg 400w, /images/nail-design-800.jpg 800w"
    alt="Beautiful nail art design with flowers"
    width="800"
    height="600"
    loading="lazy"
  />
</picture>
```

### 🚀 **Zero SEO Impact:**

- **No lazy loading penalties**: Real URLs are present from server-side rendering
- **Google Images indexing**: All image URLs are discoverable immediately  
- **Fast Core Web Vitals**: Native lazy loading improves page speed scores
- **Accessibility compliant**: Screen readers see proper image information
- **Progressive enhancement**: BlurHash and smooth loading enhance UX without affecting SEO

### 📊 **SEO vs UX Balance:**

| Aspect | SEO Benefit | UX Enhancement |
|--------|-------------|----------------|
| Real `src` attributes | ✅ Google sees actual URLs | ✅ Images work without JS |
| `loading="lazy"` | ✅ Faster page load scores | ✅ Native browser optimization |
| BlurHash placeholders | ✅ No impact on crawling | ✅ Smooth progressive loading |
| WebP + JPEG sources | ✅ Modern format indexing | ✅ Optimal format per browser |
| Responsive srcsets | ✅ Mobile-friendly signals | ✅ Perfect images per device |

**Result:** You get the best of both worlds - perfect SEO indexing AND amazing user experience!

## Installation

1. **Install the package via Composer:**
```bash
composer require elfeffe/image-resizer
```

2. **Publish the configuration:**
   ```bash
   php artisan vendor:publish --tag=image-resizer-config
   ```

3. **Publish the migrations:**
```bash
   php artisan vendor:publish --tag=image-resizer-migrations
php artisan migrate
```

4. **Publish the assets:**
   ```bash
   php artisan vendor:publish --tag=image-resizer-assets
   ```

5. **Optimize for Apache servers (optional but recommended):**
   
   For **maximum performance**, install the htaccess optimization rules:
   
   **Option A: Automated Installation (Recommended)**
   ```bash
   php artisan image-resizer:install-htaccess
   ```
   
   This command will:
   - ✅ **Safely merge** rules into your existing `.htaccess` file
   - ✅ **Create a backup** before making changes
   - ✅ **Check for existing rules** to avoid duplicates
   - ✅ **Place rules in the correct location** (after `RewriteEngine On`)
   
   **Option B: Manual Integration**
   ```bash
   php artisan vendor:publish --tag=image-resizer-htaccess
   ```
   
   This creates `.htaccess-image-resizer` in your `public/` directory. Then manually add the contents to your existing `public/.htaccess` file, right after `RewriteEngine On`:
   
   ```apache
   RewriteEngine On
   
   # Image Resizer Performance Optimization
   # This rule serves cached images directly from filesystem when available,
   # falling back to Laravel dynamic processing when not cached yet.
   
   # Image Resizer Rule - Check for cached file first, then fallback to Laravel
   RewriteCond %{REQUEST_URI} ^/image_resizer/([^/]*)/w/([^/]*)/h/([^/]*)/([^/]*)/([^.]*)\.(.*)$
   RewriteCond %{DOCUMENT_ROOT}/storage/image_resizer/%1/%2x%3/%4.%6 -f
   RewriteRule ^image_resizer/([^/]*)/w/([^/]*)/h/([^/]*)/([^/]*)/([^.]*)\.(.*)$ /storage/image_resizer/$1/$2x$3/$4.$6 [L]
   
   # Your existing rules...
   ```

6. **For Nginx servers (alternative):**
   
   Add this to your Nginx configuration:
   ```nginx
   location / {
       # Image Resizer optimization - serve cached files directly
       rewrite ^/image_resizer\/([^/]*)\/w\/([^/]*)\/h\/([^/]*)\/([^/]*)\/([^.]*)\.(.*)$ /storage/image_resizer/$1/$2x$3/$5.$6 last;
       
       # Your existing try_files...
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

### Safety Features

The package includes several safety features to protect your existing configuration:

#### Automated Installation Safety:
- 🔒 **Automatic backups**: Creates `.htaccess.backup.YYYY-MM-DD-HH-MM-SS` before any changes
- 🔍 **Duplicate detection**: Won't add rules if they already exist
- 🎯 **Precise placement**: Inserts rules in the correct location automatically
- 🛡️ **Non-destructive**: Never overwrites your existing `.htaccess` file
- 🔄 **Reversible**: Use `--force` flag to reinstall if needed

#### Manual Integration Safety:
- 📁 **Separate file**: Published as `.htaccess-image-resizer` (doesn't overwrite existing)
- 👀 **Review first**: You can inspect the rules before adding them
- 🎛️ **Full control**: You decide exactly where to place the rules

#### Troubleshooting:
```bash
# Reinstall rules (useful after Laravel updates)
php artisan image-resizer:install-htaccess --force

# Check if rules are working
curl -I https://yoursite.com/image_resizer/123/w/800/h/600/resize/test.jpg
# Look for: X-Image-Resizer: true (Laravel processing)
# Or standard Apache headers (direct file serving)
```

## Storage & serving

Resized images are stored on the `image_resizer` disk. By default that disk is
local (`storage/app/public/image_resizer`) and URLs are app-relative — the
historical behaviour, including the Apache/Nginx direct-serve fast paths
(`image-resizer:install-htaccess`).

For object storage (Backblaze B2, Cloudflare R2, AWS S3) set
`IMAGERESIZER_STORAGE_DRIVER=s3`. Resized images then live in the bucket under
the configured `root` prefix and are never written to the app disk.

### Configuration

| Key | Env | Default | Purpose |
|---|---|---|---|
| `storage.disk` | `IMAGERESIZER_STORAGE_DISK` | `image_resizer` | Disk name registered for resizes |
| `storage.driver` | `IMAGERESIZER_STORAGE_DRIVER` | `local` | `local` or `s3` |
| `storage.root` | `IMAGERESIZER_STORAGE_ROOT` | `image_resizer` | Bucket key prefix (s3 only) |
| `storage.url` | `IMAGERESIZER_STORAGE_URL` | — | Public bucket base URL (s3 disk) |
| `storage.visibility` | `IMAGERESIZER_STORAGE_VISIBILITY` | `public` | Object visibility |
| `storage.s3.key` | `IMAGERESIZER_STORAGE_KEY` | — | |
| `storage.s3.secret` | `IMAGERESIZER_STORAGE_SECRET` | — | |
| `storage.s3.region` | `IMAGERESIZER_STORAGE_REGION` | — | |
| `storage.s3.bucket` | `IMAGERESIZER_STORAGE_BUCKET` | — | |
| `storage.s3.endpoint` | `IMAGERESIZER_STORAGE_ENDPOINT` | — | |
| `storage.s3.use_path_style_endpoint` | `IMAGERESIZER_STORAGE_PATH_STYLE` | `false` | |
| `serve.url` | `IMAGERESIZER_SERVE_URL` | — | Public base for resize URLs (CDN/CNAME/bucket) |
| `serve.mode` | `IMAGERESIZER_SERVE_MODE` | `cdn_origin` | `cdn_origin` or `redirect` |

### Serving modes

- **`local` (default):** cached files at `{id}/{w}x{h}/{type}.{ext}` served by
  the web server fast path or generated on demand. Unchanged historical behaviour.
- **`s3` + `cdn_origin`:** point a CDN pull zone (e.g. Bunny) at the app and set
  `serve.url` to the CDN URL. Emitted URLs point at the CDN; on a cache miss the
  CDN pulls the app, which GETs the object from the bucket, or generates it,
  stores it (with `Content-Type` and `Cache-Control: public, max-age=2628000`),
  and streams it. This mode never redirects (loop-safe). Works with a private bucket.
- **`s3` + `redirect`:** set `serve.url` to the public bucket/CDN URL. Emitted
  URLs stay on the app route; the app 301s to the bucket object once it exists
  (generating + storing it on first request). Requires `serve.url` and a
  public-read bucket.

`type=original` redirects to the original media URL, and is refused unless `allow_original` is on (see *Access control*).

### Notes

- s3 mode requires Laravel's S3 driver (`league/flysystem-aws-s3-v3`) in the
  host project.
- Object storage keys are URL-shaped (`{id}/w/{w}/h/{h}/{type}/{file}` under the
  disk root) so `serve.url` + URL path resolves to the object.
- Storage failures are never silent: the endpoint returns 500 and logs the error.
- When a CDN sits in front of the app, keep edge 404 caching disabled so missing
  media does not poison the cache.

## Access control

`/image_resizer/{id}/w/{w}/h/{h}/{type}/{file}` takes any media id and asks for no login, so unless a project closes it a counter
loop (`/image_resizer/1/…`, `/2/…`) lists everything the project stores. **A project that sets none of the flags below is served
exactly as it was before these rules existed** (same URLs, same cache layout, same answers; one thing apart: only the route's own
segments are read, see *Every project*). It opts in with `strict`, or with `signed`, which includes `strict`.

| Key | Env | Default | Purpose |
|---|---|---|---|
| `signed` | `IMAGERESIZER_SIGNED` | `false` | Sign every URL the builders make and refuse any other request; implies `strict` |
| `signature_key` | `IMAGERESIZER_SIGNATURE_KEY` | `app.key` | The HMAC key |
| `strict` | `IMAGERESIZER_STRICT` | `false` | Hold the route to the types, the size limit and the `original` gate, without signing |
| `allow_original` | `IMAGERESIZER_ALLOW_ORIGINAL` | `false` | Serve `type=original` in a strict project |
| `max_dimension` | `IMAGERESIZER_MAX_DIMENSION` | `4096` | The largest `w` or `h` in a strict project; more is a 422 |
| `throttle` | `IMAGERESIZER_THROTTLE` | unset | Requests a minute per address (named limiter `image-resizer`); unset or 0 is no limit |
| `prune.schedule` | `IMAGERESIZER_PRUNE_SCHEDULE` | `true` | `false`: the package does not schedule `image-resizer:prune`, the host runs it |

### Strict (`strict`, or `signed`)

- **Sizes:** `w` and `h` above `max_dimension` are a 422. A width or height that is not a positive number (`0`, `None`) still means the
  source's own size, and every spelling of a size (`0100`, `h/None`) is one cache entry. The builders ask for the limit at most.
- **Types:** `resize`, `fit`, `null` (the file re-encoded at its own size, what `getMediaHtml()` asks for without a type), `crop`
  (never implemented, so also the file at its own size; two Neoteo themes still ask for it) and `original`; any other is a 422.
  `resize` and `fit` never go above the source: a `fit` box larger than the image crops it to the box's ratio at its own size.
- **`original`** is a 403 unless `allow_original` is on. No builder of this package makes such a URL.
- `allow_original` is not a confidentiality control in a project that does not sign: `type=null`, `crop`, `w/0` and `w/4096` return the
  same picture at full size. Only signing keeps a stranger from it.

### Throttle

Above `throttle` requests a minute from one address the route answers 429 with `Retry-After`. It is off unless the host sets a number.
A cache hit counts, and a page of thumbnails asks for one request each, so a dense page needs a high number. Behind a proxy or a CDN
the address is the proxy's unless the host trusts it, and a CDN pull zone sends every visitor from a few addresses: size it for them.
A host that wants another rule defines the limiter `image-resizer` itself.

### Every project

- **Route segments only:** a `?type=original` or `?img=7` in the query string changes nothing; the code reads the path's own segments.
  Laravel's `Request::__get()` prefers a query-string value to a route parameter, which let a visitor steer the controller, past a signature
  and past a host that checks the route's `img` (PostFiber's tenant guard does).

### Signed URLs

With `signed` on, `getFriendlyImageUrl()`, `getMediaUrl()` and `getMediaHtml()` end every URL in `?s=`, and the route answers 403 to
any request whose `s` is not the signature of its own path, before it reads the database or a disk (a cache hit included):

```
s = hash_hmac('sha256', "{id}|{w}|{h}|{type}|{name}.{ext}", signature_key ?? app.key)   // hex, the path's segments as the URL carries them
```

`Elfeffe\ImageResizer\Support\Access::signature($id, $w, $h, $type, $file)` makes it. The file name and the format are signed too:
the encoder follows `ext`, and an object-storage resize is kept under a key that carries the name.

- A URL is valid for as long as the key is: it survives a deploy, and rotating `app.key` (or `signature_key`) turns every URL already
  handed out, in pages, feeds and emails, into a 403 and every cache folder into a miss (`APP_PREVIOUS_KEYS` is not honoured: do not
  rotate it casually).
- Resizes are cached under a token derived from the id (`Access::folder()`), not the id. A file kept at `{id}/{w}x{h}/{type}.{ext}` is
  reachable by anyone who knows three small numbers (`/storage/image_resizer/…` when the disk is linked into `public`, the Apache and
  Nginx fast paths, a public bucket), and none of them can check a signature. The signature is never part of a path: all the signed
  URLs of a variant share one file. Turning `signed` on leaves the old `{id}` folders unread by the app; delete them once, as the user
  that wrote them (`image-resizer:prune` would only in time, and never while someone still fetches them: it goes by the last read).
- Never build a resizer URL by hand and never rewrite its `w`, `h` or `type` in JavaScript or in a consumer of an API: that is a 403.
  Ask the builders for the size you need.
- The fast paths of `image-resizer:install-htaccess` and the Nginx rewrite above never find a file in a project that signs; do not rely
  on them there. A CDN pull zone must forward the query string to the app (`?s=` is part of the URL), and the CDN's own cache must not
  ignore it.
- `serve.mode=redirect` hands the object's own URL to whoever passes the check, and that bucket is public-read: whoever has seen the
  redirect can keep reading the object. Use `cdn_origin` with a private bucket for confidential media.

### Shortcodes (optional)

The `[media-library-item]` shortcode is registered only when `webwizo/laravel-shortcodes` is installed and its provider registered (it
is a Composer *suggestion*, not a requirement: its provider replaces Laravel's view factory). A project without it boots and renders as
before; a project that uses the shortcode requires the package itself.

## Usage

### Basic Usage

Add the trait to your model:

```php
use Elfeffe\ImageResizer\Traits\HasImageResizer;

class YourModel extends Model
{
    use HasImageResizer;
}
```

### In Blade Views

Include the package styles and scripts in your layout:

```blade
@imageResizerStyles
@imageResizerScripts
```

Use the helper method to display images:

```blade
{!! $model->getMediaHtml($media, 800, 600) !!}
```

### Different Ways to Use the Package

The package provides multiple methods for working with images, depending on your needs:

#### 1. Complete HTML Output (Recommended) - Zero Configuration
Use `getMediaHtml()` when you want the complete image markup with lazy loading, BlurHash, and optimization:

```blade
{!! $model->getMediaHtml($media, 800, 600) !!}
```

**🚀 This is completely automatic!** The method handles everything for you:
- ✅ **Lazy loading** - Images load when they come into view
- ✅ **BlurHash placeholders** - Smooth progressive loading
- ✅ **WebP optimization** - Modern format with JPEG fallback
- ✅ **Responsive srcsets** - Multiple sizes for different screens
- ✅ **SEO attributes** - Proper width, height, and alt attributes
- ✅ **Mobile optimization** - Works perfectly on all devices

**No additional configuration needed!** Just add `@imageResizerStyles` and `@imageResizerScripts` to your layout once, and every `getMediaHtml()` call will automatically include lazy loading and all optimizations.

#### 2. Image URLs Only (For Custom Markup)
When you need just the image URLs without HTML (for custom implementations, JSON APIs, or building your own markup):

```php
// Get optimized image URL
$imageUrl = $model->getMediaUrl($media, 800, 600);

// Get WebP version URL
$webpUrl = $model->getMediaUrl($media, 800, 600, 'webp');

// Get srcset string for responsive images
$srcset = $model->getMediaSrcset($media, [400, 600, 800, 1200]);
$webpSrcset = $model->getMediaSrcset($media, [400, 600, 800, 1200], 'webp');
```

**⚠️ Note:** When using individual URLs, you'll need to implement your own lazy loading logic if desired.

#### 3. Image Data Object (For Complex Implementations)
Get all image data as an object for advanced use cases:

```php
$imageData = $model->getMediaData($media, 800, 600);

// Returns object with:
// $imageData->src (main image URL)
// $imageData->srcset (responsive srcset)
// $imageData->srcsetWebp (WebP srcset)
// $imageData->blurHash (BlurHash string)
// $imageData->lqipColor (dominant color)
// $imageData->width (actual width)
// $imageData->height (actual height)
```

**⚠️ Note:** This gives you the data, but you'll need to build the lazy loading functionality yourself.

#### 4. BlurHash and LQIP Data Only
For cases where you only need the placeholder data:

```php
// Get BlurHash string
$blurHash = $media->getCustomProperty('blurhash');

// Get LQIP color
$lqipColor = $media->getCustomProperty('lqip_color');

// Check if BlurHash is available
$hasBlurHash = !empty($media->getCustomProperty('blurhash'));
```

### Use Cases for Different Methods

#### ✅ **Use `getMediaHtml()` when:**
- Building standard web pages
- You want **automatic lazy loading with zero setup**
- You need SEO optimization
- You want BlurHash integration out-of-the-box
- **You want everything handled automatically** (recommended for 90% of use cases)

#### ✅ **Use image URLs/data when:**
- Building JSON APIs
- Creating custom image components
- Using JavaScript frameworks (Vue, React, etc.)
- Building custom lazy loading implementations
- Creating image galleries with specific markup
- **You need full control over the HTML structure**

#### 📱 **API/JSON Response Example:**
```php
// In your API controller
public function getImages()
{
    $images = $this->model->getMedia('gallery')->map(function ($media) {
        return [
            'id' => $media->id,
            'src' => $this->model->getMediaUrl($media, 800, 600),
            'srcset' => $this->model->getMediaSrcset($media, [400, 600, 800, 1200]),
            'webp_srcset' => $this->model->getMediaSrcset($media, [400, 600, 800, 1200], 'webp'),
            'blurhash' => $media->getCustomProperty('blurhash'),
            'lqip_color' => $media->getCustomProperty('lqip_color'),
            'alt' => $media->getCustomProperty('alt', ''),
            'width' => $media->getCustomProperty('width'),
            'height' => $media->getCustomProperty('height'),
        ];
    });

    return response()->json($images);
}
```

#### 🎨 **Custom Blade Component Example:**
```blade
{{-- resources/views/components/custom-image.blade.php --}}
@php
    $imageData = $model->getMediaData($media, $width, $height);
@endphp

<div class="custom-image-container" data-blurhash="{{ $imageData->blurHash }}">
    <div class="custom-placeholder" style="background-color: {{ $imageData->lqipColor }}">
        <span class="loading-text">Loading amazing image...</span>
    </div>
    
    <picture class="custom-picture">
        <source data-srcset="{{ $imageData->srcsetWebp }}" type="image/webp">
        <source data-srcset="{{ $imageData->srcset }}" type="image/jpeg">
        <img 
            data-src="{{ $imageData->src }}"
            alt="{{ $alt }}"
            class="custom-img {{ $class }}"
            width="{{ $imageData->width }}"
            height="{{ $imageData->height }}"
        >
    </picture>
</div>

{{-- Usage --}}
<x-custom-image :model="$product" :media="$product->getFirstMedia('gallery')" :width="400" :height="300" alt="Product image" />
```

#### ⚛️ **JavaScript Framework Integration:**
```javascript
// Fetch image data for Vue/React components
fetch('/api/images')
    .then(response => response.json())
    .then(images => {
        images.forEach(image => {
            // Use image.src, image.srcset, image.blurhash, etc.
            // Build your custom image component
        });
    });
```

### Advanced Usage

#### Responsive Images

For responsive images (auto-height):

```blade
{!! $model->getMediaHtml($media, 800, null, 'resize', ['alt' => 'Description']) !!}
```

#### Custom CSS Classes

```blade
{!! $model->getMediaHtml($media, 800, 600, 'resize', ['alt' => 'Description'], 'custom-filename', 'custom-css-class') !!}
```

#### Manual Template Usage

You can also use the template directly:

```blade
@include('image-resizer::placeholder', [
    'src' => $imageSrc,
    'srcset' => $imageSrcset,
    'srcsetWebp' => $imageSrcsetWebp,
    'width' => 800,
    'height' => 600,
    'blurHash' => $blurHash,
    'lqipColor' => '#f0f0f0',
    'class' => 'my-image-class',
    'attributeString' => 'alt="My Image"'
])
```

## Critical: Parent Container Setup

⚠️ **IMPORTANT**: To ensure lazy loading works properly, parent containers must have defined dimensions. If the parent container has 0 width or height, the IntersectionObserver cannot detect the image, and lazy loading will never trigger.

### ✅ **Correct Parent Container Examples:**

#### Grid Layout (Recommended)
```blade
<!-- CSS Grid with defined aspect ratio -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($images as $image)
        <div class="aspect-square"> <!-- This ensures proper dimensions -->
            {!! $image->getMediaHtml($media, 400, 400) !!}
        </div>
    @endforeach
</div>
```

#### Flexbox Layout
```blade
<!-- Flexbox with min-height -->
<div class="flex flex-wrap gap-4">
    @foreach($images as $image)
        <div class="w-80 h-80 flex-shrink-0"> <!-- Fixed dimensions -->
            {!! $image->getMediaHtml($media, 320, 320) !!}
        </div>
    @endforeach
</div>
```

#### Responsive Cards
```blade
<!-- Card layout with proper container -->
<div class="max-w-sm mx-auto bg-white rounded-lg shadow-md overflow-hidden">
    <div class="h-48"> <!-- Fixed height for image container -->
        {!! $model->getMediaHtml($media, 400, 192) !!}
    </div>
    <div class="p-4">
        <h3 class="font-bold">Card Title</h3>
        <p class="text-gray-600">Card content...</p>
    </div>
</div>
```

#### Masonry/Gallery Layout
```blade
<!-- Masonry with aspect ratio containers -->
<div class="columns-1 md:columns-2 lg:columns-3 gap-4">
    @foreach($images as $image)
        <div class="break-inside-avoid mb-4">
            <!-- Let the image determine height but ensure width -->
            <div class="w-full min-h-[200px]">
                {!! $image->getMediaHtml($media, 400, null) !!}
            </div>
        </div>
    @endforeach
</div>
```

### ❌ **Incorrect Parent Container Examples:**

#### No Defined Dimensions
```blade
<!-- BAD: Container may collapse to 0 height -->
<div> <!-- No dimensions defined -->
    {!! $model->getMediaHtml($media, 400, 400) !!}
</div>
```

#### Undefined Parent Width
```blade
<!-- BAD: Parent width undefined -->
<div class="some-undefined-container">
    {!! $model->getMediaHtml($media, 400, 400) !!}
</div>
```

#### Nested Containers Without Dimensions
```blade
<!-- BAD: Nested containers without proper sizing -->
<div class="wrapper">
    <div class="inner"> <!-- No dimensions -->
        {!! $model->getMediaHtml($media, 400, 400) !!}
    </div>
</div>
```

### 🔧 **Debugging Container Issues:**

If images aren't loading, check the container dimensions:

```javascript
// Add this to your browser console to debug
document.querySelectorAll('[data-blurhash]').forEach(container => {
    const rect = container.getBoundingClientRect();
    console.log('Container dimensions:', {
        width: rect.width,
        height: rect.height,
        element: container
    });
    
    if (rect.width === 0 || rect.height === 0) {
        console.warn('Container has 0 dimensions - lazy loading will not work:', container);
    }
});
```

### 💡 **Best Practices:**

1. **Always define container dimensions** using CSS classes like `w-80 h-80`, `aspect-square`, or `h-48`
2. **Use aspect-ratio utilities** for responsive layouts: `aspect-video`, `aspect-square`, `aspect-[4/3]`
3. **Set min-height** for containers when height is dynamic: `min-h-[200px]`
4. **Test on mobile** to ensure containers maintain proper dimensions on smaller screens
5. **Use fixed dimensions** for critical above-the-fold images

## BlurHash Integration

The package automatically generates BlurHash strings for all uploaded images. BlurHash creates smooth, multi-color blur placeholders that provide a better user experience compared to solid color placeholders.

### How it works:

1. **Image Upload**: When an image is uploaded, a job is dispatched to calculate both LQIP color and BlurHash
2. **Storage**: BlurHash and LQIP data are stored in the media's `custom_properties`
3. **Display**: The template automatically uses BlurHash for fixed-dimension images and LQIP color for responsive images
4. **Progressive Loading**: BlurHash renders immediately, then fades to the actual image when loaded

### Manual BlurHash Generation

You can manually generate BlurHash for existing images:

```bash
php artisan image-resizer:calculate-lqip
```

## Configuration

The package configuration is located at `config/image-resizer.php`:

```php
return [
    'blurhash' => [
        'component_x' => 6,  // Horizontal detail (1-9)
        'component_y' => 4,  // Vertical detail (1-9)
        'max_size' => 128,   // Max processing size for performance
    ],
    'lqip' => [
        'quality' => 20,     // LQIP quality (1-100)
        'blur' => 5,         // Blur radius
    ],
    'formats' => [
        'webp' => true,      // Generate WebP versions
        'jpeg' => true,      // Generate JPEG versions
    ],
];
```

## Encoding and cache pruning

Every resize is written with its metadata stripped (EXIF and ICC, once the orientation they describe has been applied). JPEG and WebP use `IMAGERESIZER_QUALITY` (default 78). With `IMAGERESIZER_AVIF=true` the `<picture>` offers an AVIF `<source>` before the WebP one, which browsers that take it prefer (about 10–30 % smaller for the same look); it costs more CPU per resize, so it is off by default. `IMAGERESIZER_AVIF_QUALITY` (default 60, AVIF's own scale) and `IMAGERESIZER_AVIF_SPEED` (libheif's 0 slowest and best to 10 fastest, default 7) tune it. The `.avif` extension is always accepted by the route.

Cached resizes nobody has asked for in `IMAGERESIZER_PRUNE_DAYS` days (default 30; `0` turns the schedule off) are deleted every night at 04:40 by `image-resizer:prune`; on a local disk the last read counts as the last use, on a remote disk the file's age does, and folders left empty are removed. `php artisan image-resizer:prune --days=7 --dry-run` shows what a run would delete. A file counts as deleted only once it is gone: the files the user running the command may not remove are reported apart and the command exits with a failure. The crontab's `schedule:run` often runs as another user than the web server that wrote the cache; set `IMAGERESIZER_PRUNE_SCHEDULE=false` and run `image-resizer:prune` from a queued job (the queue worker runs as the web user).

## Preparing resizes before the first view

A resize is made by the first request for its URL, and that request downloads the whole original first: from a bucket that is about half a second plus the transfer, again for every size of the same image, with a web worker held the whole time. A page of new thumbnails queues behind those downloads. A model can say which resize URLs its views will ask for, and the package makes them in a queued job right after the image is stored, reading the original once for all of them, so the first view finds them cached.

Opt in by defining the public method `imageResizerWarmUrls(Media $media): array` on the model, returning the URLs as it builds them for its views (`getMediaUrl()`, `getFriendlyImageUrl()`):

```php
use Elfeffe\ImageResizer\Traits\HasImageResizer;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia
{
    use HasImageResizer, InteractsWithMedia;

    public function imageResizerWarmUrls(Media $media): array
    {
        return [
            $this->getMediaUrl($media, 320, 240, 'fit'),
            $this->getMediaUrl($media, 640, null, 'resize', null, 'image/webp'),
        ];
    }
}
```

- Every URL is read the way the route reads a request for it, so what is made is exactly the file that URL is served from: a signed project's signatures are checked, a strict project's spelling is the canonical one, and the file is kept where the route looks for it (the cache disk, or the bucket). A URL the route would refuse, that is not this image's, that is `type=original` or that is not a resizer URL is skipped. One that is cached already is left as it is.
- `WarmResizesJob` is queued on `IMAGERESIZER_QUEUE` five seconds after a media is created (after commit; the row is written before its file is), for `image/*` except SVG and GIF. It is unique per media for an hour, may run 120 seconds and is tried 3 times. It is best effort, like the placeholder job: a media that is gone, a file that is not there, is empty or cannot be decoded ends it quietly, and a resize it did not make is made by the first request, as ever. A failure of the bucket fails the job, which is tried again, as it fails a request.
- A model that does not define the method never gets a job, and nothing changes for a project that defines it on none: `HasImageResizer` has no default for it.
- `php artisan image-resizer:warm` queues the job for every existing image of the models that define the method, for the images stored before the method was there and after a project changes its sizes (only the sizes that are missing are made). `--model=` limits it to a model class or morph alias, and may be repeated.

## Blade Directives

The package provides convenient Blade directives:

### @imageResizerStyles

Includes the package CSS:

```blade
@imageResizerStyles
```

Outputs:
```html
<link rel="stylesheet" href="/vendor/image-resizer/css/image-resizer.css">
```

### @imageResizerScripts

Includes the package JavaScript:

```blade
@imageResizerScripts
```

Outputs:
```html
<script src="/vendor/image-resizer/js/image-resizer.js"></script>
```

It is a classic script, so the build is an IIFE (`vite.config.js`): it declares nothing in the page's global scope (only the
`window.__imageResizerStarted` flag) and cannot clash with another classic script's names. A bundle in ES format once
broke filament-breezy.js with "Identifier 'p' has already been declared"; `BuiltScriptIsSelfContainedTest` guards it.
After rebuilding, republish into each app (`php artisan vendor:publish --tag=image-resizer-assets --force`).

## Building Assets

The package includes its own build system for development:

### Development

```bash
cd packages/elfeffe/image-resizer
npm install
npm run dev
```

### Production Build

```bash
cd packages/elfeffe/image-resizer
npm run build
```

### Publishing Updated Assets

After making changes to the package assets:

```bash
# Build inside the package
cd packages/elfeffe/image-resizer
npm run build

# Publish to the main project
cd /path/to/your/project
php artisan vendor:publish --tag=image-resizer-assets --force
```

## Performance Optimization

### Apache/Nginx Optimization

The package includes **smart caching** that dramatically improves performance:

#### How It Works:
1. **First request**: `/image_resizer/123/w/800/h/600/resize/nail-design.jpg`
   - Processed by Laravel (dynamic)
   - Cached to filesystem: `storage/image_resizer/123/800x600/resize.jpg`
   - Returns processed image with headers

2. **Subsequent requests**: Same URL
   - ⚡ **Served directly from filesystem** (bypasses Laravel entirely)
   - **10x faster** than dynamic processing
   - **Zero server load** for cached images

#### Performance Benefits:
- 🚀 **First load**: ~200ms (Laravel processing)
- ⚡ **Cached loads**: ~20ms (direct file serving)
- 📈 **Scalability**: Handles thousands of image requests with minimal server load
- 🔄 **Automatic**: No manual cache management needed

#### Server Configuration:
- **Apache**: Use the published `.htaccess` rules (see installation)
- **Nginx**: Use the provided rewrite rules (see installation)
- **Without optimization**: Still works great, just processes every request through Laravel

### AlpineJS Integration

The package is built entirely with AlpineJS for seamless integration:
- **Zero conflicts**: Works perfectly with existing AlpineJS applications
- **Native lazy loading**: Uses browser's `loading="lazy"` attribute
- **Smart enhancement**: Automatically detects and enhances images
- **Livewire support**: Re-enhances images on Livewire navigation/updates
- **Dynamic content**: Handles images added dynamically to the DOM

### BlurHash Performance

- BlurHash is calculated asynchronously via Laravel jobs
- Processing size is limited to 128px for performance
- BlurHash generation is optimized for typical web images
- Canvas rendering is optimized for immediate display

### Browser Support

- **Modern browsers**: Full BlurHash, native lazy loading, and WebP support with AlpineJS enhancement
- **Older browsers**: Graceful fallback to JPEG images, immediate loading without lazy loading
- **No JavaScript**: Images load normally using native browser `loading="lazy"` attribute
- **Screen readers**: Full accessibility with proper alt text and semantic markup
- **Mobile**: Optimized for touch devices and variable viewports with responsive srcsets

**AlpineJS Requirement**: This package requires AlpineJS to be loaded in your application for BlurHash transitions. Images will still work without AlpineJS, but BlurHash enhancement won't be available.

## Troubleshooting

### Images Not Loading

1. **Check container dimensions**: Make sure parent containers have defined width/height
2. **Verify published assets**: Run `php artisan vendor:publish --tag=image-resizer-assets --force`
3. **Check image URLs**: Since we use real `src` attributes, verify the image URLs are accessible
4. **Test without JavaScript**: Images should load even with JS disabled thanks to native lazy loading
5. **Check console for errors**: Use browser dev tools to debug JavaScript issues
6. **Test container dimensions**: Use the debugging script provided in the "Parent Container Setup" section

**Note**: With the SEO-friendly approach, images will load using native browser lazy loading even if JavaScript fails. BlurHash is an enhancement, not a requirement.

### BlurHash Not Displaying

1. **Verify job processing**: Make sure Laravel queues are running
2. **Check media custom_properties**: Verify BlurHash data is stored
3. **Regenerate BlurHash**: Run `php artisan image-resizer:calculate-lqip`

### Performance Issues

1. **Optimize image sizes**: Use appropriate dimensions for your use case
2. **Enable WebP**: Configure WebP generation in the config
3. **Queue optimization**: Use dedicated queue for image processing

## Development

### Package Structure

```
packages/elfeffe/image-resizer/
├── src/
│   ├── Traits/HasImageResizer.php
│   ├── Jobs/CalculateLqipJob.php
│   ├── Jobs/WarmResizesJob.php
│   └── ImageResizerServiceProvider.php
├── resources/
│   ├── views/placeholder.blade.php
│   ├── css/image-resizer.css
│   └── js/image-resizer.js
├── config/image-resizer.php
└── package.json
```

### Testing

Run the package tests:

```bash
cd packages/elfeffe/image-resizer
./vendor/bin/phpunit
```

## License

MIT License. See LICENSE.md for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests
5. Submit a pull request

## Support

For issues and questions, please use the GitHub issue tracker.

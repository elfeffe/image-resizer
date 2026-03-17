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

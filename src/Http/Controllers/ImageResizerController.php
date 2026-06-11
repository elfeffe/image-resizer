<?php

namespace Elfeffe\ImageResizer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImageResizerController extends Controller
{
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private Media $media;

    public function show(Request $request)
    {
        $ext = strtolower($request->ext);
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            abort(400, 'Invalid image extension');
        }

        $safeExt = match ($ext) {
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $cacheFile = $request->img.'/'.$request->w.'x'.$request->h.'/'.$request->type.'.'.$safeExt;

        if (Storage::disk('image_resizer')->exists($cacheFile)) {
            return Response::make(Storage::disk('image_resizer')->get($cacheFile))
                ->header('Content-Type', $mime)
                ->header('Pragma', 'public')
                ->header('Cache-Control', 'public, max-age=2628000')
                ->header('X-Image-Resizer', 'cached');
        }

        $this->media = Media::findOrFail($request->img);

        if ($request->type === 'original') {
            return redirect($this->media->getFullUrl());
        }

        $imageData = $this->resolveImageData();
        if (! $imageData) {
            abort(404, 'Image file does not exist');
        }

        try {
            $encodedImage = $this->processImage($request, $imageData);

            return Response::make($encodedImage)
                ->header('Content-Type', $mime)
                ->header('Pragma', 'public')
                ->header('Cache-Control', 'public, max-age=2628000')
                ->header('Connection', 'Keep-alive')
                ->header('X-Image-Resizer', 'true');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Unable to decode input') ||
                str_contains($e->getMessage(), 'corrupted') ||
                str_contains($e->getMessage(), 'invalid')) {
                abort(404, 'Image file corrupted or invalid format');
            }

            \Log::error("Image resizer: processing error for media {$this->media->getKey()}: {$e->getMessage()}");
            abort(500, 'Error processing image');
        }
    }

    /**
     * Resolve image data as a file path (local) or binary string (remote).
     * Intervention's read() handles both transparently.
     */
    protected function resolveImageData(): ?string
    {
        $diskDriver = config("filesystems.disks.{$this->media->disk}.driver");

        if ($diskDriver === 'local') {
            $localPath = $this->media->getPath();

            return file_exists($localPath) ? $localPath : null;
        }

        return $this->readFromRemoteDisk() ?? $this->readViaHttp();
    }

    /**
     * Read remote media binary via the Storage disk adapter (S3, etc.).
     */
    protected function readFromRemoteDisk(): ?string
    {
        try {
            $contents = Storage::disk($this->media->disk)
                ->get($this->media->getPathRelativeToRoot());

            return ($contents !== null && $contents !== '') ? $contents : null;
        } catch (\Exception $e) {
            \Log::warning("Image resizer: Storage disk read failed for media {$this->media->getKey()}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Fallback: read remote media binary via HTTP from the public URL.
     */
    protected function readViaHttp(): ?string
    {
        try {
            $url = $this->media->getFullUrl();
            if (! $url) {
                return null;
            }

            $appUrl = rtrim(config('app.url', ''), '/');
            if ($appUrl && str_starts_with($url, $appUrl)) {
                \Log::warning("Image resizer: skipping self-referencing HTTP download for media {$this->media->getKey()}");

                return null;
            }

            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                \Log::warning("Image resizer: HTTP download failed ({$response->status()}) from {$url}");

                return null;
            }

            return $response->body();
        } catch (\Exception $e) {
            \Log::error("Image resizer: HTTP download exception for media {$this->media->getKey()}: {$e->getMessage()}");

            return null;
        }
    }

    public function processImage(Request $request, string $imageData)
    {
        $height = $request->h === 'null' ? null : $request->h;

        $safeExt = match (strtolower($request->ext)) {
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        $file = $request->img.'/'.$request->w.'x'.$request->h.'/'.$request->type.'.'.$safeExt;

        $manager = new ImageManager(Driver::class);
        // intervention/image v4: read() -> decode() (which returns the
        // decoded binary; chained to create the image).
        $image = $manager->createImage($manager->decode($imageData));

        // If height is null, calculate it based on aspect ratio
        if ($height === null) {
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            $aspectRatio = $originalHeight / $originalWidth;
            $height = round($request->w * $aspectRatio);
        }

        // Process the image based on request type.
        if ($request->type === 'resize') {
            $image->scaleDown(width: $request->w, height: $height);
        } elseif ($request->type === 'fit') {
            // fit() not available; use cover() to crop+resize.
            $image->cover($request->w, $height, 'center');
        }

        $quality = 82;

        // v4: toPng/toJpeg/toWebp -> encode(Format::X).
        $encoded = match ($safeExt) {
            'png' => $image->encode(new Format(Format::PNG, interlaced: true)),
            'webp' => $image->encode(new Format(Format::WEBP, quality: $quality)),
            default => $image->encode(new Format(Format::JPEG, quality: $quality, progressive: true)),
        };

        // Save the encoded image to the storage disk.
        Storage::disk('image_resizer')->put($file, (string) $encoded);

        return $encoded;
    }
}

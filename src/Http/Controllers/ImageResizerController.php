<?php

namespace Elfeffe\ImageResizer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\ImageDecoderException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
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

        $manager = ImageManager::usingDriver(Driver::class);

        try {
            $image = $this->decodeSourceImage($manager);
        } catch (ImageDecoderException $e) {
            abort(404, 'Image file corrupted or invalid format');
        }

        if (! $image) {
            abort(404, 'Image file does not exist');
        }

        try {
            $encodedImage = $this->processImage($request, $image);
        } catch (ImageDecoderException $e) {
            abort(404, 'Image file corrupted or invalid format');
        } catch (\Exception $e) {
            \Log::error("Image resizer: processing error for media {$this->media->getKey()}: {$e->getMessage()}");
            abort(500, 'Error processing image');
        }

        return Response::make((string) $encodedImage)
            ->header('Content-Type', $mime)
            ->header('Pragma', 'public')
            ->header('Cache-Control', 'public, max-age=2628000')
            ->header('Connection', 'Keep-alive')
            ->header('X-Image-Resizer', 'true');
    }

    /**
     * Decode the media original using the explicit Intervention v4 API for the
     * known source type. Local disks use decodePath() so paths containing
     * non-ASCII bytes are not classified as binary; remote disks/HTTP use
     * decodeBinary() so already-fetched bytes are parsed directly.
     */
    protected function decodeSourceImage(ImageManager $manager): ?ImageInterface
    {
        $diskDriver = config("filesystems.disks.{$this->media->disk}.driver");

        if ($diskDriver === 'local') {
            $localPath = $this->media->getPath();

            return is_file($localPath) ? $manager->decodePath($localPath) : null;
        }

        $binary = $this->readFromRemoteDisk() ?? $this->readViaHttp();

        return filled($binary) ? $manager->decodeBinary($binary) : null;
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

    public function processImage(Request $request, ImageInterface $image): EncodedImageInterface
    {
        $width = (int) $request->w;
        $height = $request->h === 'null' ? null : (int) $request->h;

        $safeExt = match (strtolower($request->ext)) {
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        $file = $request->img.'/'.$request->w.'x'.$request->h.'/'.$request->type.'.'.$safeExt;

        // If height is null, calculate it based on aspect ratio
        if ($height === null) {
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            $aspectRatio = $originalHeight / $originalWidth;
            $height = (int) round($width * $aspectRatio);
        }

        // Process the image based on request type.
        if ($request->type === 'resize') {
            $image->scaleDown(width: $width, height: $height);
        } elseif ($request->type === 'fit') {
            // fit() not available; use cover() to crop+resize.
            $image->cover($width, $height, 'center');
        }

        $quality = 82;

        $encoded = match ($safeExt) {
            'png' => $image->encode(new PngEncoder(interlaced: true)),
            'webp' => $image->encode(new WebpEncoder(quality: $quality)),
            default => $image->encode(new JpegEncoder(quality: $quality, progressive: true)),
        };

        // Save the encoded image to the storage disk.
        Storage::disk('image_resizer')->put($file, (string) $encoded);

        return $encoded;
    }
}
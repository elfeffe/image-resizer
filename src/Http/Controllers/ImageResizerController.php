<?php

namespace Elfeffe\ImageResizer\Http\Controllers;

use Aws\S3\Exception\S3Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
use League\Flysystem\UnableToReadFile;
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

        if ($this->storageDriver() === 's3') {
            return $this->showFromObjectStorage($request);
        }

        return $this->showFromLocalDisk($request);
    }

    protected function storageDriver(): string
    {
        return (string) config('image-resizer.storage.driver', 'local');
    }

    /**
     * Local driver: unchanged historical flow. Cached files keep the flat
     * {id}/{w}x{h}/{type}.{ext} layout the Apache/Nginx fast paths rely on,
     * and a cache hit is served without touching the database.
     */
    protected function showFromLocalDisk(Request $request)
    {
        $mime = $this->responseMime($request);
        $cacheFile = $this->localCachePath($request);
        $disk = Storage::disk('image_resizer');

        if ($disk->exists($cacheFile)) {
            return Response::make($disk->get($cacheFile))
                ->header('Content-Type', $mime)
                ->header('Pragma', 'public')
                ->header('Cache-Control', 'public, max-age=2628000')
                ->header('X-Image-Resizer', 'cached');
        }

        $this->media = Media::findOrFail($request->img);

        if ($request->type === 'original') {
            return redirect($this->media->getFullUrl());
        }

        $encodedImage = $this->generateEncodedImage($request);

        $disk->put($cacheFile, (string) $encodedImage);

        return Response::make((string) $encodedImage)
            ->header('Content-Type', $mime)
            ->header('Pragma', 'public')
            ->header('Cache-Control', 'public, max-age=2628000')
            ->header('Connection', 'Keep-alive')
            ->header('X-Image-Resizer', 'true');
    }

    /**
     * S3 driver: resizes live in object storage (B2/R2/S3), never on local disk.
     * cdn_origin: GET-first (no exists() check), generate on miss, stream bytes —
     *             the CDN origin-pulls this endpoint, so it never redirects.
     * redirect:   301 to the public serve URL once the object exists.
     */
    protected function showFromObjectStorage(Request $request)
    {
        $this->media = Media::findOrFail($request->img);

        if ($request->type === 'original') {
            return redirect($this->media->getFullUrl())->header('X-Image-Resizer', 'redirect');
        }

        $mime = $this->responseMime($request);
        $disk = Storage::disk((string) config('image-resizer.storage.disk', 'image_resizer'));
        $key = $this->objectStoragePath($request);
        $serveUrl = rtrim((string) config('image-resizer.serve.url', ''), '/');
        $isRedirectMode = (string) config('image-resizer.serve.mode', 'cdn_origin') === 'redirect';

        if ($isRedirectMode && $serveUrl === '') {
            Log::error('Image resizer: serve mode "redirect" requires IMAGERESIZER_SERVE_URL to be set.');
            abort(500, 'Image resizer is misconfigured');
        }

        $targetUrl = $serveUrl.'/image_resizer/'.$key;

        if ($isRedirectMode && $this->objectExists($disk, $key)) {
            return redirect($targetUrl, 301)->header('X-Image-Resizer', 'redirect');
        }

        $contents = $this->readObject($disk, $key);
        $generated = false;

        if ($contents === null) {
            $contents = (string) $this->generateEncodedImage($request);
            $this->writeObject($disk, $key, $contents, $mime);
            $generated = true;

            if ($isRedirectMode) {
                return redirect($targetUrl, 301)->header('X-Image-Resizer', 'redirect');
            }
        }

        return Response::make($contents)
            ->header('Content-Type', $mime)
            ->header('Pragma', 'public')
            ->header('Cache-Control', 'public, max-age=2628000')
            ->header('Connection', 'Keep-alive')
            ->header('X-Image-Resizer', $generated ? 'object-storage-generated' : 'object-storage-hit');
    }

    /**
     * GET-first read. Flysystem wraps every S3 read failure (missing object,
     * network, credentials) in UnableToReadFile, so only a wrapped 404 means
     * "missing"; anything else is a real failure and must stay loud.
     */
    protected function readObject(Filesystem $disk, string $key): ?string
    {
        try {
            $contents = $disk->get($key);

            return ($contents !== null && $contents !== '') ? $contents : null;
        } catch (UnableToReadFile $e) {
            if ($this->isMissingObject($e)) {
                return null;
            }

            Log::error("Image resizer: object storage read failed for {$key}: {$e->getMessage()}");
            abort(500, 'Image storage is unavailable');
        }
    }

    protected function isMissingObject(UnableToReadFile $e): bool
    {
        $previous = $e->getPrevious();

        return $previous instanceof S3Exception && $previous->getStatusCode() === 404;
    }

    protected function objectExists(Filesystem $disk, string $key): bool
    {
        try {
            return $disk->exists($key);
        } catch (\Throwable $e) {
            Log::error("Image resizer: object storage exists check failed for {$key}: {$e->getMessage()}");
            abort(500, 'Image storage is unavailable');
        }
    }

    protected function writeObject(Filesystem $disk, string $key, string $contents, string $mime): void
    {
        try {
            $disk->put($key, $contents, [
                'visibility' => (string) config('image-resizer.storage.visibility', 'public'),
                'ContentType' => $mime,
                'CacheControl' => 'public, max-age=2628000',
            ]);
        } catch (\Throwable $e) {
            Log::error("Image resizer: object storage write failed for {$key}: {$e->getMessage()}");
            abort(500, 'Image storage is unavailable');
        }
    }

    protected function generateEncodedImage(Request $request): EncodedImageInterface
    {
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
            return $this->processImage($request, $image);
        } catch (ImageDecoderException $e) {
            abort(404, 'Image file corrupted or invalid format');
        } catch (\Exception $e) {
            Log::error("Image resizer: processing error for media {$this->media->getKey()}: {$e->getMessage()}");
            abort(500, 'Error processing image');
        }
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
            Log::warning("Image resizer: Storage disk read failed for media {$this->media->getKey()}: {$e->getMessage()}");

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
                Log::warning("Image resizer: skipping self-referencing HTTP download for media {$this->media->getKey()}");

                return null;
            }

            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                Log::warning("Image resizer: HTTP download failed ({$response->status()}) from {$url}");

                return null;
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::error("Image resizer: HTTP download exception for media {$this->media->getKey()}: {$e->getMessage()}");

            return null;
        }
    }

    public function processImage(Request $request, ImageInterface $image): EncodedImageInterface
    {
        $width = (int) $request->w;
        $height = $request->h === 'null' ? null : (int) $request->h;

        if ($height === null) {
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            $aspectRatio = $originalHeight / $originalWidth;
            $height = (int) round($width * $aspectRatio);
        }

        if ($request->type === 'resize') {
            $image->scaleDown(width: $width, height: $height);
        } elseif ($request->type === 'fit') {
            $image->cover($width, $height, 'center');
        }

        $quality = 82;

        return match ($this->safeExt($request)) {
            'png' => $image->encode(new PngEncoder(interlaced: true)),
            'webp' => $image->encode(new WebpEncoder(quality: $quality)),
            default => $image->encode(new JpegEncoder(quality: $quality, progressive: true)),
        };
    }

    protected function localCachePath(Request $request): string
    {
        return $request->img.'/'.$request->w.'x'.$request->h.'/'.$request->type.'.'.$this->safeExt($request);
    }

    /**
     * URL-shaped key relative to the disk root, so serve.url + URL path resolves
     * to the object for both CDN origin-pull and bare-bucket layouts.
     */
    protected function objectStoragePath(Request $request): string
    {
        return $request->img.'/w/'.$request->w.'/h/'.$request->h.'/'.$request->type.'/'.$request->path.'.'.$this->safeExt($request);
    }

    protected function safeExt(Request $request): string
    {
        return match (strtolower($request->ext)) {
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };
    }

    protected function responseMime(Request $request): string
    {
        return match (strtolower($request->ext)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}

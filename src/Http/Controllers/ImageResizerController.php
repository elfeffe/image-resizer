<?php

namespace Elfeffe\ImageResizer\Http\Controllers;

use Aws\S3\Exception\S3Exception;
use Elfeffe\ImageResizer\Support\Access;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\ImageDecoderException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use League\Flysystem\UnableToReadFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageResizerController extends Controller
{
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    private Media $media;

    public function show(Request $request)
    {
        $this->admit($request);

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
     * What a request must satisfy before the resizer reads the database or a disk. In a strict project (one that signs, or says
     * `strict`): the signature when it signs (403), a type the controller implements (422), `original` only where the project serves
     * it (403), a size within the limit (422); a refusal costs one HMAC. Any other project is served as the package always did.
     *
     * Every project gets one thing: only the route's own segments are read. Request::__get() prefers a query-string value to a route
     * parameter, so `?type=original` or `?img=7` would otherwise steer the code below: past a signature, and past a host's own check
     * of the route's `img`. The segments are written back; in a strict project in their canonical spelling (`w` and `h` as plain
     * numbers, 0 and `null` meaning the source's own size, `img` without leading zeros), so a hundred spellings of one resize are one
     * cache entry.
     */
    protected function admit(Request $request): void
    {
        $segment = static fn (string $name): string => (string) $request->route()?->parameter($name, '');
        [$img, $w, $h, $type, $path, $ext] = array_map($segment, ['img', 'w', 'h', 'type', 'path', 'ext']);

        if (Access::strict()) {
            if (Access::signed() && ! Access::accepts($request->query('s'), $img, $w, $h, $type, $path.'.'.$ext)) {
                abort(403, 'Invalid image signature');
            }

            if (! in_array($type, Access::TYPES, true)) {
                abort(422, 'Unsupported image type');
            }

            if ($type === 'original' && ! Access::allowsOriginal()) {
                abort(403, 'Original images are not served');
            }

            // A width or height that is not a positive number has always meant "the source's own": a stale `h/None` or `w/0` URL still works.
            $width = (int) $w;
            $height = $h === 'null' ? 0 : (int) $h;

            if (max($width, $height) > Access::maxDimension()) {
                abort(422, 'Image size out of range');
            }

            $img = ctype_digit($img) ? (string) (int) $img : $img;
            $w = (string) max(0, $width);
            $h = $height > 0 ? (string) $height : 'null';
        }

        $request->merge(compact('img', 'w', 'h', 'type', 'path', 'ext'));
    }

    /**
     * Local driver: unchanged historical flow. Cached files keep the flat
     * {id}/{w}x{h}/{type}.{ext} layout the Apache/Nginx fast paths rely on,
     * and a cache hit is served without touching the database. In a project
     * that signs, {id} is Access::folder()'s token, so no fast path can serve
     * a file without the signature the controller checked.
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
     *
     * Storage is consulted before the database, mirroring the local driver's
     * cache-first flow: an already-generated resize is served without a query,
     * and keeps serving after its media row is deleted or recycled. The media
     * is only resolved when the object is missing and must be generated.
     */
    protected function showFromObjectStorage(Request $request)
    {
        if ($request->type === 'original') {
            $this->media = Media::findOrFail($request->img);

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

        // A hit is handed to the client without ever being held. $disk->get()
        // pulled the whole object into a PHP string first: a 4.3 MB image cost
        // an Octane worker 23 MB it never gave back, and on neoteo a quarter of
        // all requests are these. Octane's RoadRunner client sends a
        // StreamedResponse without buffering it, so the bytes go from Backblaze
        // to the reader and nothing is kept here or written to local disk.
        if (! $isRedirectMode) {
            $stream = $this->openObject($disk, $key);

            if ($stream !== null) {
                return $this->streamObject($stream, $mime);
            }
        }

        // Only a miss reaches here, so the variant is made once, saved to
        // object storage, and every later request for it is streamed above.
        $this->media = Media::findOrFail($request->img);
        $contents = (string) $this->generateEncodedImage($request);
        $this->writeObject($disk, $key, $contents, $mime);

        if ($isRedirectMode) {
            return redirect($targetUrl, 301)->header('X-Image-Resizer', 'redirect');
        }

        return Response::make($contents)
            ->header('Content-Type', $mime)
            ->header('Pragma', 'public')
            ->header('Cache-Control', 'public, max-age=2628000')
            ->header('X-Image-Resizer', 'object-storage-generated');
    }

    /**
     * GET-first read. Flysystem wraps every S3 read failure (missing object,
     * network, credentials) in UnableToReadFile, so only a wrapped 404 means
     * "missing"; anything else is a real failure and must stay loud.
     */
    /**
     * The object as a handle, or null when it is not there yet.
     *
     * @return resource|null
     */
    protected function openObject(Filesystem $disk, string $key)
    {
        try {
            $stream = $disk->readStream($key);

            return is_resource($stream) ? $stream : null;
        } catch (UnableToReadFile $e) {
            if ($this->isMissingObject($e)) {
                return null;
            }

            Log::error("Image resizer: object storage read failed for {$key}: {$e->getMessage()}");
            abort(500, 'Image storage is unavailable');
        }
    }

    /** @param  resource  $stream */
    protected function streamObject($stream, string $mime): StreamedResponse
    {
        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Pragma' => 'public',
            'Cache-Control' => 'public, max-age=2628000',
            'X-Image-Resizer' => 'object-storage-hit',
        ]);
    }

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
        // Metadata goes once the orientation it describes has been applied (autoOrientation is on).
        $manager = ImageManager::usingDriver(Driver::class, strip: true);

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

        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Degenerate dimensions (0, negative, or a non-numeric segment such as a
        // stale "h/None" URL) are not usable resize targets. Rather than fail the
        // request, fall back to the image's real aspect ratio so a proportionally
        // correct image is always produced. A zero-width source image has no
        // aspect ratio to derive from, so fall back to a square box instead of
        // dividing by zero.
        if ($width <= 0) {
            $width = $originalWidth;
        }

        if ($height === null || $height <= 0) {
            $height = $originalWidth > 0
                ? max(1, (int) round($width * ($originalHeight / $originalWidth)))
                : max(1, $width);
        }

        if ($request->type === 'resize') {
            $image->scaleDown(width: $width, height: $height);
        } elseif ($request->type === 'fit' && Access::strict()) {
            // Never above the source: a box larger than the image crops it to the box's ratio at its own size.
            $image->coverDown($width, $height, 'center');
        } elseif ($request->type === 'fit') {
            $image->cover($width, $height, 'center');
        }

        $quality = (int) config('image-resizer.encode.quality', 78);

        return match ($this->safeExt($request)) {
            'png' => $image->encode(new PngEncoder(interlaced: true)),
            'webp' => $image->encode(new WebpEncoder(quality: $quality)),
            'avif' => $this->encodeAvif($image),
            default => $image->encode(new JpegEncoder(quality: $quality, progressive: true)),
        };
    }

    /** AVIF through Imagick: libheif's AV1 speed (0 slowest and best, 10 fastest) is set on the handle it encodes with. */
    private function encodeAvif(ImageInterface $image): EncodedImageInterface
    {
        $native = $image->core()->native();

        if ($native instanceof \Imagick) {
            $native->setOption('heic:speed', (string) config('image-resizer.encode.avif_speed', 7));
        }

        return $image->encode(new AvifEncoder(quality: (int) config('image-resizer.encode.avif_quality', 60)));
    }

    protected function localCachePath(Request $request): string
    {
        return Access::folder($request->img).'/'.$request->w.'x'.$request->h.'/'.$request->type.'.'.$this->safeExt($request);
    }

    /**
     * URL-shaped key relative to the disk root, so serve.url + URL path resolves
     * to the object for both CDN origin-pull and bare-bucket layouts.
     */
    protected function objectStoragePath(Request $request): string
    {
        return Access::folder($request->img).'/w/'.$request->w.'/h/'.$request->h.'/'.$request->type.'/'.$request->path.'.'.$this->safeExt($request);
    }

    protected function safeExt(Request $request): string
    {
        return match (strtolower($request->ext)) {
            'png' => 'png',
            'webp' => 'webp',
            'avif' => 'avif',
            default => 'jpg',
        };
    }

    protected function responseMime(Request $request): string
    {
        return match (strtolower($request->ext)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'image/jpeg',
        };
    }
}

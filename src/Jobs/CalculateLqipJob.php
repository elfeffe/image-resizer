<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Jobs;

use Bepsvpt\Blurhash\Facades\BlurHash;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * A placeholder (size, dominant colour, BlurHash) for an image. Best effort: nothing waits for it, so a file that is not there (the
 * row is written before the file is, a fixture has none, the object was deleted), is empty, or cannot be decoded ends the job
 * quietly instead of failing it. The next save of the model, or `image-resizer:calculate-lqip`, queues the media again.
 */
class CalculateLqipJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 60;

    /**
     * The number of seconds the unique lock should be maintained.
     *
     * @var int
     */
    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $mediaId,
        protected bool $force = false,
    ) {}

    public function uniqueId(): string
    {
        return $this->mediaId.($this->force ? ':force' : '');
    }

    public function tries(): int
    {
        return $this->force ? 15 : 3;
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("image-resizer:{$this->mediaId}"))
                ->releaseAfter(5)
                ->expireAfter($this->timeout),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $media = Media::find($this->mediaId);

        if (! $media || ! $this->isImageFile($media->mime_type)) {
            return;
        }

        if (! $this->force
            && $media->hasCustomProperty('lqip_color')
            && $media->hasCustomProperty('blurhash')
            && $media->hasCustomProperty('image_resizer.width')
            && $media->hasCustomProperty('image_resizer.height')) {
            return;
        }

        $source = $this->getImageSource($media);

        if ($source === null) {
            Log::debug("[image-resizer] LQIP skipped for media {$this->mediaId}: its file is not there, or is empty.");

            return;
        }

        [$imageSource, $binary] = $source;

        if ($this->force
            || ! $media->hasCustomProperty('image_resizer.width')
            || ! $media->hasCustomProperty('image_resizer.height')) {
            try {
                $manager = ImageManager::usingDriver(Driver::class);
                $image = $binary
                    ? $manager->decodeBinary($imageSource)
                    : $manager->decodePath($imageSource);
            } catch (Throwable $e) {
                Log::info("[image-resizer] LQIP skipped for media {$this->mediaId}: its file cannot be decoded ({$e->getMessage()}).");

                return;
            }

            $media
                ->setCustomProperty('image_resizer.width', $image->width())
                ->setCustomProperty('image_resizer.height', $image->height());

            unset($image);
        }

        if ($this->force || ! $media->hasCustomProperty('lqip_color')) {
            $dominantColor = $this->calculateDominantColor($imageSource, $binary);

            if ($dominantColor) {
                $media->setCustomProperty('lqip_color', $dominantColor);
            }
        }

        if ($this->force || ! $media->hasCustomProperty('blurhash')) {
            $blurHash = $this->generateBlurHash($imageSource, $binary);

            if ($blurHash) {
                $media->setCustomProperty('blurhash', $blurHash);
            }
        }

        $media->save();
    }

    /**
     * The file's path (or, on a disk that is not local, its bytes) and whether those are bytes; null when the file is not there or is
     * empty.
     *
     * @return array{0: string, 1: bool}|null
     */
    private function getImageSource(Media $media): ?array
    {
        if (config("filesystems.disks.{$media->disk}.driver") === 'local') {
            $path = $media->getPath();

            return is_file($path) && filesize($path) > 0 ? [$path, false] : null;
        }

        try {
            $contents = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
        } catch (Throwable) {
            // An object that is not there: a disk configured with `throw => true` says so with an exception, the others with null.
            return null;
        }

        return is_string($contents) && $contents !== '' ? [$contents, true] : null;
    }

    /**
     * Generate BlurHash for the given image
     */
    protected function generateBlurHash(string $imageSource, bool $binary = false): ?string
    {
        try {
            // Generate BlurHash with optimal settings for proper blur representation
            // ComponentX and ComponentY control detail level (4-9 recommended)
            // Higher values = more detail but larger hash strings
            $blurHash = app('blurhash')
                ->setComponentX(6)  // Horizontal detail
                ->setComponentY(4)  // Vertical detail
                ->setMaxSize(128)   // Resize image for processing (balance speed vs quality)
                ->encode($binary
                    ? 'data://application/octet-stream;base64,'.base64_encode($imageSource)
                    : $imageSource);

            return $blurHash;

        } catch (Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * Check if the media file is an image
     */
    protected function isImageFile(?string $mimeType): bool
    {
        if (! $mimeType) {
            return false;
        }

        return str_starts_with($mimeType, 'image/') &&
               ! in_array($mimeType, ['image/svg+xml', 'image/gif']); // Skip SVG and GIF
    }

    /**
     * Calculate the dominant color of an image
     */
    protected function calculateDominantColor(string $imageSource, bool $binary = false): ?string
    {
        try {
            $imageInfo = $binary
                ? getimagesizefromstring($imageSource)
                : getimagesize($imageSource);

            if (! $imageInfo) {
                return '#f0f0f0';
            }

            if ($binary) {
                $image = imagecreatefromstring($imageSource);
            } else {
                $image = match ($imageInfo['mime']) {
                    'image/jpeg' => imagecreatefromjpeg($imageSource),
                    'image/png' => imagecreatefrompng($imageSource),
                    'image/webp' => imagecreatefromwebp($imageSource),
                    default => null,
                };
            }

            if (! $image) {
                return '#f0f0f0';
            }

            // Resize to small image for faster processing
            $smallImage = imagecreatetruecolor(50, 50);
            imagecopyresampled($smallImage, $image, 0, 0, 0, 0, 50, 50, imagesx($image), imagesy($image));

            // Sample colors
            $colors = [];
            $totalPixels = 0;

            for ($x = 0; $x < 50; $x++) {
                for ($y = 0; $y < 50; $y++) {
                    $rgb = imagecolorat($smallImage, $x, $y);

                    // Extract RGB values
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    $colors[] = ['r' => $r, 'g' => $g, 'b' => $b];
                    $totalPixels++;
                }
            }

            // Clean up memory
            imagedestroy($image);
            imagedestroy($smallImage);

            if ($totalPixels === 0) {
                return '#f0f0f0';
            }

            // Calculate average color
            $avgR = array_sum(array_column($colors, 'r')) / $totalPixels;
            $avgG = array_sum(array_column($colors, 'g')) / $totalPixels;
            $avgB = array_sum(array_column($colors, 'b')) / $totalPixels;

            // Convert to hex color
            $hex = sprintf('#%02x%02x%02x',
                (int) round($avgR),
                (int) round($avgG),
                (int) round($avgB)
            );

            return $hex;

        } catch (Exception $e) {
            report($e);

            return '#f0f0f0';
        }
    }
}

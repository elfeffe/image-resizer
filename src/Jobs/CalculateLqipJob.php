<?php

namespace Elfeffe\ImageResizer\Jobs;

use Bepsvpt\Blurhash\Facades\BlurHash;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CalculateLqipJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * The number of seconds the unique lock should be maintained.
     *
     * @var int
     */
    public $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $mediaId
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Load the media item
            $media = Media::find($this->mediaId);

            if (! $media) {
                // Media was likely deleted between job dispatch and execution
                // This is normal behavior, just exit silently
                return;
            }

            // Skip if both LQIP color and BlurHash already exist
            if ($media->hasCustomProperty('lqip_color') && $media->hasCustomProperty('blurhash')) {
                return;
            }

            // Get the media file path
            $imagePath = $media->getPath();

            if (! file_exists($imagePath)) {
                throw new Exception("Media file not found: {$imagePath}");
            }

            // Only process image files
            if (! $this->isImageFile($media->mime_type)) {
                return;
            }

            // Calculate dominant color if not exists
            if (! $media->hasCustomProperty('lqip_color')) {
                $dominantColor = $this->calculateDominantColor($imagePath);
                if ($dominantColor) {
                    $media->setCustomProperty('lqip_color', $dominantColor);
                }
            }

            // Generate BlurHash if not exists
            if (! $media->hasCustomProperty('blurhash')) {
                $blurHash = $this->generateBlurHash($imagePath);
                if ($blurHash) {
                    $media->setCustomProperty('blurhash', $blurHash);
                }
            }

            // Save the media with updated custom properties
            $media->save();

        } catch (Exception $e) {
            // Silently ignore errors - LQIP is non-critical functionality
        }
    }

    /**
     * Generate BlurHash for the given image
     */
    protected function generateBlurHash(string $imagePath): ?string
    {
        try {
            // Generate BlurHash with optimal settings for proper blur representation
            // ComponentX and ComponentY control detail level (4-9 recommended)
            // Higher values = more detail but larger hash strings
            $blurHash = app('blurhash')
                ->setComponentX(6)  // Horizontal detail
                ->setComponentY(4)  // Vertical detail
                ->setMaxSize(128)   // Resize image for processing (balance speed vs quality)
                ->encode($imagePath);

            return $blurHash;

        } catch (Exception $e) {
            // Silently ignore - BlurHash is non-critical
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
    protected function calculateDominantColor(string $imagePath): ?string
    {
        try {
            // Get image info first
            $imageInfo = getimagesize($imagePath);
            if (! $imageInfo) {
                return '#f0f0f0';
            }

            // Create image resource from file
            $image = null;
            switch ($imageInfo['mime']) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($imagePath);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($imagePath);
                    break;
                default:
                    return '#f0f0f0';
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
            // Fallback: return a neutral color
            return '#f0f0f0';
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        // Silently ignore - LQIP is non-critical functionality
    }
}

<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Commands;

use Elfeffe\ImageResizer\Jobs\CalculateLqipJob;
use Elfeffe\ImageResizer\Traits\HasImageResizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CalculateLqipCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'image-resizer:calculate-lqip 
                           {--force : Recalculate LQIP data even if it already exists}
                           {--limit=100 : Number of media items to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate LQIP colors and BlurHash for media items from models using HasImageResizer trait';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        $this->info('Starting LQIP data calculation (colors and BlurHash)...');

        $query = Media::query()->whereHasMorph('model', '*', function (Builder $query, string $type): void {
            $traits = class_uses_recursive($type);

            if (! in_array(HasImageResizer::class, $traits, true)) {
                $query->whereKey(-1);
            }
        });

        // If not forcing, only process media missing LQIP color or BlurHash.
        // A missing JSON key resolves to NULL via json_extract, so whereNull
        // matches both absent keys and explicit nulls.
        if (! $force) {
            $query->where(function (Builder $query): void {
                $query->whereNull('custom_properties')
                    ->orWhereNull('custom_properties->lqip_color')
                    ->orWhereNull('custom_properties->blurhash')
                    ->orWhereNull('custom_properties->image_resizer->width')
                    ->orWhereNull('custom_properties->image_resizer->height');
            });
        }

        $mediaItems = $query->limit($limit)->get();

        if ($mediaItems->isEmpty()) {
            $this->info('No media items found to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$mediaItems->count()} media items...");

        $progressBar = $this->output->createProgressBar($mediaItems->count());
        $progressBar->start();

        $processed = 0;
        $skipped = 0;

        foreach ($mediaItems as $media) {
            // Skip non-image files
            if (! str_starts_with($media->mime_type ?? '', 'image/')
                || in_array($media->mime_type, ['image/svg+xml', 'image/gif'], true)) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Skip if both LQIP color and BlurHash already exist and not forcing
            if (! $force
                && $media->hasCustomProperty('lqip_color')
                && $media->hasCustomProperty('blurhash')
                && $media->hasCustomProperty('image_resizer.width')
                && $media->hasCustomProperty('image_resizer.height')) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Dispatch the job
            CalculateLqipJob::dispatch($media->id, $force)
                ->onQueue((string) config('image-resizer.queue'));
            $processed++;

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Queued {$processed} media items for LQIP data calculation.");
        
        if ($skipped > 0) {
            $this->info("Skipped {$skipped} items (already processed or not images).");
        }

        $this->info('Jobs have been queued. Run your queue worker to process them.');
        $this->info('This will generate both dominant colors and BlurHash placeholders for improved loading experience.');

        return self::SUCCESS;
    }
}

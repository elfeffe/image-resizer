<?php

namespace Elfeffe\ImageResizer\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Elfeffe\ImageResizer\Jobs\CalculateLqipJob;
use Elfeffe\ImageResizer\Traits\HasImageResizer;

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
        $force = $this->option('force');
        $limit = (int) $this->option('limit');

        $this->info('Starting LQIP data calculation (colors and BlurHash)...');

        // Query media items that belong to models using HasImageResizer trait
        $query = Media::whereHasMorph('model', '*', function ($query, $type) {
            // Check if the model class uses HasImageResizer trait
            $traits = class_uses_recursive($type);
            return in_array(HasImageResizer::class, $traits);
        });

        // If not forcing, only process media without complete LQIP data
        if (!$force) {
            $query->where(function ($query) {
                $query->whereJsonDoesntContain('custom_properties->lqip_color', null)
                      ->orWhereJsonDoesntContain('custom_properties->blurhash', null)
                      ->orWhereNull('custom_properties');
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
            if (!str_starts_with($media->mime_type ?? '', 'image/')) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Skip if both LQIP color and BlurHash already exist and not forcing
            if (!$force && 
                $media->hasCustomProperty('lqip_color') && 
                $media->hasCustomProperty('blurhash')) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Dispatch the job
            CalculateLqipJob::dispatch($media->id)->onQueue('default');
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

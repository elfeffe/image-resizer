<?php

namespace Elfeffe\ImageResizer\Observers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Elfeffe\ImageResizer\Jobs\CalculateLqipJob;
use Elfeffe\ImageResizer\Traits\HasImageResizer;

class MediaObserver
{
    /**
     * Handle the Media "created" event.
     */
    public function created(Media $media): void
    {
        // Only process media for models that use HasImageResizer trait
        if ($this->modelUsesImageResizer($media)) {
            // Dispatch job to calculate LQIP color asynchronously
            CalculateLqipJob::dispatch($media->id)
                ->onQueue('default')
                ->delay(now()->addSeconds(5)); // Small delay to ensure file is fully processed
        }
    }

    /**
     * Check if the model associated with this media uses HasImageResizer trait
     */
    protected function modelUsesImageResizer(Media $media): bool
    {
        $model = $media->model;
        
        if (!$model) {
            return false;
        }
        
        // Check if the model class uses the HasImageResizer trait
        $traits = class_uses_recursive(get_class($model));
        
        return in_array(HasImageResizer::class, $traits);
    }
} 

<?php

namespace Elfeffe\ImageResizer\Observers;

use Elfeffe\ImageResizer\Support\QueuesMissingLqipData;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaObserver
{
    public function __construct(private QueuesMissingLqipData $queuesMissingLqipData) {}

    /**
     * Handle the Media "created" event.
     */
    public function created(Media $media): void
    {
        $this->queuesMissingLqipData->forMedia($media, delay: true);
    }
}

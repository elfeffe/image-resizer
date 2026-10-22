<?php

namespace Elfeffe\ImageResizer\Observers;

use Elfeffe\ImageResizer\Support\QueuesMissingLqipData;
use Elfeffe\ImageResizer\Support\QueuesWarmResizes;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaObserver
{
    public function __construct(
        private QueuesMissingLqipData $queuesMissingLqipData,
        private QueuesWarmResizes $queuesWarmResizes,
    ) {}

    /**
     * Handle the Media "created" event.
     */
    public function created(Media $media): void
    {
        $this->queuesMissingLqipData->forMedia($media, delay: true);
        $this->queuesWarmResizes->forMedia($media, delay: true);
    }
}

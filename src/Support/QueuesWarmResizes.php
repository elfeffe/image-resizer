<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Support;

use Elfeffe\ImageResizer\Jobs\WarmResizesJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Queues the preparation of the resizes of a media whose model opted in, by defining the public method
 * `imageResizerWarmUrls(Media $media): array` (see WarmResizesJob). A model that does not define it never gets a job.
 */
class QueuesWarmResizes
{
    /** The method a model defines to opt in. */
    public const string METHOD = 'imageResizerWarmUrls';

    /** @param  object|string|null  $model  a model or its class: a class that does not exist does not opt in */
    public static function optsIn(object|string|null $model): bool
    {
        return $model !== null && method_exists($model, self::METHOD);
    }

    public function forMedia(Media $media, bool $delay = false): void
    {
        if ($this->isSupportedImage($media) && self::optsIn($media->model)) {
            $this->dispatch($media->id, $delay);
        }
    }

    /**
     * The delay is for a media just created: its row is written before its file is (see CalculateLqipJob), and a job that ran first
     * would find no file to prepare anything from.
     */
    public function dispatch(int $mediaId, bool $delay = false): void
    {
        $dispatch = WarmResizesJob::dispatch($mediaId)
            ->onQueue((string) config('image-resizer.queue'))
            ->afterCommit();

        if ($delay) {
            $dispatch->delay(now()->addSeconds(5));
        }
    }

    /** The images the resizer handles: not SVG (nothing to resize) and not GIF (a resize would flatten it). */
    private function isSupportedImage(Media $media): bool
    {
        return str_starts_with((string) $media->mime_type, 'image/')
            && ! in_array($media->mime_type, ['image/svg+xml', 'image/gif'], true);
    }
}

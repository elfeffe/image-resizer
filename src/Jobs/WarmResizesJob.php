<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Jobs;

use Elfeffe\ImageResizer\Http\Controllers\ImageResizerController;
use Elfeffe\ImageResizer\Support\QueuesWarmResizes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The resizes a model's views will ask for, made right after the image is stored so the first view finds them (one download of the
 * original for all of them, instead of one per size, each held by a web worker). The model says which: it opts in by defining
 * `imageResizerWarmUrls(Media $media): array`, the resize URLs (as `getMediaUrl()` builds them) its views request for that media.
 * Best effort, like the placeholder: nothing waits for it, so a media that is gone, a model that does not opt in or a file that is
 * not there ends the job quietly, and a resize that is not made here is made by the first request, as it always was.
 */
class WarmResizesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the unique lock should be maintained.
     */
    public int $uniqueFor = 3600;

    public function __construct(protected int $mediaId) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    public function handle(): void
    {
        $media = Media::find($this->mediaId);
        $model = $media?->model;

        if (! $model || ! QueuesWarmResizes::optsIn($model)) {
            return;
        }

        $urls = array_filter((array) $model->imageResizerWarmUrls($media), static fn (mixed $url): bool => is_string($url) && $url !== '');

        if ($urls !== []) {
            app(ImageResizerController::class)->warm($media, $urls);
        }
    }
}

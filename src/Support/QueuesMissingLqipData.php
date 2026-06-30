<?php

namespace Elfeffe\ImageResizer\Support;

use Elfeffe\ImageResizer\Jobs\CalculateLqipJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class QueuesMissingLqipData
{
    public function forModel(Model $model): void
    {
        if (! $model instanceof HasMedia || ! method_exists($model, 'media')) {
            return;
        }

        $model->media()
            ->where('mime_type', 'like', 'image/%')
            ->whereNotIn('mime_type', ['image/svg+xml', 'image/gif'])
            ->where(function (Builder $query): void {
                $query->whereNull('custom_properties')
                    ->orWhereNull('custom_properties->lqip_color')
                    ->orWhereNull('custom_properties->blurhash');
            })
            ->pluck('id')
            ->each(fn (int|string $mediaId) => $this->dispatch((int) $mediaId));
    }

    public function forMedia(Media $media, bool $delay = false): void
    {
        if (! $media->model instanceof HasMedia) {
            return;
        }

        if (! $this->isSupportedImage($media) || ! $this->isMissingLqipData($media)) {
            return;
        }

        $this->dispatch($media->id, $delay);
    }

    private function dispatch(int $mediaId, bool $delay = false): void
    {
        $dispatch = CalculateLqipJob::dispatch($mediaId)
            ->onQueue('default')
            ->afterCommit();

        if ($delay) {
            $dispatch->delay(now()->addSeconds(5));
        }
    }

    private function isSupportedImage(Media $media): bool
    {
        return str_starts_with((string) $media->mime_type, 'image/')
            && ! in_array($media->mime_type, ['image/svg+xml', 'image/gif'], true);
    }

    private function isMissingLqipData(Media $media): bool
    {
        return ! $media->hasCustomProperty('lqip_color')
            || ! $media->hasCustomProperty('blurhash');
    }
}

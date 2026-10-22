<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Commands;

use Elfeffe\ImageResizer\Support\QueuesWarmResizes;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WarmResizesCommand extends Command
{
    protected $signature = 'image-resizer:warm
                           {--model=* : Model class or morph alias to limit to}';

    protected $description = 'Queue the preparation of the resizes of existing images whose model defines imageResizerWarmUrls()';

    public function handle(QueuesWarmResizes $queues): int
    {
        $only = array_map($this->classOf(...), (array) $this->option('model'));

        // The model types are resolved here, one class each, instead of loading a model per media: a type whose class is gone (an
        // uninstalled package, a renamed model) or that does not opt in is skipped, and a morph alias is mapped back to its class.
        $types = Media::query()
            ->distinct()
            ->pluck('model_type')
            ->filter(fn (?string $type): bool => filled($type)
                && QueuesWarmResizes::optsIn($this->classOf($type))
                && ($only === [] || in_array($this->classOf($type), $only, true)))
            ->values()
            ->all();

        $queued = 0;

        if ($types !== []) {
            Media::query()
                ->whereIn('model_type', $types)
                ->where('mime_type', 'like', 'image/%')
                ->whereNotIn('mime_type', ['image/svg+xml', 'image/gif'])
                ->chunkById(500, function (Collection $chunk) use ($queues, &$queued): void {
                    foreach ($chunk as $media) {
                        $queues->dispatch((int) $media->id);
                        $queued++;
                    }
                });
        }

        $this->info("Queued {$queued} media items to prepare their resizes.");

        if ($queued > 0) {
            $this->info('Run your queue worker to process them.');
        }

        return self::SUCCESS;
    }

    private function classOf(string $type): string
    {
        return ltrim(Relation::getMorphedModel($type) ?? $type, '\\');
    }
}

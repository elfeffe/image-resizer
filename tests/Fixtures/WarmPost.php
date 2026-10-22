<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests\Fixtures;

use Elfeffe\ImageResizer\Traits\HasImageResizer;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** A model that opts in: it says which resizes its views ask for. */
class WarmPost extends Model implements HasMedia
{
    use HasImageResizer, InteractsWithMedia;

    protected $table = 'warm_models';

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<int, mixed>|null what the model answers instead of its usual three, for a test that wants other URLs */
    public static ?array $urls = null;

    /** @return array<int, mixed> */
    public function imageResizerWarmUrls(Media $media): array
    {
        return self::$urls ?? [
            $this->getMediaUrl($media, 320, 240, 'fit'),
            $this->getMediaUrl($media, 640),
            $this->getMediaUrl($media, 640, null, 'resize', null, 'image/webp'),
        ];
    }
}

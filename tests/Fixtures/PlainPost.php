<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** A model with media that never opted in. */
class PlainPost extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'warm_models';

    public $timestamps = false;

    protected $guarded = [];
}

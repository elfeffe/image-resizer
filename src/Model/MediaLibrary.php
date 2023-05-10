<?php
namespace Elfeffe\ImageResizer\Model;

use Elfeffe\ImageResizer\Traits\HasImageResizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibrary extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use HasImageResizer;

    protected $table = 'media_library';
    protected $guarded = [];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uploaded_by_user_id = auth()->id();
        });
    }

    public function getItem(string $collection = 'default'): Media
    {
        return $this->getFirstMedia($collection);
    }
}

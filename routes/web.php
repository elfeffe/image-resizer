<?php

use Elfeffe\ImageResizer\Http\Controllers\ImageResizerController;

Route::get('image_resizer/{img}/w/{w}/h/{h}/{type}/{path}.{ext}', [ImageResizerController::class, 'show'])
    ->where('path', '.*')
    ->where('ext', 'jpg|jpeg|png|webp|avif');

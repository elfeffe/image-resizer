<?php
Route::get('image_resizer/{img}/w/{w}/h/{h}/{type}/{path}.{ext}', [\Elfeffe\ImageResizer\Http\Controllers\ImageResizerController::class, 'show'])
    ->where('path', '.*');

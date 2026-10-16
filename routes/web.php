<?php

use Elfeffe\ImageResizer\Http\Controllers\ImageResizerController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

// No `web` group on purpose: a resized image sets no cookie, so a CDN and the browser may keep it. The limiter is
// ImageResizerServiceProvider's `image-resizer`.
Route::get('image_resizer/{img}/w/{w}/h/{h}/{type}/{path}.{ext}', [ImageResizerController::class, 'show'])
    ->middleware(ThrottleRequests::class.':image-resizer')
    ->where('path', '.*')
    ->where('ext', 'jpg|jpeg|png|webp|avif');

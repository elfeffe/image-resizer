<?php

namespace Elfeffe\ImageResizer;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Elfeffe\ImageResizer\ImageResizer
 */
class ImageResizerFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'image-resizer';
    }
}

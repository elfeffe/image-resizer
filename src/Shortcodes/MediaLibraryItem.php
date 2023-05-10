<?php
namespace Elfeffe\ImageResizer\Shortcodes;

use Illuminate\Support\Facades\Blade;

class MediaLibraryItem
{
    public function register($shortcode, $content, $compiler, $name, $viewData)
    {
        $string = '';

        if($shortcode->id)
        {
            $string .= sprintf(' id="%s" ', $shortcode->id, $content);
        }

        if($shortcode->width)
        {
            $string .= sprintf(' width="%s" ', $shortcode->width, $content);
        }

        if($shortcode->height)
        {
            $string .= sprintf(' height="%s" ', $shortcode->height, $content);
        }

        if($shortcode->type)
        {
            $string .= sprintf(' type="%s" ', $shortcode->type, $content);
        }

        if($shortcode->name)
        {
            $string .= sprintf(' name="%s" ', $shortcode->name, $content);
        }

        return Blade::render('<x-media-library-item ' . $string . ' />');
    }
}

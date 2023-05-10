<?php
namespace Elfeffe\ImageResizer\View;

use Elfeffe\ImageResizer\Model\MediaLibrary;
use Illuminate\View\Component;

class MediaLibraryItem extends Component
{
    public $id;
    public $item;
    public $media;
    public $width;
    public $height;
    public $type;
    public $name;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($id, $width = 256, $height = 256, $type = 'resize', $name = '')
    {
        $this->id = $id;
        $this->item = MediaLibrary::find($id);
        $this->media = $this->item->getItem();
        $this->width = $width;
        $this->height = $height;
        $this->type = $type;
        $this->name = $name;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('resizer::media-library-item');
    }
}

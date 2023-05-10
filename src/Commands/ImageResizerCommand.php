<?php

namespace Elfeffe\ImageResizer\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImageResizerCommand extends Command
{
    public $signature = 'image-resizer:delete-old {date?}';

    public $description = 'My command';

    public function handle()
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now();
        $files = Storage::disk('image_resizer')->allDirectories();

        foreach ($files as $file) {
            if(Storage::disk('image_resizer')->exists($file) && Storage::disk('image_resizer')->lastModified($file) < $date->getTimestamp())
            {
                Storage::disk('image_resizer')->deleteDirectory($file);
            }
        }

        $this->comment('All done');
    }
}

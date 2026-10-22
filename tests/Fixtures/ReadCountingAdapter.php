<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests\Fixtures;

use Illuminate\Filesystem\FilesystemAdapter;

/** A disk that is not `local`, as a bucket is, and counts how many times a file was read whole. */
class ReadCountingAdapter extends FilesystemAdapter
{
    public int $reads = 0;

    public function get($path)
    {
        $this->reads++;

        return parent::get($path);
    }
}

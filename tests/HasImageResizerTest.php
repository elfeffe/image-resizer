<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\Traits\HasImageResizer;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class HasImageResizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::setInstance(new Container);
        Container::getInstance()->instance('config', new Repository);
    }

    #[Test]
    public function it_uses_img_when_the_name_is_empty(): void
    {
        $resizer = new class
        {
            use HasImageResizer;

            public ?string $name = null;
        };

        $media = new Media(['mime_type' => 'image/jpeg']);
        $media->id = 42;

        $this->assertSame(
            '/image_resizer/42/w/100/h/null/resize/img.jpg',
            $resizer->getFriendlyImageUrl(100, media: $media),
        );
    }
}

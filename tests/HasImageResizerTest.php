<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\Support\Access;
use Elfeffe\ImageResizer\Traits\HasImageResizer;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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

    #[Test]
    public function it_signs_every_segment_of_the_path_when_the_project_signs(): void
    {
        config(['image-resizer.signed' => true, 'app.key' => 'a-test-key']);

        $signature = hash_hmac('sha256', '42|100|null|resize|img.jpg', 'a-test-key');

        $this->assertSame(
            '/image_resizer/42/w/100/h/null/resize/img.jpg?s='.$signature,
            $this->resizer()->getFriendlyImageUrl(100, media: $this->media()),
        );
        $this->assertSame($signature, Access::signature(42, 100, 'null', 'resize', 'img.jpg'));
        $this->assertTrue(Access::accepts($signature, '42', '100', 'null', 'resize', 'img.jpg'));
    }

    #[Test]
    public function it_signs_the_name_and_the_format_a_caller_picked(): void
    {
        config(['image-resizer.signed' => true, 'app.key' => 'a-test-key']);

        $url = $this->resizer()->getMediaUrl($this->media(), 480, 480, 'fit', 'Kept Photo', 'image/webp');

        $this->assertSame(
            '/image_resizer/42/w/480/h/480/fit/kept_photo.webp?s='.hash_hmac('sha256', '42|480|480|fit|kept_photo.webp', 'a-test-key'),
            $url,
        );
    }

    #[Test]
    public function it_signs_the_cdn_url_the_same_way(): void
    {
        config(['image-resizer.signed' => true, 'app.key' => 'a-test-key', 'image-resizer.serve.url' => 'https://cdn.example.com/', 'image-resizer.serve.mode' => 'cdn_origin']);

        $this->assertSame(
            'https://cdn.example.com/image_resizer/42/w/100/h/null/resize/img.jpg?s='.Access::signature(42, 100, 'null', 'resize', 'img.jpg'),
            $this->resizer()->getFriendlyImageUrl(100, media: $this->media()),
        );
    }

    #[Test]
    public function it_asks_for_the_largest_size_the_route_serves_and_signs_that(): void
    {
        config(['image-resizer.signed' => true, 'app.key' => 'a-test-key']);

        $url = $this->resizer()->getFriendlyImageUrl(99999, 5000, media: $this->media());

        $this->assertStringStartsWith('/image_resizer/42/w/4096/h/4096/resize/img.jpg?s=', $url);
        $this->assertStringEndsWith(Access::signature(42, 4096, 4096, 'resize', 'img.jpg'), $url);

        config(['image-resizer.signed' => false, 'image-resizer.max_dimension' => 1000]);

        $this->assertSame('/image_resizer/42/w/1000/h/null/resize/img.jpg', $this->resizer()->getFriendlyImageUrl(2000, media: $this->media()));
    }

    #[Test]
    public function a_signature_key_of_its_own_replaces_the_app_key(): void
    {
        config(['app.key' => 'a-test-key', 'image-resizer.signature_key' => 'another-key']);

        $this->assertSame(hash_hmac('sha256', '1|2|3|fit|f.jpg', 'another-key'), Access::signature(1, 2, 3, 'fit', 'f.jpg'));
    }

    #[Test]
    public function no_signature_matches_a_url_it_was_not_made_for(): void
    {
        config(['app.key' => 'a-test-key']);
        $signature = Access::signature(42, 100, 'null', 'resize', 'img.jpg');

        foreach ([[43, 100, 'null', 'resize', 'img.jpg'], [42, 101, 'null', 'resize', 'img.jpg'], [42, 100, 100, 'resize', 'img.jpg'], [42, 100, 'null', 'fit', 'img.jpg'], [42, 100, 'null', 'resize', 'img.png'], [42, 100, 'null', 'resize', 'other.jpg']] as $other) {
            $this->assertFalse(Access::accepts($signature, ...$other), implode('|', $other));
        }

        $this->assertFalse(Access::accepts(null, 42, 100, 'null', 'resize', 'img.jpg'));
        $this->assertFalse(Access::accepts(['x'], 42, 100, 'null', 'resize', 'img.jpg'));
        $this->assertFalse(Access::accepts('', 42, 100, 'null', 'resize', 'img.jpg'));
    }

    #[Test]
    public function it_refuses_to_sign_without_a_key(): void
    {
        config(['image-resizer.signed' => true, 'app.key' => '']);

        $this->expectException(RuntimeException::class);

        $this->resizer()->getFriendlyImageUrl(100, media: $this->media());
    }

    #[Test]
    public function the_cache_folder_is_the_id_until_the_project_signs(): void
    {
        config(['app.key' => 'a-test-key']);

        $this->assertSame('42', Access::folder(42));

        config(['image-resizer.signed' => true]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', Access::folder(42));
        $this->assertSame(Access::folder(42), Access::folder('42'));
        $this->assertNotSame(Access::folder(42), Access::folder(43));

        config(['app.key' => 'another-key']);

        $this->assertNotSame(hash_hmac('sha256', 'folder|42', 'a-test-key'), Access::folder(42));
    }

    private function resizer(): object
    {
        return new class
        {
            use HasImageResizer;

            public ?string $name = null;
        };
    }

    private function media(): Media
    {
        $media = new Media(['mime_type' => 'image/jpeg']);
        $media->id = 42;

        return $media;
    }
}

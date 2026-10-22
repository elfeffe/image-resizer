<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\Support\Access;
use Elfeffe\ImageResizer\Tests\Fixtures\WarmPost;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

/**
 * `ImageResizerController::warm()` makes the resizes a view will ask for from one read of the original, where a GET of each would
 * download the original again, and keeps them where that GET looks for them: what it makes is checked by asking the route for it.
 */
class WarmResizesTest extends WarmingTestCase
{
    #[Test]
    public function the_route_serves_what_was_warmed_from_the_cache_and_would_have_made_the_same_bytes(): void
    {
        $media = $this->media(5, $this->png(800, 400));
        $urls = (new WarmPost)->imageResizerWarmUrls($media);

        $this->assertSame(3, $this->warm($media, $urls));

        $warmed = [];

        foreach ($urls as $url) {
            $warmed[$url] = $this->get($url)->assertOk()->assertHeader('X-Image-Resizer', 'cached')->getContent();
        }

        $this->assertSame([320, 240], array_slice(getimagesizefromstring($warmed[$urls[0]]), 0, 2));
        $this->assertSame([640, 320], array_slice(getimagesizefromstring($warmed[$urls[1]]), 0, 2));
        $this->assertSame('image/webp', getimagesizefromstring($warmed[$urls[2]])['mime']);

        // The same requests with nothing cached make the same bytes.
        File::deleteDirectory($this->cacheRoot);

        foreach ($urls as $url) {
            $this->assertSame($warmed[$url], $this->get($url)->assertOk()->assertHeader('X-Image-Resizer', 'true')->getContent(), $url);
        }
    }

    #[Test]
    public function a_signed_project_is_warmed_into_the_folder_only_the_app_names(): void
    {
        config()->set('image-resizer.signed', true);
        $media = $this->media(5, $this->png(800, 400));
        $urls = (new WarmPost)->imageResizerWarmUrls($media);

        $this->assertStringContainsString('?s=', $urls[0]);
        $this->assertSame(3, $this->warm($media, $urls));
        $this->assertFileExists($this->cacheRoot.'/'.Access::folder('5').'/320x240/fit.png');
        $this->assertDirectoryDoesNotExist($this->cacheRoot.'/5');

        foreach ($urls as $url) {
            $this->get($url)->assertOk()->assertHeader('X-Image-Resizer', 'cached');
        }

        $this->assertSame(0, $this->warm($media, $urls));
    }

    #[Test]
    public function a_second_warm_makes_nothing(): void
    {
        $media = $this->media(5, $this->png(800, 400));
        $urls = (new WarmPost)->imageResizerWarmUrls($media);

        $this->assertSame(3, $this->warm($media, $urls));
        $this->assertSame(0, $this->warm($media, $urls));

        // A size that is new is the only one made.
        $this->assertSame(1, $this->warm($media, [...$urls, '/image_resizer/5/w/100/h/null/resize/img.jpg']));
    }

    #[Test]
    public function a_resize_that_is_cached_already_is_left_as_it_is(): void
    {
        $media = $this->media(5, $this->png(800, 400));
        Storage::disk('image_resizer')->put('5/320x240/fit.png', 'made by a request');

        $this->assertSame(1, $this->warm($media, ['/image_resizer/5/w/320/h/240/fit/img.png', '/image_resizer/5/w/100/h/null/resize/img.jpg']));
        $this->get('/image_resizer/5/w/320/h/240/fit/img.png')->assertContent('made by a request');
    }

    #[Test]
    public function a_url_the_route_would_not_serve_for_this_media_is_skipped(): void
    {
        config()->set('image-resizer.signed', true);
        config()->set('image-resizer.allow_original', true);
        $media = $this->media(5, $this->png(800, 400));
        $other = $this->media(6, $this->png(800, 400));
        $signed = static fn (int $id, int $width, string $type = 'resize', string $file = 'img.jpg'): string => "/image_resizer/{$id}/w/{$width}/h/null/{$type}/{$file}?s=".Access::signature($id, $width, 'null', $type, $file);

        $this->assertSame(0, $this->warm($media, [
            $signed(6, 100),                                                    // another media's
            '/image_resizer/5/w/100/h/null/resize/img.jpg?s=nope',              // a bad signature
            '/image_resizer/5/w/100/h/null/resize/img.jpg',                     // none at all
            '/image_resizer/5/w/101/h/null/resize/img.jpg?s='.Access::signature(5, 100, 'null', 'resize', 'img.jpg'), // another URL's
            $signed(5, 100, 'original'),                                        // signed and allowed, but not a resize
            $signed(5, 100, 'blur'),                                            // a type the route refuses
            $signed(5, 99999),                                                  // a size it refuses
            $signed(5, 100, 'resize', 'img.gif'),                               // an extension the route does not match
            'https://example.com/photos/5/img.jpg',                             // not a resizer URL
            '/storage/image_resizer/5/100xnull/resize.jpg',
            '',
            null,
            5,
        ]));

        $this->assertSame([], $this->cachedFiles());
        $this->assertSame(1, $this->warm($media, [$signed(5, 100)]));
        $this->assertSame(0, $this->warm($other, [$signed(5, 101)]), 'a signed URL of a media is not warmed for another');
        $this->assertFileDoesNotExist($this->cacheRoot.'/'.Access::folder('5').'/101xnull/resize.jpg');
    }

    #[Test]
    public function original_is_never_warmed_in_a_project_that_does_not_check_the_type(): void
    {
        $media = $this->media(5, $this->png(800, 400));

        $this->assertSame(0, $this->warm($media, ['/image_resizer/5/w/100/h/null/original/img.jpg']));
        $this->assertSame([], $this->cachedFiles());
    }

    #[Test]
    public function a_url_may_be_absolute_on_another_host_and_carry_a_prefix_and_a_query(): void
    {
        config()->set('image-resizer.signed', true);
        config()->set('image-resizer.serve.url', 'https://cdn.example.com/media');
        $media = $this->media(5, $this->png(800, 400));
        $urls = (new WarmPost)->imageResizerWarmUrls($media);

        $this->assertStringStartsWith('https://cdn.example.com/media/image_resizer/5/', $urls[0]);
        $this->assertSame(3, $this->warm($media, $urls));
        $this->get($this->routeOf($urls[0]))->assertHeader('X-Image-Resizer', 'cached');
    }

    #[Test]
    public function a_strict_project_is_warmed_under_the_spelling_it_serves(): void
    {
        config()->set('image-resizer.strict', true);
        $media = $this->media(5, $this->png(800, 400));

        $this->assertSame(1, $this->warm($media, ['/image_resizer/005/w/0100/h/None/resize/img.png', '/image_resizer/5/w/100/h/0/resize/img.png']));
        $this->assertFileExists($this->cacheRoot.'/5/100xnull/resize.png');
        $this->get('/image_resizer/0005/w/100.9/h/null/resize/other.png')->assertHeader('X-Image-Resizer', 'cached');
    }

    #[Test]
    public function the_original_is_read_once_for_all_the_sizes(): void
    {
        $this->remoteDisk('bucket');
        $media = $this->media(5, $this->png(800, 400), disk: 'bucket');
        $urls = (new WarmPost)->imageResizerWarmUrls($media);

        $this->assertSame(3, $this->warm($media, $urls));
        $this->assertSame(1, Storage::disk('bucket')->reads, 'one download for three sizes');

        $this->assertSame(0, $this->warm($media, $urls));
        $this->assertSame(1, Storage::disk('bucket')->reads, 'nothing is missing, so nothing is read');
        $this->get($urls[1])->assertHeader('X-Image-Resizer', 'cached');
    }

    #[Test]
    public function object_storage_is_warmed_where_the_route_looks_for_it(): void
    {
        $this->remoteDisk('cache_bucket');
        config()->set('image-resizer.storage.driver', 's3');
        config()->set('image-resizer.storage.disk', 'cache_bucket');
        $media = $this->media(5, $this->png(800, 400));
        $urls = (new WarmPost)->imageResizerWarmUrls($media);

        $this->assertSame(3, $this->warm($media, $urls));
        $this->assertTrue(Storage::disk('cache_bucket')->exists('5/w/320/h/240/fit/img.png'));
        $this->assertSame([], $this->cachedFiles(), 'object storage never touches the app disk');
        $this->assertSame(0, $this->warm($media, $urls));

        $warmed = $this->get($urls[0])->assertOk()->assertHeader('X-Image-Resizer', 'object-storage-hit')->streamedContent();

        Storage::disk('cache_bucket')->delete('5/w/320/h/240/fit/img.png');

        // What the route makes for the same URL is the object that was warmed.
        $this->get($urls[0])->assertOk()->assertHeader('X-Image-Resizer', 'object-storage-generated');
        $this->assertSame($warmed, Storage::disk('cache_bucket')->get('5/w/320/h/240/fit/img.png'));
    }

    #[Test]
    public function a_file_that_is_not_there_or_cannot_be_decoded_ends_the_warm_up_quietly(): void
    {
        Log::spy();

        foreach ([5 => null, 6 => '', 7 => 'this is not an image'] as $id => $bytes) {
            $media = $this->media($id, $bytes);

            $this->assertSame(0, $this->warm($media, (new WarmPost)->imageResizerWarmUrls($media)), (string) $bytes);
        }

        Log::shouldNotHaveReceived('error');
        Log::shouldHaveReceived('info')->withArgs(static fn (string $message): bool => str_contains($message, 'cannot be decoded'))->twice();
        $this->assertSame([], $this->cachedFiles());
    }

    #[Test]
    public function a_route_of_the_host_that_answers_to_the_path_is_not_the_resizers_to_admit(): void
    {
        Route::fallback(static fn () => 'the host');
        $media = $this->media(5, $this->png(800, 400));

        $this->assertSame(0, $this->warm($media, ['/image_resizer/5/w/100/h/null/resize/img.gif']));
        $this->assertSame([], $this->cachedFiles());
    }

    #[Test]
    public function a_media_without_urls_to_warm_makes_nothing(): void
    {
        $this->assertSame(0, $this->warm($this->media(5, $this->png(800, 400)), []));
    }
}

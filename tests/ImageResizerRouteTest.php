<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\Support\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

/**
 * Who may ask the resizer route for what: the signature, the types, the sizes, `original`, the limiter and the cache folder. A test
 * that expects a 404 where the media table is empty says "the guard let the request through to the database", a 403 or 422 says it
 * did not; a signed project's tests have no media table at all, so any read before the signature is checked would be a 500.
 */
class ImageResizerRouteTest extends TestCase
{
    private string $cacheRoot;

    private string $mediaRoot;

    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), MediaLibraryServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Testbench ships no key; a project that signs has one.
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $this->cacheRoot = sys_get_temp_dir().'/image-resizer-cache-'.uniqid();
        $this->mediaRoot = sys_get_temp_dir().'/image-resizer-media-'.uniqid();
        config()->set('filesystems.disks.image_resizer.root', $this->cacheRoot);
        config()->set('filesystems.disks.media_test', ['driver' => 'local', 'root' => $this->mediaRoot, 'url' => 'https://files.example.com']);
        Storage::forgetDisk('image_resizer');
        Storage::forgetDisk('media_test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cacheRoot);
        File::deleteDirectory($this->mediaRoot);

        parent::tearDown();
    }

    #[Test]
    public function the_route_is_throttled_and_stays_outside_the_web_group(): void
    {
        $route = Route::getRoutes()->getByAction('Elfeffe\ImageResizer\Http\Controllers\ImageResizerController@show');

        $this->assertNotNull($route);
        $this->assertSame(['Illuminate\Routing\Middleware\ThrottleRequests:image-resizer'], $route->gatherMiddleware(), 'no session, no cookie: a CDN may keep the image');
    }

    #[Test]
    public function an_unsigned_project_lets_a_request_through_to_the_database(): void
    {
        $this->makeMediaTable();

        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp')->assertNotFound();
        $this->get('/image_resizer/5/w/100/h/50/fit/thumb.webp')->assertNotFound();
        $this->get('/image_resizer/5/w/100/h/null/null/thumb.webp')->assertNotFound();
    }

    #[Test]
    public function a_size_above_the_limit_is_refused(): void
    {
        $this->makeMediaTable();

        $this->get('/image_resizer/5/w/4096/h/4096/fit/thumb.webp')->assertNotFound();
        $this->get('/image_resizer/5/w/4097/h/null/resize/thumb.webp')->assertStatus(422);
        $this->get('/image_resizer/5/w/99999/h/null/resize/thumb.webp')->assertStatus(422);
        $this->get('/image_resizer/5/w/100/h/4097/fit/thumb.webp')->assertStatus(422);
        $this->get('/image_resizer/5/w/99999999999999999999/h/null/resize/thumb.webp')->assertStatus(422);
        $this->get('/image_resizer/5/w/1e5/h/null/resize/thumb.webp')->assertStatus(422);

        config()->set('image-resizer.max_dimension', 1000);

        $this->get('/image_resizer/5/w/1000/h/null/resize/thumb.webp')->assertNotFound();
        $this->get('/image_resizer/5/w/1001/h/null/resize/thumb.webp')->assertStatus(422);
    }

    #[Test]
    public function a_size_that_is_not_a_positive_number_still_means_the_source_own(): void
    {
        $this->makeMediaTable();

        foreach (['w/0/h/null', 'w/100/h/None', 'w/100/h/0', 'w/-5/h/-5', 'w/abc/h/null'] as $size) {
            $this->get("/image_resizer/5/{$size}/resize/thumb.webp")->assertNotFound();
        }
    }

    #[Test]
    public function a_type_the_controller_does_not_implement_is_refused(): void
    {
        $this->makeMediaTable();

        foreach (['blur', 'cover', 'contain', 'RESIZE', 'resize%20', '..'] as $type) {
            $this->get("/image_resizer/5/w/100/h/null/{$type}/thumb.webp")->assertStatus(422);
        }

        // `crop` is what two Neoteo themes have always asked for: served at the file's own size, so it still passes.
        foreach (['resize', 'fit', 'null', 'crop'] as $type) {
            $this->get("/image_resizer/5/w/100/h/null/{$type}/thumb.webp")->assertNotFound();
        }
    }

    #[Test]
    public function original_is_refused_unless_the_project_allows_it(): void
    {
        $this->makeMediaTable();
        $this->makeMedia(5, $this->png(100, 50));

        $this->get('/image_resizer/5/w/1/h/1/original/x.jpg')->assertForbidden();

        config()->set('image-resizer.allow_original', true);

        $this->get('/image_resizer/5/w/1/h/1/original/x.jpg')->assertRedirect(Media::query()->find(5)->getFullUrl());
        $this->get('/image_resizer/6/w/1/h/1/original/x.jpg')->assertNotFound();
    }

    #[Test]
    public function a_query_string_cannot_steer_the_route(): void
    {
        $this->cached('5', '100xnull', 'resize.webp', 'resize five');
        $this->cached('5', '100xnull', 'fit.webp', 'fit five');
        $this->cached('7', '100xnull', 'resize.webp', 'resize seven');

        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp?type=fit')->assertContent('resize five');
        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp?img=7')->assertContent('resize five');
        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp?w=99999&h=3')->assertContent('resize five');
        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp?type=original')->assertContent('resize five');
        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp?ext=php')->assertContent('resize five');
    }

    #[Test]
    public function every_spelling_of_a_size_is_one_cache_entry(): void
    {
        $this->cached('5', '100xnull', 'resize.webp', 'one entry');

        foreach (['w/100/h/null', 'w/0100/h/None', 'w/100/h/0', 'w/100.9/h/-1', 'w/100/h/null'] as $size) {
            $this->get("/image_resizer/005/{$size}/resize/thumb.webp")->assertContent('one entry');
        }
    }

    #[Test]
    public function a_signed_project_answers_403_before_it_reads_anything(): void
    {
        config()->set('image-resizer.signed', true);
        $this->cached(Access::folder('5'), '100xnull', 'resize.webp', 'five');

        // No media table in this test: a read of the database before the signature is checked would be a 500.
        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp')->assertForbidden();
        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp?s=')->assertForbidden();
        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp?s=nope')->assertForbidden();
        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp?s[]=nope')->assertForbidden();
        $this->get('/image_resizer/5/w/99999/h/null/resize/thumb.webp')->assertForbidden();
        $this->get('/image_resizer/5/w/1/h/1/original/thumb.webp')->assertForbidden();
    }

    #[Test]
    public function a_signature_opens_that_url_and_no_other(): void
    {
        config()->set('image-resizer.signed', true);
        $this->cached(Access::folder('5'), '100xnull', 'resize.webp', 'five');
        $s = Access::signature(5, 100, 'null', 'resize', 'thumb.webp');

        $this->get("/image_resizer/5/w/100/h/null/resize/thumb.webp?s={$s}")->assertOk()->assertContent('five')->assertHeader('X-Image-Resizer', 'cached');

        foreach ([
            '/image_resizer/6/w/100/h/null/resize/thumb.webp',
            '/image_resizer/5/w/101/h/null/resize/thumb.webp',
            '/image_resizer/5/w/100/h/100/resize/thumb.webp',
            '/image_resizer/5/w/100/h/null/fit/thumb.webp',
            '/image_resizer/5/w/100/h/null/resize/other.webp',
            '/image_resizer/5/w/100/h/null/resize/thumb.jpg',
            '/image_resizer/5/w/0100/h/null/resize/thumb.webp',
            '/image_resizer/05/w/100/h/null/resize/thumb.webp',
        ] as $other) {
            $this->get("{$other}?s={$s}")->assertForbidden();
        }
    }

    #[Test]
    public function a_signed_query_cannot_borrow_another_media_or_type(): void
    {
        config()->set('image-resizer.signed', true);
        $this->cached(Access::folder('5'), '100xnull', 'resize.webp', 'five');
        $this->cached(Access::folder('7'), '100xnull', 'resize.webp', 'seven');
        $s = Access::signature(5, 100, 'null', 'resize', 'thumb.webp');

        $this->get("/image_resizer/5/w/100/h/null/resize/thumb.webp?s={$s}&img=7")->assertContent('five');
        $this->get("/image_resizer/7/w/100/h/null/resize/thumb.webp?s={$s}&img=5")->assertForbidden();
    }

    #[Test]
    public function a_signed_project_keeps_its_cache_where_only_the_app_finds_it(): void
    {
        config()->set('image-resizer.signed', true);
        $this->makeMediaTable();
        $this->makeMedia(5, $this->png(100, 50));
        $s = Access::signature(5, 100, 'null', 'resize', 'thumb.png');

        $this->get("/image_resizer/5/w/100/h/null/resize/thumb.png?s={$s}")->assertOk()->assertHeader('X-Image-Resizer', 'true');

        $this->assertFileExists($this->cacheRoot.'/'.Access::folder('5').'/100xnull/resize.png');
        $this->assertDirectoryDoesNotExist($this->cacheRoot.'/5', 'a plain /storage/image_resizer/5/… must not exist for the web server to find');
        $this->get("/image_resizer/5/w/100/h/null/resize/thumb.png?s={$s}")->assertOk()->assertHeader('X-Image-Resizer', 'cached');
    }

    #[Test]
    public function an_unsigned_project_keeps_the_historical_cache_layout(): void
    {
        $this->makeMediaTable();
        $this->makeMedia(5, $this->png(100, 50));

        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.png')->assertOk();

        $this->assertFileExists($this->cacheRoot.'/5/100xnull/resize.png');
    }

    #[Test]
    public function a_signed_original_needs_the_project_to_allow_it_as_well(): void
    {
        config()->set('image-resizer.signed', true);
        $this->makeMediaTable();
        $this->makeMedia(5, $this->png(100, 50));
        $s = Access::signature(5, 1, 1, 'original', 'x.jpg');

        $this->get("/image_resizer/5/w/1/h/1/original/x.jpg?s={$s}")->assertForbidden();

        config()->set('image-resizer.allow_original', true);

        $this->get("/image_resizer/5/w/1/h/1/original/x.jpg?s={$s}")->assertRedirect(Media::query()->find(5)->getFullUrl());
        $this->get('/image_resizer/5/w/1/h/1/original/x.jpg')->assertForbidden();
    }

    #[Test]
    public function neither_type_goes_above_the_source(): void
    {
        $this->makeMediaTable();
        $this->makeMedia(5, $this->png(100, 50));

        $this->assertSame([100, 50], $this->dimensionsOf('/image_resizer/5/w/400/h/null/resize/a.png'));
        $this->assertSame([100, 50], $this->dimensionsOf('/image_resizer/5/w/400/h/400/resize/b.png'));
        $this->assertSame([50, 50], $this->dimensionsOf('/image_resizer/5/w/400/h/400/fit/c.png'), 'cropped to the box ratio, not enlarged to the box');
        $this->assertSame([60, 30], $this->dimensionsOf('/image_resizer/5/w/60/h/30/fit/d.png'));
        $this->assertSame([40, 20], $this->dimensionsOf('/image_resizer/5/w/40/h/null/resize/e.png'));
        $this->assertSame([100, 50], $this->dimensionsOf('/image_resizer/5/w/4096/h/4096/null/f.png'), 'no type, no resize');
        $this->assertSame([100, 50], $this->dimensionsOf('/image_resizer/5/w/64/h/64/crop/g.png'), 'crop was never implemented: the file at its own size');
    }

    #[Test]
    public function the_limiter_answers_429_after_the_requests_a_minute_it_is_set_to(): void
    {
        config()->set('image-resizer.signed', true);

        $this->assertSame(120, config('image-resizer.throttle'), 'the shipped limit');

        for ($request = 1; $request <= 120; $request++) {
            $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp')->assertForbidden();
        }

        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp')->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])->get('/image_resizer/5/w/100/h/null/resize/thumb.webp')->assertForbidden();
    }

    #[Test]
    public function the_limit_is_the_hosts_to_set_and_zero_turns_it_off(): void
    {
        config()->set('image-resizer.signed', true);
        config()->set('image-resizer.throttle', 3);

        foreach (range(1, 3) as $ignored) {
            $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp')->assertForbidden();
        }

        $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp')->assertStatus(429);

        config()->set('image-resizer.throttle', 0);

        foreach (range(1, 130) as $ignored) {
            $this->get('/image_resizer/5/w/100/h/null/resize/thumb.webp')->assertForbidden();
        }
    }

    /** @return array{0: int, 1: int} the size of the image a URL answers with */
    private function dimensionsOf(string $url): array
    {
        $response = $this->get($url)->assertOk();
        [$width, $height] = getimagesizefromstring((string) $response->getContent());

        return [$width, $height];
    }

    private function cached(string $folder, string $size, string $file, string $contents): void
    {
        Storage::disk('image_resizer')->put("{$folder}/{$size}/{$file}", $contents);
    }

    private function makeMediaTable(): void
    {
        $stub = dirname((new ReflectionClass(Media::class))->getFileName(), 4).'/database/migrations/create_media_table.php.stub';

        (include $stub)->up();
    }

    private function makeMedia(int $id, string $bytes): void
    {
        Storage::disk('media_test')->put("{$id}/original.png", $bytes);

        DB::table('media')->insert([
            'id' => $id,
            'model_type' => 'test',
            'model_id' => 1,
            'collection_name' => 'default',
            'name' => 'original',
            'file_name' => 'original.png',
            'mime_type' => 'image/png',
            'disk' => 'media_test',
            'size' => strlen($bytes),
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
        ]);
    }

    private function png(int $width, int $height): string
    {
        $image = new Imagick;
        $image->newImage($width, $height, new ImagickPixel('red'));
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }
}

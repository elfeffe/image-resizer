<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\Http\Controllers\ImageResizerController;
use Elfeffe\ImageResizer\Tests\Fixtures\ReadCountingAdapter;
use Elfeffe\ImageResizer\Tests\Fixtures\WarmPost;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Imagick;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use ReflectionClass;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

/**
 * What the warm-up tests share: a media table, a table for the models that own media, a cache disk and a disk for the originals,
 * both in the temp directory. The media row `$id` has its file at `{$id}/original.png`, as the default path generator lays it out.
 */
abstract class WarmingTestCase extends TestCase
{
    protected string $cacheRoot;

    protected string $mediaRoot;

    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), MediaLibraryServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Testbench ships no key; a project that signs has one.
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('cache.default', 'array');
        $this->cacheRoot = sys_get_temp_dir().'/image-resizer-warm-cache-'.uniqid();
        $this->mediaRoot = sys_get_temp_dir().'/image-resizer-warm-media-'.uniqid();
        config()->set('filesystems.disks.image_resizer.root', $this->cacheRoot);
        config()->set('filesystems.disks.media_test', ['driver' => 'local', 'root' => $this->mediaRoot, 'url' => 'https://files.example.com']);
        Storage::forgetDisk('image_resizer');
        Storage::forgetDisk('media_test');

        $stub = dirname((new ReflectionClass(Media::class))->getFileName(), 4).'/database/migrations/create_media_table.php.stub';
        (include $stub)->up();
        Schema::create('warm_models', static fn (Blueprint $table) => $table->id());
        DB::table('warm_models')->insert(['id' => 1]);

        WarmPost::$urls = null;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cacheRoot);
        File::deleteDirectory($this->mediaRoot);

        parent::tearDown();
    }

    /**
     * A media row, its file in `$disk` unless `$bytes` is null. The row is inserted, not created, so nothing observes it.
     *
     * @param  class-string  $model
     */
    protected function media(int $id, ?string $bytes = null, string $model = WarmPost::class, string $mime = 'image/png', string $disk = 'media_test'): Media
    {
        if ($bytes !== null) {
            Storage::disk($disk)->put("{$id}/original.png", $bytes);
        }

        DB::table('media')->insert([
            'id' => $id,
            'model_type' => $model,
            'model_id' => 1,
            'collection_name' => 'default',
            'name' => 'original',
            'file_name' => 'original.png',
            'mime_type' => $mime,
            'disk' => $disk,
            'size' => strlen((string) $bytes),
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
        ]);

        return Media::query()->findOrFail($id);
    }

    /** @param  array<int, mixed>  $urls */
    protected function warm(Media $media, array $urls): int
    {
        return app(ImageResizerController::class)->warm($media, $urls);
    }

    /** A disk that is not local, as a bucket is; `Storage::disk($name)->reads` counts the files read whole. */
    protected function remoteDisk(string $name): void
    {
        Storage::extend('warm-test', static fn (Application $app, array $config): ReadCountingAdapter => new ReadCountingAdapter(
            new Filesystem($adapter = new LocalFilesystemAdapter($config['root'])),
            $adapter,
            $config,
        ));
        config()->set("filesystems.disks.{$name}", ['driver' => 'warm-test', 'root' => $this->mediaRoot.'/'.$name, 'throw' => false]);
    }

    /** @return array<int, string> the resizes in the cache disk */
    protected function cachedFiles(): array
    {
        return Storage::disk('image_resizer')->allFiles();
    }

    /** What a GET of the URL asks the app for: from the first `/image_resizer/` on. */
    protected function routeOf(string $url): string
    {
        return substr($url, (int) strpos($url, '/image_resizer/'));
    }

    protected function png(int $width, int $height): string
    {
        $image = new Imagick;
        $image->newPseudoImage($width, $height, 'gradient:red-blue');
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }
}

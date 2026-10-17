<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Bepsvpt\Blurhash\BlurhashServiceProvider;
use Elfeffe\ImageResizer\Jobs\CalculateLqipJob;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

/**
 * The placeholder job is best effort: a file that is not there (the row is written before the file is; a fixture has none; the
 * object was deleted), is empty, or cannot be decoded ends it quietly. It used to throw, which a sync queue (a test, a developer's
 * machine) raised into whatever saved the model, and which a real queue filed under failed jobs.
 */
class CalculateLqipJobTest extends TestCase
{
    private string $root;

    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), MediaLibraryServiceProvider::class, BlurhashServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/image-resizer-lqip-'.uniqid();
        config()->set('filesystems.disks.local_disk', ['driver' => 'local', 'root' => $this->root.'/local']);
        // A driver that is not `local`, as a bucket is: the job reads the bytes through the disk instead of the path.
        Storage::extend('lqip-test', static fn (Application $app, array $config): FilesystemAdapter => new FilesystemAdapter(
            new Filesystem($adapter = new LocalFilesystemAdapter($config['root'])),
            $adapter,
            $config,
        ));
        config()->set('filesystems.disks.remote_disk', ['driver' => 'lqip-test', 'root' => $this->root.'/remote', 'throw' => false]);
        config()->set('filesystems.disks.remote_throwing_disk', ['driver' => 'lqip-test', 'root' => $this->root.'/remote', 'throw' => true]);

        $stub = dirname((new ReflectionClass(Media::class))->getFileName(), 4).'/database/migrations/create_media_table.php.stub';
        (include $stub)->up();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /** @return array<string, array{string}> */
    public static function disks(): array
    {
        return ['a local disk' => ['local_disk'], 'a disk that is not local' => ['remote_disk'], 'one that throws for a missing file' => ['remote_throwing_disk']];
    }

    #[Test]
    #[DataProvider('disks')]
    public function a_file_that_is_not_there_ends_the_job_quietly(string $disk): void
    {
        $this->media(5, $disk);
        Log::spy();

        CalculateLqipJob::dispatchSync(5);
        CalculateLqipJob::dispatchSync(5, true);

        $this->assertSame([], $this->properties(5), 'nothing was written');
        Log::shouldHaveReceived('debug')->twice();
        Log::shouldNotHaveReceived('error');
    }

    #[Test]
    #[DataProvider('disks')]
    public function an_empty_file_ends_the_job_quietly(string $disk): void
    {
        $this->media(5, $disk, bytes: '');
        Log::spy();

        CalculateLqipJob::dispatchSync(5);

        $this->assertSame([], $this->properties(5));
        Log::shouldHaveReceived('debug')->once();
    }

    #[Test]
    #[DataProvider('disks')]
    public function a_file_that_cannot_be_decoded_is_logged_and_never_thrown(string $disk): void
    {
        $this->media(5, $disk, bytes: 'this is not an image');
        Log::spy();

        CalculateLqipJob::dispatchSync(5);

        $this->assertSame([], $this->properties(5));
        Log::shouldHaveReceived('info')->withArgs(static fn (string $message): bool => str_contains($message, 'cannot be decoded'))->once();
    }

    #[Test]
    #[DataProvider('disks')]
    public function a_real_image_still_gets_its_placeholder(string $disk): void
    {
        $this->media(5, $disk, bytes: $this->png(100, 50));

        CalculateLqipJob::dispatchSync(5);

        $properties = $this->properties(5);
        $this->assertSame(100, $properties['image_resizer']['width']);
        $this->assertSame(50, $properties['image_resizer']['height']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $properties['lqip_color']);
        $this->assertNotEmpty($properties['blurhash']);
    }

    #[Test]
    public function a_media_that_is_gone_or_is_not_an_image_is_left_alone(): void
    {
        $this->media(6, 'local_disk', bytes: 'a,b', mime: 'text/csv');

        CalculateLqipJob::dispatchSync(6);
        CalculateLqipJob::dispatchSync(999);

        $this->assertSame([], $this->properties(6));
    }

    /** @param  string|null  $bytes  null stores no file at all */
    private function media(int $id, string $disk, ?string $bytes = null, string $mime = 'image/png'): void
    {
        if ($bytes !== null) {
            Storage::disk($disk)->put("{$id}/original.png", $bytes);
        }

        DB::table('media')->insert([
            'id' => $id,
            'model_type' => 'test',
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
    }

    /** @return array<string, mixed> */
    private function properties(int $id): array
    {
        return (array) json_decode((string) DB::table('media')->where('id', $id)->value('custom_properties'), true);
    }

    private function png(int $width, int $height): string
    {
        $image = new Imagick;
        $image->newImage($width, $height, new ImagickPixel('red'));
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }
}

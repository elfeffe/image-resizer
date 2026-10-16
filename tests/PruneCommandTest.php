<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PruneCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/image-resizer-prune-'.uniqid();
        config()->set('filesystems.disks.prune_test', ['driver' => 'local', 'root' => $this->root]);
        config()->set('image-resizer.storage.disk', 'prune_test');
        Storage::forgetDisk('prune_test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_deletes_the_variants_unused_for_the_given_days_and_the_folders_they_leave_empty(): void
    {
        $old = $this->variant('1/800x600/resize.jpg', madeDaysAgo: 45);
        $fresh = $this->variant('2/800x600/resize.jpg', madeDaysAgo: 3);
        $oldButRead = $this->variant('3/800x600/resize.jpg', madeDaysAgo: 45, readDaysAgo: 2);
        $sibling = $this->variant('3/400x300/resize.jpg', madeDaysAgo: 45);

        $this->artisan('image-resizer:prune', ['--days' => 30, '--dry-run' => true])
            ->expectsOutputToContain('Would delete 2 cached resizes')
            ->assertSuccessful();
        $this->assertFileExists($old);

        $this->artisan('image-resizer:prune', ['--days' => 30])
            ->expectsOutputToContain('Deleted 2 cached resizes')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertDirectoryDoesNotExist($this->root.'/1');
        $this->assertFileDoesNotExist($sibling);
        $this->assertDirectoryDoesNotExist($this->root.'/3/400x300');
        $this->assertFileExists($fresh);
        $this->assertFileExists($oldButRead);
    }

    public function test_it_refuses_less_than_one_day(): void
    {
        $this->artisan('image-resizer:prune', ['--days' => 0])->assertFailed();
    }

    public function test_it_runs_every_night(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'image-resizer:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('40 4 * * *', $events->first()->expression);
    }

    private function variant(string $path, int $madeDaysAgo, ?int $readDaysAgo = null): string
    {
        $full = $this->root.'/'.$path;
        File::ensureDirectoryExists(dirname($full));
        file_put_contents($full, 'jpeg');
        touch($full, time() - $madeDaysAgo * 86400, time() - ($readDaysAgo ?? $madeDaysAgo) * 86400);

        return $full;
    }
}

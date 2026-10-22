<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\Jobs\CalculateLqipJob;
use Elfeffe\ImageResizer\Jobs\WarmResizesJob;
use Elfeffe\ImageResizer\Tests\Fixtures\PlainPost;
use Elfeffe\ImageResizer\Tests\Fixtures\WarmArticle;
use Elfeffe\ImageResizer\Tests\Fixtures\WarmPost;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * When the job is queued: for a media just created (the observer) and for the media that exist (`image-resizer:warm`). In both a
 * model that does not define `imageResizerWarmUrls()` never gets one, and neither does a file the resizer does not resize.
 */
class WarmResizesQueueingTest extends WarmingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('image-resizer.queue', 'images');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    #[Test]
    public function a_media_created_for_a_model_that_opted_in_is_queued_after_a_delay_on_the_configured_queue(): void
    {
        $media = $this->create(WarmPost::class);

        Queue::assertPushedOn('images', WarmResizesJob::class, static fn (WarmResizesJob $job): bool => $job->uniqueId() === (string) $media->id
            && $job->afterCommit === true
            && $job->delay !== null);
        Queue::assertPushed(WarmResizesJob::class, 1);
    }

    #[Test]
    public function the_placeholder_is_queued_as_before_whether_or_not_the_model_opted_in(): void
    {
        $this->create(WarmPost::class);
        $this->create(PlainPost::class);

        Queue::assertPushed(CalculateLqipJob::class, 2);
    }

    #[Test]
    public function a_model_that_did_not_opt_in_queues_nothing_new(): void
    {
        $this->create(PlainPost::class);

        Queue::assertNotPushed(WarmResizesJob::class);
    }

    /** @return array<string, array{string}> */
    public static function filesThatAreNotResized(): array
    {
        return ['an SVG' => ['image/svg+xml'], 'a GIF' => ['image/gif'], 'a PDF' => ['application/pdf'], 'no type' => ['']];
    }

    #[Test]
    #[DataProvider('filesThatAreNotResized')]
    public function a_file_the_resizer_does_not_resize_queues_nothing(string $mime): void
    {
        $this->create(WarmPost::class, $mime);

        Queue::assertNotPushed(WarmResizesJob::class);
    }

    #[Test]
    public function the_other_image_types_are_queued(): void
    {
        foreach (['image/jpeg', 'image/webp', 'image/avif'] as $mime) {
            $this->create(WarmPost::class, $mime);
        }

        Queue::assertPushed(WarmResizesJob::class, 3);
    }

    #[Test]
    public function the_command_queues_every_image_of_the_models_that_opted_in(): void
    {
        $this->rows();

        $this->artisan('image-resizer:warm')->expectsOutputToContain('Queued 4 media items')->assertSuccessful();

        $this->assertSame([1, 2, 6, 9], $this->queued());
        Queue::assertPushedOn('images', WarmResizesJob::class, static fn (WarmResizesJob $job): bool => $job->delay === null);
    }

    #[Test]
    public function the_command_can_be_limited_to_a_model_by_class(): void
    {
        $this->rows();

        $this->artisan('image-resizer:warm', ['--model' => [WarmArticle::class]])->expectsOutputToContain('Queued 1 media items')->assertSuccessful();

        $this->assertSame([6], $this->queued());
    }

    #[Test]
    public function the_command_resolves_a_morph_alias_in_the_option_and_in_the_rows(): void
    {
        $this->rows();

        $this->artisan('image-resizer:warm', ['--model' => ['post_alias']])->expectsOutputToContain('Queued 3 media items')->assertSuccessful();

        $this->assertSame([1, 2, 9], $this->queued());
    }

    #[Test]
    public function the_command_limited_to_a_model_that_did_not_opt_in_queues_nothing(): void
    {
        $this->rows();

        $this->artisan('image-resizer:warm', ['--model' => [PlainPost::class, 'Gone\\Model']])->expectsOutputToContain('Queued 0 media items')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_command_walks_the_media_in_chunks(): void
    {
        foreach (range(1, 1203) as $id) {
            $this->row($id, WarmPost::class);
        }

        $this->artisan('image-resizer:warm')->expectsOutputToContain('Queued 1203 media items')->assertSuccessful();

        Queue::assertPushed(WarmResizesJob::class, 1203);
    }

    /** A media created the way the media library creates one, so the observer sees it. */
    private function create(string $model, string $mime = 'image/png'): Media
    {
        return Media::query()->create([
            'model_type' => $model,
            'model_id' => 1,
            'collection_name' => 'default',
            'name' => 'original',
            'file_name' => 'original.png',
            'mime_type' => $mime,
            'disk' => 'media_test',
            'size' => 10,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    /** Rows no observer sees: 1 and 2 (a PNG and a JPEG) and 3 to 5 (SVG, GIF, PDF) of a model that opted in, 6 of another that did, 7 of one that did not, 8 of a class that is gone, 9 under a morph alias. */
    private function rows(): void
    {
        Relation::morphMap(['post_alias' => WarmPost::class]);

        $this->row(1, WarmPost::class);
        $this->row(2, WarmPost::class, 'image/jpeg');
        $this->row(3, WarmPost::class, 'image/svg+xml');
        $this->row(4, WarmPost::class, 'image/gif');
        $this->row(5, WarmPost::class, 'application/pdf');
        $this->row(6, WarmArticle::class);
        $this->row(7, PlainPost::class);
        $this->row(8, 'Gone\\Model');
        $this->row(9, 'post_alias');
    }

    private function row(int $id, string $type, string $mime = 'image/png'): void
    {
        $this->media($id, model: $type, mime: $mime);
    }

    /** @return array<int, int> the media the jobs queued so far are for */
    private function queued(): array
    {
        return Queue::pushed(WarmResizesJob::class)
            ->map(static fn (WarmResizesJob $job): int => (int) $job->uniqueId())
            ->sort()
            ->values()
            ->all();
    }
}

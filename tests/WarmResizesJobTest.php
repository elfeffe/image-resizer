<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\Jobs\WarmResizesJob;
use Elfeffe\ImageResizer\Tests\Fixtures\PlainPost;
use Elfeffe\ImageResizer\Tests\Fixtures\WarmArticle;
use Elfeffe\ImageResizer\Tests\Fixtures\WarmPost;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;

/**
 * The job is best effort, like the placeholder one: whatever it cannot do ends it quietly, and what it did not make a request makes.
 */
class WarmResizesJobTest extends WarmingTestCase
{
    #[Test]
    public function it_is_a_unique_queued_job_that_may_run_two_minutes_and_three_times(): void
    {
        $job = new WarmResizesJob(5);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('5', $job->uniqueId());
        $this->assertSame(3600, $job->uniqueFor);
        $this->assertSame(120, $job->timeout);
        $this->assertSame(3, $job->tries);
    }

    #[Test]
    public function it_makes_the_resizes_the_model_asks_for(): void
    {
        $media = $this->media(5, $this->png(800, 400));
        $urls = (new WarmPost)->imageResizerWarmUrls($media);

        WarmResizesJob::dispatchSync(5);

        $this->assertCount(3, $this->cachedFiles());

        foreach ($urls as $url) {
            $this->get($url)->assertOk()->assertHeader('X-Image-Resizer', 'cached');
        }

        WarmResizesJob::dispatchSync(5);

        $this->assertCount(3, $this->cachedFiles());
    }

    #[Test]
    public function a_model_that_inherits_the_method_opts_in_too(): void
    {
        $this->media(5, $this->png(800, 400), WarmArticle::class);

        WarmResizesJob::dispatchSync(5);

        $this->assertCount(3, $this->cachedFiles());
    }

    #[Test]
    public function only_the_non_empty_strings_the_model_returns_are_used(): void
    {
        $this->media(5, $this->png(800, 400));
        WarmPost::$urls = ['', null, 5, ['/image_resizer/5/w/90/h/null/resize/img.png'], '/image_resizer/5/w/100/h/null/resize/img.png'];

        WarmResizesJob::dispatchSync(5);

        $this->assertSame(['5/100xnull/resize.png'], $this->cachedFiles());
    }

    #[Test]
    public function a_model_with_nothing_to_warm_is_left_alone(): void
    {
        $this->media(5, $this->png(800, 400));
        WarmPost::$urls = [];

        WarmResizesJob::dispatchSync(5);

        $this->assertSame([], $this->cachedFiles());
    }

    #[Test]
    public function a_model_that_did_not_opt_in_or_is_gone_is_left_alone(): void
    {
        $this->media(5, $this->png(800, 400), PlainPost::class);
        $this->media(7, $this->png(800, 400));
        DB::table('media')->where('id', 7)->update(['model_id' => 99]);

        WarmResizesJob::dispatchSync(5);
        WarmResizesJob::dispatchSync(7);

        $this->assertSame([], $this->cachedFiles());
    }

    #[Test]
    public function a_media_that_is_gone_or_has_no_file_ends_the_job_quietly(): void
    {
        $this->media(5);
        Log::spy();

        WarmResizesJob::dispatchSync(999);
        WarmResizesJob::dispatchSync(5);

        $this->assertSame([], $this->cachedFiles());
        Log::shouldNotHaveReceived('error');
    }
}

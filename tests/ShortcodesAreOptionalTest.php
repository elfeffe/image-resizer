<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\ImageResizerServiceProvider;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Webwizo\Shortcodes\Facades\Shortcode;

/**
 * webwizo/laravel-shortcodes is a suggestion, and its provider replaces Laravel's view factory: a project that does not use
 * shortcodes must boot and render without it. The trap is a project where the package is installed but its provider is not
 * registered (excluded from discovery): the facade class exists, the `shortcode` binding does not.
 */
class ShortcodesAreOptionalTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ImageResizerServiceProvider::class];
    }

    #[Test]
    public function the_provider_boots_to_its_end_without_the_shortcodes_provider(): void
    {
        $this->assertFalse($this->app->bound('shortcode'), 'the shortcodes provider is not registered in this application');
        $this->assertTrue(class_exists(Shortcode::class), 'but its facade is installed: the case that used to fail on boot');

        // Booting got past the shortcode step: what the provider registers after it is there.
        $this->assertArrayHasKey('imageResizerStyles', Blade::getCustomDirectives());
        $this->assertStringContainsString('image-resizer', view('resizer::placeholder', [
            'attributeString' => 'alt="x"', 'sizes' => '100vw', 'srcset' => '', 'srcsetWebp' => '', 'srcsetAvif' => '', 'src' => '/a.jpg',
            'fallbackMimeType' => 'image/jpeg', 'width' => 10, 'height' => 'null', 'fallbackHeight' => 10, 'isResponsive' => true,
            'isBoxed' => false, 'class' => '', 'lqipColor' => '#f0f0f0', 'blurHash' => null,
        ])->render());
    }
}

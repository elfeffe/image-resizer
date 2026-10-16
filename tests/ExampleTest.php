<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use PHPUnit\Framework\Attributes\Test;

class ExampleTest extends TestCase
{
    #[Test]
    public function it_renders_images_without_a_height_responsively(): void
    {
        $html = view('resizer::placeholder', $this->viewData(isResponsive: true, height: 'null'))->render();

        $this->assertStringContainsString('image-resizer-picture-responsive', $html);
        $this->assertStringContainsString('image-resizer-img-responsive', $html);
        $this->assertStringContainsString('--min-height: 0px', $html);
        $this->assertStringNotContainsString('class="absolute inset-0 w-full h-full"', $html);
    }

    #[Test]
    public function it_keeps_images_with_explicit_dimensions_fixed(): void
    {
        $html = view('resizer::placeholder', $this->viewData(isResponsive: false, height: 600))->render();

        $this->assertStringContainsString('class="absolute inset-0 w-full h-full"', $html);
        $this->assertStringContainsString('w-full h-full object-cover', $html);
        $this->assertStringContainsString('aspect-ratio: 1200 / 600', $html);
        $this->assertStringNotContainsString('image-resizer-img-responsive', $html);
    }

    #[Test]
    public function it_offers_avif_before_webp_only_when_given(): void
    {
        $html = view('resizer::placeholder', $this->viewData(isResponsive: true, height: 'null', srcsetAvif: '/example.avif 1200w'))->render();

        $this->assertStringContainsString('type="image/avif"', $html);
        $this->assertLessThan(strpos($html, 'type="image/webp"'), strpos($html, 'type="image/avif"'));

        $this->assertStringNotContainsString('image/avif', view('resizer::placeholder', $this->viewData(isResponsive: true, height: 'null'))->render());
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(bool $isResponsive, int|string $height, string $srcsetAvif = ''): array
    {
        return [
            'srcsetAvif' => $srcsetAvif,
            'attributeString' => 'alt="Example"',
            'sizes' => '100vw',
            'srcset' => '/example.jpg 1200w',
            'srcsetWebp' => '/example.webp 1200w',
            'src' => '/example.jpg',
            'fallbackMimeType' => 'image/jpeg',
            'width' => 1200,
            'height' => $height,
            'fallbackHeight' => 1200,
            'isResponsive' => $isResponsive,
            'isBoxed' => ! $isResponsive,
            'class' => 'justify-center items-center',
            'lqipColor' => '#f0f0f0',
            'blurHash' => null,
        ];
    }
}

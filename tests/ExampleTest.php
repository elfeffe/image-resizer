<?php

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
        $this->assertStringContainsString('--min-height: 600px', $html);
        $this->assertStringNotContainsString('image-resizer-img-responsive', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(bool $isResponsive, int|string $height): array
    {
        return [
            'attributeString' => 'alt="Example"',
            'loadingAttributeValue' => null,
            'srcset' => '/example.jpg 1200w',
            'srcsetWebp' => '/example.webp 1200w',
            'srcWebp' => '/example.webp',
            'src' => '/example.jpg',
            'width' => 1200,
            'height' => $height,
            'fallbackHeight' => 1200,
            'isResponsive' => $isResponsive,
            'class' => 'justify-center items-center',
            'lqipColor' => '#f0f0f0',
            'blurHash' => null,
        ];
    }
}

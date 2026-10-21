<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `@imageResizerScripts` loads the built script as a classic <script>, which shares the page's global scope with every
 * other classic script. An ES bundle's top-level `const p = …` there made filament-breezy.js fail with "Identifier 'p'
 * has already been declared" (and a second evaluation of the script itself would have thrown the same). The build is an
 * IIFE (`vite.config.js`): nothing it declares reaches the global scope.
 */
class BuiltScriptIsSelfContainedTest extends TestCase
{
    #[Test]
    public function the_built_script_declares_nothing_in_the_global_scope(): void
    {
        $script = trim((string) file_get_contents(__DIR__.'/../public/build/js/image-resizer.js'));

        $this->assertStringStartsWith('(function(){', $script, 'rebuild with `npm run build`: the bundle must be an IIFE');
        $this->assertStringEndsWith('})();', $script);
    }
}

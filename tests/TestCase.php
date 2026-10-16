<?php

namespace Elfeffe\ImageResizer\Tests;

use Elfeffe\ImageResizer\ImageResizerServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Webwizo\Shortcodes\ShortcodesServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Spatie\\ImageResizer\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return array_values(array_filter([
            // The provider registers a shortcode when the package that serves them is installed.
            class_exists(ShortcodesServiceProvider::class) ? ShortcodesServiceProvider::class : null,
            ImageResizerServiceProvider::class,
        ]));
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        include_once __DIR__.'/../database/migrations/create_image-resizer_table.php.stub';
        (new \CreatePackageTable())->up();
        */
    }
}

<?php

namespace Elfeffe\ImageResizer\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Elfeffe\ImageResizer\ImageResizerServiceProvider;

class TestCase extends Orchestra
{
    public function setUp(): void
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
            class_exists(\Webwizo\Shortcodes\ShortcodesServiceProvider::class) ? \Webwizo\Shortcodes\ShortcodesServiceProvider::class : null,
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

<?php

namespace Elfeffe\ImageResizer;

use Elfeffe\BuilderComponent\View\Components\Element;
use Elfeffe\BuilderComponent\View\Components\Image;
use Elfeffe\BuilderComponent\View\Components\Input;
use Elfeffe\BuilderComponent\View\Components\Modal;
use Elfeffe\BuilderComponent\View\Components\RawText;
use Elfeffe\BuilderComponent\View\Components\Text;
use Elfeffe\CommerceBlocks\Shortcodes\ProductGalleryShortcode;
use Elfeffe\ImageResizer\Shortcodes\MediaLibraryItem;
use Elfeffe\ImageResizer\Observers\MediaObserver;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Elfeffe\ImageResizer\Commands\ImageResizerCommand;
use Elfeffe\ImageResizer\Commands\CalculateLqipCommand;
use Elfeffe\ImageResizer\Console\Commands\InstallHtaccessCommand;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Webwizo\Shortcodes\Facades\Shortcode;

class ImageResizerServiceProvider extends PackageServiceProvider
{

    public function boot()
    {
        // Register the 'image-resizer' service
        $this->app->singleton('image-resizer', function ($app) {
            return new ImageResizer; // Ensure ImageResizer class exists and is imported
        });

        app()->config["filesystems.disks.image_resizer"] = [
            'driver' => 'local',
            'root' => storage_path('app/public/image_resizer'),
            'url' => config('app.url').'/storage/image_resizer',
            'visibility' => 'public',
        ];

        $this->commands([
            ImageResizerCommand::class,
            CalculateLqipCommand::class,
            InstallHtaccessCommand::class,
        ]);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'resizer');

        // Publish built assets
        $this->publishes([
            __DIR__.'/../public/build' => public_path('vendor/image-resizer'),
        ], 'image-resizer-assets');

        // Publish htaccess rules for Apache optimization
        $this->publishes([
            __DIR__.'/../resources/htaccess/.htaccess' => public_path('.htaccess-image-resizer'),
        ], 'image-resizer-htaccess');

        // Register MediaObserver to automatically calculate LQIP colors
        Media::observe(MediaObserver::class);

        if (class_exists(Shortcode::class)) {
            Shortcode::register('media-library-item', MediaLibraryItem::class);
        }

        Blade::component('media-library-item', \Elfeffe\ImageResizer\View\MediaLibraryItem::class);

        // Register Blade directives
        $this->registerBladeDirectives();
    }

    protected function registerBladeDirectives()
    {
        // @imageResizerStyles - Include CSS
        Blade::directive('imageResizerStyles', function () {
            return "<?php echo \Elfeffe\ImageResizer\ImageResizerServiceProvider::styles(); ?>";
        });

        // @imageResizerScripts - Include JS
        Blade::directive('imageResizerScripts', function () {
            return "<?php echo \Elfeffe\ImageResizer\ImageResizerServiceProvider::scripts(); ?>";
        });
    }

    public static function styles(): string
    {
        $cssPath = asset('vendor/image-resizer/css/image-resizer.css');
        
        return <<<HTML
        <link rel="stylesheet" href="{$cssPath}">
        HTML;
    }

    public static function scripts(): string
    {
        $jsPath = asset('vendor/image-resizer/js/image-resizer.js');
        
        return <<<HTML
        <script src="{$jsPath}"></script>
        HTML;
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('image-resizer')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_image-resizer_table')
            ->hasCommand(ImageResizerCommand::class)
            ->hasCommand(CalculateLqipCommand::class)
            ->hasCommand(InstallHtaccessCommand::class)
            ->publishesServiceProvider('ImageResizerServiceProvider')
            ->hasAssets();
    }
}

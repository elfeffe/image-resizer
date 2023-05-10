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
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Elfeffe\ImageResizer\Commands\ImageResizerCommand;
use Webwizo\Shortcodes\Facades\Shortcode;

class ImageResizerServiceProvider extends PackageServiceProvider
{

    public function boot()
    {
        app()->config["filesystems.disks.image_resizer"] = [
            'driver' => 'local',
            'root' => storage_path('app/public/image_resizer'),
            'url' => config('app.url').'/storage/image_resizer',
            'visibility' => 'public',
        ];

        $this->commands([
            ImageResizerCommand::class,
        ]);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'resizer');

        Shortcode::register('media-library-item', MediaLibraryItem::class);
        Blade::component('media-library-item', View\MediaLibraryItem::class);
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
            ->hasCommand(ImageResizerCommand::class);
    }
}

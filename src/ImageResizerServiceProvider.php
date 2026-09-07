<?php

namespace Elfeffe\ImageResizer;

use Elfeffe\BuilderComponent\View\Components\Image;
use Elfeffe\ImageResizer\Commands\CalculateLqipCommand;
use Elfeffe\ImageResizer\Commands\ImageResizerCommand;
use Elfeffe\ImageResizer\Console\Commands\InstallHtaccessCommand;
use Elfeffe\ImageResizer\Observers\MediaObserver;
use Elfeffe\ImageResizer\Shortcodes\MediaLibraryItem;
use Elfeffe\ImageResizer\Support\QueuesMissingLqipData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
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

        /*
         * The canonical `image_resizer` disk is always registered as a local disk so
         * cached resizes keep resolving with the historical behaviour. When a remote
         * driver is configured it is registered under its configured name (overriding
         * the local definition when the names match), mirroring BlockNoteField/blog.
         */
        app()->config['filesystems.disks.image_resizer'] = $this->imageResizerLocalDiskDefinition();

        $configuredDisk = (string) config('image-resizer.storage.disk', 'image_resizer');

        if ((string) config('image-resizer.storage.driver', 'local') === 's3') {
            app()->config['filesystems.disks.'.$configuredDisk] = $this->imageResizerRemoteDiskDefinition();
        }

        $this->validateServeConfiguration();

        $this->commands([
            ImageResizerCommand::class,
            CalculateLqipCommand::class,
            InstallHtaccessCommand::class,
        ]);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'resizer');

        // Publish built assets
        $this->publishes([
            __DIR__.'/../public/build' => public_path('vendor/image-resizer'),
        ], 'image-resizer-assets');

        // Publish htaccess rules for Apache optimization
        $this->publishes([
            __DIR__.'/../resources/htaccess/.htaccess' => public_path('.htaccess-image-resizer'),
        ], 'image-resizer-htaccess');

        if (app()->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/boost/guidelines/core.blade.php' => base_path('.ai/guidelines/image-resizer/core.blade.php'),
                __DIR__.'/../resources/boost/skills/image-resizer-development/SKILL.md' => base_path('.ai/skills/image-resizer-development/SKILL.md'),
            ], 'image-resizer-boost');
        }

        // Register MediaObserver to automatically calculate LQIP colors
        Media::observe(MediaObserver::class);
        Event::listen('eloquent.saved: *', function (string $eventName, array $models): void {
            $model = $models[0] ?? null;

            if (! $model instanceof Model) {
                return;
            }

            app(QueuesMissingLqipData::class)->forModel($model);
        });

        if (class_exists(Shortcode::class)) {
            Shortcode::register('media-library-item', MediaLibraryItem::class);
        }

        Blade::component('media-library-item', View\MediaLibraryItem::class);

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

    protected function imageResizerLocalDiskDefinition(): array
    {
        return [
            'driver' => 'local',
            'root' => storage_path('app/public/image_resizer'),
            'url' => config('app.url').'/storage/image_resizer',
            'visibility' => 'public',
        ];
    }

    protected function imageResizerRemoteDiskDefinition(): array
    {
        return [
            'driver' => 's3',
            'key' => config('image-resizer.storage.s3.key'),
            'secret' => config('image-resizer.storage.s3.secret'),
            'region' => config('image-resizer.storage.s3.region'),
            'bucket' => config('image-resizer.storage.s3.bucket'),
            'endpoint' => config('image-resizer.storage.s3.endpoint'),
            'use_path_style_endpoint' => (bool) config('image-resizer.storage.s3.use_path_style_endpoint', false),
            'url' => config('image-resizer.storage.url'),
            'root' => (string) config('image-resizer.storage.root', 'image_resizer'),
            'visibility' => (string) config('image-resizer.storage.visibility', 'public'),
            'throw' => true,
        ];
    }

    protected function validateServeConfiguration(): void
    {
        $serveUrl = (string) config('image-resizer.serve.url', '');

        if ($serveUrl === '') {
            return;
        }

        $mode = (string) config('image-resizer.serve.mode', 'cdn_origin');

        if (! in_array($mode, ['cdn_origin', 'redirect'], true)) {
            throw new \RuntimeException(
                "image-resizer: invalid serve.mode '{$mode}'. Expected 'cdn_origin' or 'redirect'."
            );
        }
    }

    public static function styles(): string
    {
        $cssPath = self::versionedAsset('vendor/image-resizer/css/image-resizer.css');

        return <<<HTML
        <link rel="stylesheet" href="{$cssPath}">
        HTML;
    }

    public static function scripts(): string
    {
        $jsPath = self::versionedAsset('vendor/image-resizer/js/image-resizer.js');

        // data-navigate-once: under wire:navigate the body is replaced on
        // every visit and its scripts re-run; this one installs document-wide
        // listeners and an observer, which the first run already did.
        return <<<HTML
        <script src="{$jsPath}" data-navigate-once></script>
        HTML;
    }

    /**
     * The published asset's URL with its modification time as a query, so a
     * republished script is fetched instead of served from browser and CDN
     * caches for as long as they please.
     */
    private static function versionedAsset(string $path): string
    {
        $file = public_path($path);
        $version = is_file($file) ? (string) filemtime($file) : null;

        return asset($path).($version !== null ? '?v='.$version : '');
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

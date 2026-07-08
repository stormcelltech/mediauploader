<?php

namespace StormcellTech;

use Illuminate\Support\ServiceProvider;
use StormcellTech\Console\InstallPackageCommand;

class MediaUploaderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Path adjusted to look for config folder adjacent to the provider inside src/
        $this->mergeConfigFrom(
            __DIR__ . '/config/media-upload.php',
            'media-upload'
        );

        // Bind MediaUploader singleton correctly using class resolution reference
        $this->app->singleton(MediaUploader::class, function ($app) {
            return new MediaUploader();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/config/media-upload.php' => config_path('media-upload.php'),
        ], 'media-upload-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/database/migrations' => database_path('migrations'),
        ], 'media-upload-migrations');

        // Publish Blade components
        $this->publishes([
            __DIR__ . '/resources/views/components' => resource_path('views/components/media-upload'),
        ], 'media-upload-components');

        // Load views
        $this->loadViewsFrom(
            __DIR__ . '/resources/views',
            'media-upload'
        );

        // Load routes (Make sure this exists or comment out if empty)
        if (file_exists(__DIR__ . '/routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        }

        // Load migrations automatically into framework boot pool
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallPackageCommand::class,
            ]);
        }
    }
}

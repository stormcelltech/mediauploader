<?php

namespace StormcellTech\MediaUploader;

use Illuminate\Support\ServiceProvider;
use StormcellTech\MediaUploader\Console\InstallPackageCommand;
use StormcellTech\MediaUploader\MediaUploader;


class MediaUploaderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/media-upload.php',
            'media-upload'
        );

        // Bind MediaUploader singleton
        $this->app->singleton(MediaUploader::class, function ($app) {
            return new MediaUploader();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register the command if the application is running via terminal

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

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallPackageCommand::class,
            ]);
        }
    }
}

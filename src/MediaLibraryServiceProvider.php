<?php

namespace Palgoal\MediaLibrary;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Palgoal\MediaLibrary\Models\Media;

class MediaLibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/media-library.php', 'media-library');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'media-library');
        $this->registerRoutes();
        $this->registerPolicy();
        $this->registerPublishing();
    }

    protected function registerRoutes(): void
    {
        Route::prefix(config('media-library.route_prefix', 'media-library'))
            ->middleware(config('media-library.middleware', ['web', 'auth']))
            ->name('media-library.')
            ->group(__DIR__ . '/../routes/media-library.php');
    }

    protected function registerPolicy(): void
    {
        $policy = config('media-library.policy', Policies\MediaPolicy::class);

        Gate::policy(Media::class, $policy);
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/media-library.php' => config_path('media-library.php'),
        ], 'media-library-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'media-library-migrations');

        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/media-library'),
        ], 'media-library-assets');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/media-library'),
        ], 'media-library-views');
    }
}

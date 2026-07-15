<?php

namespace Palgoal\MediaLibrary;

use Illuminate\Support\Facades\Blade;
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

        // loadViewsFrom() only registers the "media-library::" VIEW namespace
        // (enough for view('media-library::x') / @include). It does NOT make
        // <x-media-library::x> or <x-dynamic-component :component="'media-library::x'">
        // resolve, because Blade's component-tag compiler resolves tag/component
        // names through a separate registry (anonymous component paths), not
        // through the view-namespace hints. Without this line, any package view
        // that uses <x-dynamic-component> to render "media-library::layouts.minimal"
        // (or any other media-library:: component) throws:
        // "Unable to locate a class or view for component [media-library::...]".
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views', 'media-library');

        $this->registerRoutes();
        $this->registerPolicy();
        $this->registerPublishing();
    }

    /**
     * Register the package's routes under the configured prefix.
     *
     * config('media-library.route_prefix') may be a multi-segment path
     * (e.g. "dashboard/media-library") to mount the package inside an
     * existing dashboard/admin area — Laravel's Route::prefix() natively
     * supports that, no route file of the host application is touched.
     * Route NAMES are always "media-library.*" regardless of the prefix,
     * so route()/URL helper calls never need to change.
     */
    protected function registerRoutes(): void
    {
        $prefix = trim((string) config('media-library.route_prefix', 'media-library'), '/');

        Route::prefix($prefix)
            ->middleware(config('media-library.middleware', ['web', 'auth']))
            ->name('media-library.')
            ->group(__DIR__ . '/../routes/media-library.php');
    }

    protected function registerPolicy(): void
    {
        if (! config('media-library.register_policy', true)) {
            return;
        }

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

        // Deliberately NOT covered by loadMigrationsFrom() (see boot()) and
        // NOT part of the 'media-library-migrations' tag above - attaching
        // media to host models via Concerns\HasMedia is an optional
        // feature on top of the base package, so the mediables table is
        // opt-in via its own tag instead of appearing automatically for
        // every install. The published filename is fixed (not generated
        // at publish time), so running this publish command more than
        // once always overwrites the same target file instead of creating
        // duplicate migrations with different timestamps.
        $this->publishes([
            __DIR__ . '/../database/optional-migrations/2025_06_12_000001_create_mediables_table.php'
                => database_path('migrations/2025_06_12_000001_create_mediables_table.php'),
        ], 'media-library-relations-migration');

        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/media-library'),
        ], 'media-library-assets');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/media-library'),
        ], 'media-library-views');
    }
}

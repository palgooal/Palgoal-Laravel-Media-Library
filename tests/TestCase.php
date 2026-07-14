<?php

namespace Palgoal\MediaLibrary\Tests;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Orchestra\Testbench\TestCase as Orchestra;
use Palgoal\MediaLibrary\MediaLibraryServiceProvider;
use Palgoal\MediaLibrary\Tests\Support\User;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // The package's default route middleware stack includes the "web"
        // group, which in a full Laravel app includes CSRF verification.
        // Test requests don't carry a browser session/token, so disable it
        // here the same way most Laravel feature tests do — this is a test
        // concern only, CSRF stays fully active for real requests.
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('session.driver', 'array');

        $app['config']->set('filesystems.disks.public', [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => '/storage',
            'visibility' => 'public',
        ]);

        // Point the package (and Laravel's default auth provider config)
        // at the test-only user model instead of assuming App\Models\User
        // exists, mirroring how a real host application would configure it.
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('media-library.user_model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/Support/migrations');
    }

    protected function actingAsUser(): User
    {
        $user = User::create([
            'name'     => 'Test User',
            'email'    => 'user' . uniqid() . '@example.test',
            'password' => 'not-used-in-tests',
        ]);

        $this->actingAs($user);

        return $user;
    }
}

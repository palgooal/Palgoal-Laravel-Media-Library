<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Palgoal\MediaLibrary\MediaLibraryServiceProvider;

/**
 * Deliberately extends Orchestra's own base TestCase directly — NOT
 * Palgoal\MediaLibrary\Tests\TestCase — because that shared abstract
 * class loads database/optional-migrations for every other test file's
 * convenience (see its defineDatabaseMigrations()). Bypassing it here is
 * the whole point: this class registers ONLY the service provider, the
 * same way a brand-new host application would, with nothing extra.
 */
class MediaLibraryRelationsNotAutoloadedTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MediaLibraryServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * MediaLibraryServiceProvider::boot() calls loadMigrationsFrom() only
     * for database/migrations (the base `media` table — see the
     * assertion below, which confirms that path DOES still auto-run).
     * database/optional-migrations (the `mediables` relations table) is
     * never passed to loadMigrationsFrom() anywhere in the package, and
     * is registered only as the 'media-library-relations-migration'
     * publish tag — so with nothing more than the provider registered,
     * `mediables` must not exist.
     */
    public function test_mediables_table_does_not_exist_without_an_explicit_publish_and_migrate(): void
    {
        $this->assertTrue(Schema::hasTable('media'), 'Expected the base `media` table to auto-load.');
        $this->assertFalse(Schema::hasTable('mediables'), 'The `mediables` table must NOT be created automatically.');
    }
}

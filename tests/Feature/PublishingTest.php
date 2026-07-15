<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Palgoal\MediaLibrary\Tests\TestCase;

class PublishingTest extends TestCase
{
    public function test_config_can_be_published(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'media-library-config', '--force' => true]);

        $this->assertFileExists(config_path('media-library.php'));
    }

    public function test_views_can_be_published(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'media-library-views', '--force' => true]);

        $this->assertFileExists(resource_path('views/vendor/media-library/media.blade.php'));
        $this->assertFileExists(resource_path('views/vendor/media-library/layouts/minimal.blade.php'));
    }

    public function test_assets_can_be_published(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'media-library-assets', '--force' => true]);

        $this->assertFileExists(public_path('vendor/media-library/js/media-library.js'));
        $this->assertFileExists(public_path('vendor/media-library/js/media-picker.js'));
    }

    public function test_migrations_can_be_published(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'media-library-migrations', '--force' => true]);

        $published = File::glob(database_path('migrations') . '/*_create_media_table.php');
        $this->assertNotEmpty($published, 'Expected the media table migration to be published.');
    }

    /**
     * The `mediables` relations migration is published under its OWN,
     * separate tag ('media-library-relations-migration') — never bundled
     * into 'media-library-migrations' above, and never auto-loaded (see
     * MediaLibraryRelationsNotAutoloadedTest).
     */
    public function test_relations_migration_can_be_published_under_its_own_tag(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'media-library-relations-migration', '--force' => true]);

        $this->assertFileExists(database_path('migrations/2025_06_12_000001_create_mediables_table.php'));
    }

    public function test_publishing_the_relations_migration_twice_does_not_create_a_duplicate_file(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'media-library-relations-migration', '--force' => true]);
        Artisan::call('vendor:publish', ['--tag' => 'media-library-relations-migration', '--force' => true]);

        $published = File::glob(database_path('migrations') . '/*_create_mediables_table.php');
        $this->assertCount(1, $published, 'Republishing must overwrite the same file, not create a second one.');
    }
}

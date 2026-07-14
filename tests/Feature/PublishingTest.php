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
}

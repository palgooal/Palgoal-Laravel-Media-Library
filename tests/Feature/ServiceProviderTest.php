<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Palgoal\MediaLibrary\Models\Media;
use Palgoal\MediaLibrary\Policies\MediaPolicy;
use Palgoal\MediaLibrary\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged_with_expected_defaults(): void
    {
        $this->assertSame('media-library', config('media-library.route_prefix'));
        $this->assertSame(['web', 'auth'], config('media-library.middleware'));
        $this->assertSame(MediaPolicy::class, config('media-library.policy'));
        $this->assertSame('public', config('media-library.disk'));
        $this->assertNotContains('svg', config('media-library.allowed_mimes'));
    }

    public function test_routes_are_registered_with_expected_names_and_prefix(): void
    {
        $this->assertTrue(Route::has('media-library.page'));
        $this->assertTrue(Route::has('media-library.media.index'));
        $this->assertTrue(Route::has('media-library.media.store'));
        $this->assertTrue(Route::has('media-library.media.show'));
        $this->assertTrue(Route::has('media-library.media.edit'));
        $this->assertTrue(Route::has('media-library.media.update'));
        $this->assertTrue(Route::has('media-library.media.destroy'));
        $this->assertTrue(Route::has('media-library.media.bulk-destroy'));

        $this->assertSame('/media-library', route('media-library.page', [], false));
        $this->assertSame('/media-library/media', route('media-library.media.index', [], false));
    }

    public function test_policy_is_registered_for_the_media_model(): void
    {
        $this->assertSame(MediaPolicy::class, Gate::getPolicyFor(Media::class));
    }
}

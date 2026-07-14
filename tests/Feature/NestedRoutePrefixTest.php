<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Palgoal\MediaLibrary\Tests\TestCase;

/**
 * Proves the package can be mounted inside an existing dashboard/admin
 * area purely through config — no routes file, controller, or namespace
 * inside the package changes. route_prefix is read during service
 * provider boot(), so it must be set before the application boots (see
 * RoutePrefixIsConfigurableTest for the same pattern with a single-segment
 * prefix).
 */
class NestedRoutePrefixTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('media-library.route_prefix', 'dashboard/media-library');
    }

    public function test_routes_are_nested_under_a_multi_segment_prefix(): void
    {
        $this->assertSame('/dashboard/media-library', route('media-library.page', [], false));
        $this->assertSame('/dashboard/media-library/media', route('media-library.media.index', [], false));
        $this->assertSame('/dashboard/media-library/media/bulk', route('media-library.media.bulk-destroy', [], false));
    }

    public function test_route_names_are_unchanged_when_nested_under_a_dashboard(): void
    {
        // Only the URL changes with route_prefix — route names always stay
        // "media-library.*" so route()/URL helper calls in a host app never
        // need to change when this setting changes.
        $this->assertTrue(Route::has('media-library.page'));
        $this->assertTrue(Route::has('media-library.media.index'));
        $this->assertTrue(Route::has('media-library.media.store'));
        $this->assertTrue(Route::has('media-library.media.show'));
        $this->assertTrue(Route::has('media-library.media.edit'));
        $this->assertTrue(Route::has('media-library.media.update'));
        $this->assertTrue(Route::has('media-library.media.destroy'));
        $this->assertTrue(Route::has('media-library.media.bulk-destroy'));
    }

    public function test_the_nested_page_route_is_reachable(): void
    {
        $this->actingAsUser();

        $response = $this->get('/dashboard/media-library');

        $response->assertOk();
        $response->assertViewIs('media-library::media');
    }
}

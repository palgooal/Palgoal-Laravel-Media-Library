<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Palgoal\MediaLibrary\Tests\TestCase;

/**
 * The route prefix affects service-provider boot() behavior, so it must be
 * set BEFORE the application boots — this gets its own test class that
 * overrides defineEnvironment() rather than mutating config() mid-test
 * against an already-booted app/route collection.
 */
class RoutePrefixIsConfigurableTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('media-library.route_prefix', 'custom-media');
    }

    public function test_routes_use_the_configured_prefix(): void
    {
        $this->assertSame('/custom-media', route('media-library.page', [], false));
        $this->assertSame('/custom-media/media', route('media-library.media.index', [], false));
    }
}

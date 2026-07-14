<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Palgoal\MediaLibrary\Tests\TestCase;

/**
 * Defensive normalization: a route_prefix with stray leading/trailing
 * slashes (an easy typo, e.g. copy-pasted from a URL) must not produce
 * double slashes in the generated routes.
 */
class RoutePrefixIsTrimmedTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('media-library.route_prefix', '/dashboard/media-library/');
    }

    public function test_leading_and_trailing_slashes_are_trimmed(): void
    {
        $this->assertSame('/dashboard/media-library', route('media-library.page', [], false));
        $this->assertSame('/dashboard/media-library/media', route('media-library.media.index', [], false));
    }
}

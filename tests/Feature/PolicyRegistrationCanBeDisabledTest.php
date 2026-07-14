<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Palgoal\MediaLibrary\Models\Media;
use Palgoal\MediaLibrary\Tests\TestCase;

/**
 * media-library.register_policy is read during service-provider boot(), so
 * it must be set BEFORE the application boots — see
 * RoutePrefixIsConfigurableTest for why this needs its own test class.
 */
class PolicyRegistrationCanBeDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('media-library.register_policy', false);
    }

    public function test_no_policy_is_registered_for_media_when_disabled(): void
    {
        $this->assertNull(Gate::getPolicyFor(Media::class));
    }
}

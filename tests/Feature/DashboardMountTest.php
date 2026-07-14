<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Palgoal\MediaLibrary\Tests\TestCase;

/**
 * config('media-library.layout') and config('media-library.breadcrumb')
 * are both read at *render time* inside resources/views/media.blade.php
 * (not during service-provider boot()), so — unlike route_prefix/policy —
 * they can be toggled per test method with a plain config() call; no
 * dedicated defineEnvironment() subclass is needed here.
 */
class DashboardMountTest extends TestCase
{
    public function test_default_layout_is_used_when_no_layout_is_configured(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('media-library.page'));

        $response->assertOk();
        // Marker unique to the package's own self-contained layout
        // (resources/views/layouts/minimal.blade.php).
        $response->assertSee('cdn.tailwindcss.com', false);
    }

    public function test_configured_layout_replaces_the_default_one(): void
    {
        $this->actingAsUser();
        $this->app['view']->addLocation(__DIR__ . '/../Support/resources/views');
        config(['media-library.layout' => 'custom-layout']);

        $response = $this->get(route('media-library.page'));

        $response->assertOk();
        $response->assertSee('Custom Host Dashboard Chrome');
        // The page must not also drag in the package's own standalone
        // layout/CDN script once a host layout has been configured.
        $response->assertDontSee('cdn.tailwindcss.com', false);
    }

    public function test_configured_layout_still_renders_the_page_content(): void
    {
        $this->actingAsUser();
        $this->app['view']->addLocation(__DIR__ . '/../Support/resources/views');
        config(['media-library.layout' => 'custom-layout']);

        $response = $this->get(route('media-library.page'));

        $response->assertOk();
        // The media page's own markup (grid container, upload button) must
        // still be present — the host layout only wraps it, it doesn't
        // replace it.
        $response->assertSee('id="media-grid"', false);
        $response->assertSee('id="btn-upload"', false);
    }

    public function test_breadcrumb_is_not_rendered_by_default(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('media-library.page'));

        $response->assertOk();
        $response->assertDontSee('aria-label="Breadcrumb"', false);
    }

    public function test_breadcrumb_renders_configured_items(): void
    {
        $this->actingAsUser();
        config(['media-library.breadcrumb' => [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Media Library', 'url' => null],
        ]]);

        $response = $this->get(route('media-library.page'));

        $response->assertOk();
        $response->assertSee('aria-label="Breadcrumb"', false);
        $response->assertSee('Dashboard');
        $response->assertSee('Media Library');
        $response->assertSee('href="/dashboard"', false);
    }
}

{{--
    Stand-in "host dashboard layout" used only by DashboardMountTest to
    prove that config('media-library.layout') actually swaps the page's
    wrapper component. A real host application would have its own sidebar,
    topbar, etc. here — this package doesn't know or care what's inside,
    only that the component accepts a default slot.
--}}
<div data-testid="custom-dashboard-layout">
    <header>Custom Host Dashboard Chrome</header>
    <main>{{ $slot }}</main>
</div>

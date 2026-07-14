# Changelog

All notable changes to `palgoal/media-library` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project intends to follow [Semantic Versioning](https://semver.org/)
once it reaches `v1.0.0`.

## [Unreleased]

First standalone audit pass of the package after extraction into its own
repository. No tagged release has been made yet — see the "Packagist
readiness" section of the project's audit report for what's still
outstanding before `v0.1.0`.

### Added — Dashboard integration (non-breaking)

- `config('media-library.route_prefix')` can now be a multi-segment path
  (e.g. `dashboard/media-library`) to mount the package inside an existing
  dashboard/admin area, using Laravel's native `Route::prefix()` support
  for multi-segment prefixes. No route file, controller, or namespace in
  the package changes; route *names* are unaffected (`route_prefix` only
  changes the URL, `media-library.*` route names stay the same). The
  service provider now also trims stray leading/trailing slashes from
  this value defensively.
- `config('media-library.layout')` (default `null`) — the full library
  page now renders inside `<x-dynamic-component :component="...">`
  instead of a hardcoded `<x-media-library::layouts.minimal>` tag. Leave
  it `null` to keep the package's own self-contained layout, or point it
  at any host Blade component that accepts a default slot to make the
  page render as part of your dashboard's chrome instead of as a
  standalone page. Resolution follows Laravel's normal `<x-...>` naming
  rules (unnamespaced names resolve under `resources/views/components/`).
- `config('media-library.breadcrumb')` (default `null`) — optional
  breadcrumb trail rendered above the page header, so the page can sit
  visually inside a dashboard's navigation. Not rendered at all unless
  configured.
- No new dependency on `App\...`, Filament/Nova/Voyager, or any specific
  dashboard layout was introduced — everything above is opt-in through
  `config/media-library.php` only.

### Security

- SVG is no longer allowed by default (`allowed_mimes` / `allowed_mimetypes`
  in `config/media-library.php`). Stored SVG can carry `<script>` /
  event-handler XSS payloads, and the package ships no sanitizer. Re-enable
  it only after adding sanitization — see `SECURITY.md`.
- Fixed a stored-XSS vulnerability in `public/js/media-library.js` and
  `public/js/media-picker.js`: media grid tiles were built by interpolating
  `file_original_name` directly into an `innerHTML` template (as an `alt`
  attribute and as tile text). A crafted filename could inject arbitrary
  HTML/script that ran for anyone browsing the media grid. Tiles are now
  built with `document.createElement` / `textContent` / element properties
  instead of `innerHTML`, which cannot be interpreted as markup.
- `MediaController::bulkDestroy()` previously authorized the bulk-delete
  action against the `create` ability (unrelated to deleting) instead of
  `delete`. It also authorized and deleted items in the same loop, so a
  mid-batch authorization failure left some items deleted and others not.
  Now every item is authorized against `delete` before any item is deleted
  (all-or-nothing).
- `MediaController::show()` and `edit()` previously authorized against
  `viewAny` (a class-level check) instead of `view` on the specific media
  item being requested.

### Fixed

- File uploads no longer leave an orphaned file on disk if the database
  insert fails after the file was already written to storage
  (`MediaController::saveMediaFile()` now rolls back the stored file on a
  DB failure).
- Delete operations (single and bulk) now remove the database record first
  and the storage file second, so a failure partway through cannot leave a
  database record pointing at an already-deleted file. A failure to remove
  the physical file after a successful DB delete is reported but does not
  fail the request (see the audit report for the accepted trade-off).
- `Media::uploader()` no longer silently falls back to the hardcoded class
  `App\Models\User` when no user model can be resolved from config. It now
  throws a clear `RuntimeException` instead of guessing a class that may
  not exist in the host application (this package must not assume any
  specific application's structure).
- `Media::getUrlAttribute()` no longer throws an uncaught exception when
  the configured disk doesn't support URL generation; it returns `null`.
- The `type` query parameter on the listing endpoint is now validated
  against `config('media-library.allowed_types')`
  (`image|video|audio|document|other`) instead of being passed to the
  database query unchecked.

### Added

- `config('media-library.register_policy')` — set to `false` to stop the
  package from calling `Gate::policy()` for you, if your host application
  registers the `Media` policy itself.
- `config('media-library.allowed_types')` — the allow-list used to
  validate the `type` filter.
- `Media::fileExistsOnDisk()` — explicit, opt-in check for whether a
  media item's file still exists on its disk (not called automatically by
  any accessor, since it performs I/O per call).
- Orchestra Testbench test suite (service provider discovery, config
  merging, publishing, migrations, upload/validation, delete/bulk-delete,
  authorization, metadata updates, search/filter/pagination, and a
  regression test asserting no orphaned files survive a failed DB insert).
- GitHub Actions workflow running `composer validate`, PHPUnit, and
  Laravel Pint across a PHP 8.1–8.3 × Laravel 10–12 compatibility matrix.
- `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md`, `phpunit.xml`.

### Changed

- `composer.json`: added explicit `illuminate/routing`, `illuminate/auth`,
  `illuminate/filesystem`, `illuminate/validation`, `illuminate/contracts`
  requirements (previously only `illuminate/support|database|http` were
  declared, even though the package uses routing, auth/Gate, filesystem,
  and validation directly); added `require-dev` (`orchestra/testbench`,
  `phpunit/phpunit`, `laravel/pint`) and `autoload-dev`.
- README rewritten for the package's life as a standalone repository:
  removed claims specific to the original PalgooalWeb monorepo extraction
  (e.g. "this doesn't affect the PalgooalWeb project"), added VCS/Packagist
  installation instructions, an upgrade guide, troubleshooting, and a
  security section.
- Migration now documents the risk of its `Schema::hasTable()` short-circuit
  when a `media` table already exists with an incompatible schema, and why
  `uploader_id` intentionally has no foreign key constraint.

### Breaking changes

See the audit report / README "Upgrade Guide" section. In summary, for
existing installs:

- SVG uploads that previously succeeded will now be rejected (422) unless
  you explicitly re-add `svg` / `image/svg+xml` to `allowed_mimes` /
  `allowed_mimetypes` in your published config.
- The `type` filter on the listing endpoint now returns `422` for values
  outside `image|video|audio|document|other` instead of silently returning
  an empty result set.
- `Media::uploader()` throws instead of silently resolving to
  `App\Models\User` when no user model is configured/resolvable.

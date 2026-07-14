<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route prefix / name
    |--------------------------------------------------------------------------
    |
    | The library page will be served at {prefix} and the JSON API at
    | {prefix}/media/*. Route names are prefixed with "media-library.".
    |
    */
    'route_prefix' => 'media-library',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Applied to every route the package registers. Add your own admin/auth
    | guard here (e.g. ['web', 'auth', 'can:access-admin']).
    |
    */
    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Disk used to store uploaded files. Must be a disk defined in
    | config/filesystems.php. "public" requires `php artisan storage:link`.
    |
    */
    'disk' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Upload base directory
    |--------------------------------------------------------------------------
    |
    | Files are stored under {disk}/{directory}/{Year}/{Month}/{file}.
    |
    */
    'directory' => 'media',

    /*
    |--------------------------------------------------------------------------
    | Upload constraints
    |--------------------------------------------------------------------------
    |
    | SVG is intentionally NOT in the default allow-list. An SVG file can
    | contain inline <script>, <foreignObject>, or event-handler attributes
    | (onload, onerror, ...) that execute when the file is opened directly
    | or embedded in the page — a stored XSS vector. This package ships
    | with no SVG sanitizer, so allowing SVG here would let any user with
    | upload access plant script content that runs in the context of
    | whoever later opens the file.
    |
    | Only add 'svg' / 'image/svg+xml' back to these lists if you have
    | verified (and ideally automated, e.g. via a sanitizer library such as
    | enshrined/svg-sanitize run on every upload) that stored SVGs cannot
    | carry executable content. See SECURITY.md for details.
    |
    */
    'max_upload_size_kb' => 10240, // 10MB
    'allowed_mimes'      => ['jpeg', 'jpg', 'png', 'gif', 'webp'],
    'allowed_mimetypes'  => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | Used for the `uploader()` relation on the Media model. Defaults to the
    | app's configured auth provider model.
    |
    */
    'user_model' => null, // null => config('auth.providers.users.model')

    /*
    |--------------------------------------------------------------------------
    | Policy
    |--------------------------------------------------------------------------
    |
    | Class used to authorize viewAny/view/create/update/delete on Media.
    | Override this in your own app (config/media-library.php after
    | publishing, or by binding your own Gate::policy() in a service
    | provider that boots after this package) to plug into your existing
    | roles/permissions system.
    |
    | IMPORTANT: the shipped default policy (Palgoal\MediaLibrary\Policies\
    | MediaPolicy) only requires an authenticated user. It does NOT scope
    | media to its uploader — any authenticated user can view, edit, or
    | delete ANY media item, including files uploaded by other users. This
    | is a deliberate, documented default meant for single-admin / trusted
    | back-office use, not a safe default for multi-tenant or public-user
    | applications. If your app has multiple untrusted users, replace this
    | policy (e.g. restrict update/delete to `$media->uploader_id === $user->id`
    | or your own role system) before going to production.
    |
    */
    'policy' => \Palgoal\MediaLibrary\Policies\MediaPolicy::class,

    /*
    |--------------------------------------------------------------------------
    | Policy auto-registration
    |--------------------------------------------------------------------------
    |
    | The service provider calls Gate::policy(Media::class, ...) using the
    | class above during boot(). Set this to false if your host application
    | registers the Media policy itself (e.g. via its own AuthServiceProvider
    | $policies map) and you want to avoid a double registration / make sure
    | your own registration order/logic wins.
    |
    */
    'register_policy' => true,

    /*
    |--------------------------------------------------------------------------
    | Allowed logical media types
    |--------------------------------------------------------------------------
    |
    | Used to validate the `type` query parameter on the index/listing
    | endpoint (and by detectFileType()). Values other than these are
    | rejected instead of being passed straight into the database query.
    |
    */
    'allowed_types' => ['image', 'video', 'audio', 'document', 'other'],

];

/*
|--------------------------------------------------------------------------
| Full library page layout
|--------------------------------------------------------------------------
|
| resources/views/vendor/media-library/media.blade.php ships with a
| minimal, self-contained Tailwind layout so the page works out of the box
| in any project. To match your own admin theme, publish the views:
|
|   php artisan vendor:publish --tag=media-library-views
|
| ...then edit the published copy to wrap the content in your own
| <x-dashboard-layout> (or whatever layout component your app uses).
| Laravel automatically prefers the published copy over the package's.
|
*/

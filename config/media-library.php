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
    */
    'max_upload_size_kb' => 10240, // 10MB
    'allowed_mimes'      => ['jpeg', 'jpg', 'png', 'gif', 'webp', 'svg'],
    'allowed_mimetypes'  => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],

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
    | Class used to authorize viewAny/create/update/delete on Media. Override
    | this in your own app (config/media-library.php after publishing, or by
    | binding your own Gate::policy() in a service provider that boots after
    | this package) to plug into your existing roles/permissions system.
    |
    */
    'policy' => \Palgoal\MediaLibrary\Policies\MediaPolicy::class,

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

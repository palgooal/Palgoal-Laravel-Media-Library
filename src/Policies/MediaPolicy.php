<?php

namespace Palgoal\MediaLibrary\Policies;

use Palgoal\MediaLibrary\Models\Media;

/**
 * Default authorization policy for the media library.
 *
 * Out of the box this simply requires an authenticated user (the "auth"
 * middleware already guarantees that before these methods even run). If
 * your host project has its own roles/permissions system, don't edit this
 * file — instead publish the config and point `media-library.policy` at
 * your own policy class, or register it yourself:
 *
 *   Gate::policy(\Palgoal\MediaLibrary\Models\Media::class, \App\Policies\MediaPolicy::class);
 *
 * in a service provider that boots after MediaLibraryServiceProvider.
 */
class MediaPolicy
{
    public function viewAny($user): bool
    {
        return (bool) $user;
    }

    public function view($user, Media $media): bool
    {
        return (bool) $user;
    }

    public function create($user): bool
    {
        return (bool) $user;
    }

    public function update($user, Media $media): bool
    {
        return (bool) $user;
    }

    public function delete($user, Media $media): bool
    {
        return (bool) $user;
    }
}

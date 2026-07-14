<?php

namespace Palgoal\MediaLibrary\Tests\Support;

use Palgoal\MediaLibrary\Models\Media;

/**
 * A restrictive policy used only by AuthorizationTest to prove that
 * MediaController checks authorization per-item (and, for bulk actions,
 * for every item before deleting any of them) rather than relying on a
 * blanket "any authenticated user" check.
 */
class OwnerOnlyMediaPolicy
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
        return $user && $media->uploader_id === $user->id;
    }

    public function delete($user, Media $media): bool
    {
        return $user && $media->uploader_id === $user->id;
    }
}

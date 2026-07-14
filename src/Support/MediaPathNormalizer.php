<?php

namespace Palgoal\MediaLibrary\Support;

use Palgoal\MediaLibrary\Models\Media;

/**
 * Unified media path/ID normalizer.
 *
 * Handy when migrating an existing project's string-path media columns
 * (e.g. `logo` storing "media/2025/06/x.png") to FK `*_media_id` columns
 * that reference this package's `media` table.
 *
 * Responsibilities:
 *  - Trim whitespace
 *  - Remove leading slashes
 *  - Strip a `storage/` prefix that some write-paths prepend
 *  - Return null for empty strings or external URLs (not media paths)
 *  - Resolve numeric IDs -> media.file_path
 *  - Resolve path strings -> media.id (for backfill / dual-write)
 */
class MediaPathNormalizer
{
    /**
     * Normalize a raw stored value to a clean `file_path` string suitable
     * for looking up a record in `media.file_path`.
     *
     * @param  mixed  $value
     */
    public static function normalize($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (
            str_starts_with($normalized, 'http://')  ||
            str_starts_with($normalized, 'https://') ||
            str_starts_with($normalized, '//')
        ) {
            return null;
        }

        $normalized = ltrim($normalized, '/');

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Resolve a raw stored value to a `media.id` integer.
     *
     * Resolution order:
     *  1. Numeric string -> treated as an existing media.id (direct lookup)
     *  2. Path string    -> normalize, then look up by file_path
     *  3. External URL   -> null (cannot be in the media table)
     *  4. Empty / null   -> null
     *
     * @param  mixed  $value
     */
    public static function resolveToMediaId($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $media = Media::find((int) $raw);
            return $media?->id;
        }

        if (
            str_starts_with($raw, 'http://')  ||
            str_starts_with($raw, 'https://') ||
            str_starts_with($raw, '//')
        ) {
            return null;
        }

        $path = static::normalize($raw);
        if ($path === null) {
            return null;
        }

        $media = Media::where('file_path', $path)->first();
        return $media?->id;
    }

    /**
     * Convenience: resolve multiple values to an array of media IDs.
     * Null entries (orphans) are included as null at the same index.
     *
     * @param  array<mixed>  $values
     * @return array<int|null>
     */
    public static function resolveMany(array $values): array
    {
        return array_map(
            fn ($v) => static::resolveToMediaId($v),
            $values
        );
    }
}

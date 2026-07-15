<?php

namespace Palgoal\MediaLibrary\Support;

/**
 * Pure input normalizer for "which media IDs did the client select"
 * values coming out of the Picker's hidden input (or any other source):
 * a comma-separated string ("5,8,10"), an array possibly mixing ints and
 * numeric strings, a single int, or null.
 *
 * This class does NOT query the database and does NOT validate that the
 * IDs actually exist in the `media` table — it only cleans up the raw
 * shape of the input. Existence should still be validated by the host
 * application (see docs/HAS-MEDIA.md "Validation") and/or is filtered a
 * second time as a safety net by HasMedia::attachMedia()/syncMedia().
 *
 * Typical usage:
 *
 *   $product->syncMedia(
 *       MediaSelection::parse($request->input('gallery_media_ids')),
 *       'gallery'
 *   );
 */
class MediaSelection
{
    /**
     * Normalize a raw media-selection value into a deduplicated array of
     * positive integer IDs, preserving the order of first occurrence.
     *
     * Rules:
     * - `null` -> `[]`.
     * - `int` -> `[$value]` if positive, otherwise `[]`.
     * - `string` -> split on `,`, each piece trimmed; only pieces that
     *   are purely digits (after trimming) and > 0 are kept.
     * - `array` -> each element handled the same way an individual
     *   string/int value would be (ints kept if positive, numeric
     *   strings trimmed+parsed, anything else — floats, bools, nested
     *   arrays, non-numeric strings, empty strings — silently ignored).
     *
     * @param  array<array-key, mixed>|string|int|null  $value
     * @return array<int, int>
     */
    public static function parse(array|string|int|null $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_int($value)) {
            return $value > 0 ? [$value] : [];
        }

        $items = is_array($value) ? $value : explode(',', $value);

        $ids = [];

        foreach ($items as $item) {
            $id = static::parseSingle($item);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Parse one raw item (as found inside an array, or one comma-split
     * piece of a string) into a positive integer, or null if it isn't
     * one.
     */
    protected static function parseSingle(mixed $item): ?int
    {
        if (is_int($item)) {
            return $item > 0 ? $item : null;
        }

        if (! is_string($item)) {
            // Floats, bools, nested arrays, objects, null: not a valid ID.
            return null;
        }

        $trimmed = trim($item);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        $int = (int) $trimmed;

        return $int > 0 ? $int : null;
    }
}

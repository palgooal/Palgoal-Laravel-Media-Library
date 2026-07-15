<?php

namespace Palgoal\MediaLibrary\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Palgoal\MediaLibrary\Models\Media;

/**
 * Opt-in trait that lets ANY Eloquent model attach media from this
 * package's shared library, grouped into named "collections" (logo,
 * cover, gallery, documents, avatars, banners, ... — any string you
 * choose; nothing is hardcoded or enumerated here).
 *
 * Usage:
 *
 *   use Palgoal\MediaLibrary\Concerns\HasMedia;
 *
 *   class Product extends Model
 *   {
 *       use HasMedia;
 *   }
 *
 * Requires the OPTIONAL `mediables` table, published and migrated once:
 *
 *   php artisan vendor:publish --tag=media-library-relations-migration
 *   php artisan migrate
 *
 * A model that never uses this trait is completely unaffected — no
 * table, no query, no behavior change (see docs/HAS-MEDIA.md).
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasMedia
{
    /**
     * Package boot hook, called automatically by Eloquent for every trait
     * named `HasMedia` used by a model (Laravel's standard "boot{Trait}"
     * convention — the same mechanism `Illuminate\Database\Eloquent\SoftDeletes`
     * uses for its own `bootSoftDeletes()`).
     *
     * Registers automatic `mediables` cleanup when a model instance is
     * actually removed from the database, so attachments don't silently
     * accumulate as orphaned pivot rows forever. Rules:
     *
     * - Model WITHOUT SoftDeletes: every `delete()` removes its
     *   `mediables` rows (the row is really gone, so the attachments
     *   pointing at it should go too).
     * - Model WITH SoftDeletes: a *soft* delete (`delete()` while
     *   `deleted_at` is merely being set) does NOT touch `mediables` —
     *   the row still exists and can be restored, so its attachments
     *   must still be there when it comes back. Restore therefore needs
     *   no special handling at all: since nothing was ever deleted, the
     *   pivot rows are simply still there.
     * - Model WITH SoftDeletes, `forceDelete()`: DOES clean up
     *   `mediables` — the row is really gone this time. `forceDelete()`
     *   still fires the `deleting` event with `isForceDeleting()` true,
     *   which is how this listener tells the two cases apart.
     */
    protected static function bootHasMedia(): void
    {
        static::deleting(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            DB::table($model->getMediaPivotTable())
                ->where('mediable_type', $model->getMorphClass())
                ->where('mediable_id', $model->getKey())
                ->delete();
        });
    }

    /**
     * Base relation: every media item attached to this model, across ALL
     * collections, ordered by `sort_order`. Pivot columns `collection`
     * and `sort_order` (plus timestamps) are available via `->pivot` on
     * each returned Media instance.
     *
     * Uses `$this->getMorphClass()` implicitly (via Eloquent's own
     * morphToMany() internals) for the stored `mediable_type` value, so
     * this relation is `Relation::morphMap()`-aware out of the box — see
     * docs/HAS-MEDIA.md "Using a morph map".
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->withPivot(['collection', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * All media in a single named collection, ordered by `sort_order`.
     *
     * Behavior (documented, not incidental):
     * - If the `media` relation is ALREADY loaded on this model instance
     *   (`relationLoaded('media')` is true) — regardless of whether that
     *   eager load itself was constrained (e.g.
     *   `Product::with(['media' => fn ($q) => $q->wherePivot(...)])`) —
     *   this filters ONLY the already-loaded items in memory and never
     *   issues a fresh query. This avoids N+1 queries when iterating many
     *   models that were eager-loaded together, but it also means a
     *   constrained eager-load that excluded this collection will make
     *   this method return an empty Collection, NOT silently re-query to
     *   "correct" it. Once a relation is loaded, loaded data is treated
     *   as the source of truth — the same convention Eloquent itself uses
     *   for `relationLoaded()` everywhere else.
     * - Otherwise, runs a fresh query scoped to this collection.
     */
    public function mediaCollection(string $collection = 'default'): Collection
    {
        $collection = $this->normalizeMediaCollectionName($collection);

        if ($this->relationLoaded('media')) {
            return $this->getRelation('media')
                ->filter(fn (Media $media) => $media->pivot->collection === $collection)
                ->sortBy(fn (Media $media) => (int) $media->pivot->sort_order)
                ->values();
        }

        return $this->media()
            ->wherePivot('collection', $collection)
            ->get();
    }

    /**
     * First media item in a collection (by `sort_order`), or null.
     */
    public function firstMedia(string $collection = 'default'): ?Media
    {
        return $this->mediaCollection($collection)->first();
    }

    /**
     * URL of the first media item in a collection, or `$default`.
     *
     * `$default` is also returned when a media item exists but its `url`
     * accessor itself returns null (e.g. the configured disk doesn't
     * support URL generation — see `Media::getUrlAttribute()`), not just
     * when the collection is empty.
     */
    public function firstMediaUrl(string $collection = 'default', ?string $default = null): ?string
    {
        return $this->firstMedia($collection)?->url ?? $default;
    }

    /**
     * Attach one or more media items to a collection WITHOUT touching
     * any existing attachment (in this or any other collection).
     *
     * Accepts a single ID, a single Media model, or an array mixing IDs
     * and Media models (e.g. `[5, 8, $mediaModel, '10']`).
     *
     * - Input is normalized to a deduplicated list of integer IDs
     *   (first-occurrence order preserved).
     * - IDs that don't correspond to an existing `media` row are silently
     *   dropped (this is a data-integrity safety net around the
     *   `media_id` foreign key, NOT a replacement for validating
     *   user-supplied input in your controller — see docs/HAS-MEDIA.md
     *   "Validation").
     * - An ID already attached to THIS collection is left untouched (no
     *   duplicate row, no unique-constraint error). The same ID already
     *   attached to a DIFFERENT collection is attached again here too —
     *   reusing one media item across collections/models is expected.
     * - Newly attached items are appended after the current highest
     *   `sort_order` in this collection.
     * - Never removes any existing row. Use syncMedia() when you want
     *   replacement semantics.
     */
    public function attachMedia(int|Media|array $media, string $collection = 'default'): static
    {
        $collection = $this->normalizeMediaCollectionName($collection);
        $ids = $this->normalizeMediaIdsInput($media);

        if (empty($ids)) {
            return $this;
        }

        $ids = $this->filterExistingMediaIds($ids);

        if (empty($ids)) {
            return $this;
        }

        DB::transaction(function () use ($ids, $collection) {
            $alreadyAttached = $this->newMediaPivotQuery($collection)
                ->whereIn('media_id', $ids)
                ->pluck('media_id')
                ->all();

            $toAttach = array_values(array_diff($ids, $alreadyAttached));

            if (empty($toAttach)) {
                return;
            }

            $nextOrder = (int) ($this->newMediaPivotQuery($collection)->max('sort_order') ?? -1) + 1;

            $now = now();
            $rows = [];

            foreach (array_values($toAttach) as $offset => $mediaId) {
                $rows[] = [
                    'media_id' => $mediaId,
                    'mediable_type' => $this->getMorphClass(),
                    'mediable_id' => $this->getKey(),
                    'collection' => $collection,
                    'sort_order' => $nextOrder + $offset,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table($this->getMediaPivotTable())->insert($rows);
        });

        return $this;
    }

    /**
     * Replace the ENTIRE contents of a single collection with the given
     * media IDs, in the given order (array position becomes `sort_order`:
     * index 0 -> sort_order 0, index 1 -> sort_order 1, ...).
     *
     * Scope is strictly limited to `$collection` on THIS model:
     * - Removes rows in this collection whose media_id is NOT in $mediaIds.
     * - Adds rows for IDs in $mediaIds not already in this collection.
     * - Keeps (and re-numbers the sort_order of) IDs present in both.
     * - Every other collection on this model (and every other model
     *   entirely) is never read or written by this call.
     * - An empty array clears the collection (equivalent to
     *   clearMediaCollection(), implemented via the same code path).
     * - Runs inside DB::transaction() — either the whole collection ends
     *   up in its new state, or (on failure) none of it changes.
     *
     * Deliberately does NOT call `$this->media()->sync($ids)` — Eloquent's
     * built-in sync() operates on the WHOLE pivot relation for this
     * model, with no concept of "collection", so it would delete
     * attachments in every other collection too. This method only ever
     * touches rows that already match `collection = $collection`.
     */
    public function syncMedia(array $mediaIds, string $collection = 'default'): static
    {
        $collection = $this->normalizeMediaCollectionName($collection);
        $ids = $this->filterExistingMediaIds($this->normalizeMediaIdsInput($mediaIds));

        DB::transaction(function () use ($ids, $collection) {
            if (empty($ids)) {
                $this->newMediaPivotQuery($collection)->delete();

                return;
            }

            $this->newMediaPivotQuery($collection)->whereNotIn('media_id', $ids)->delete();

            $existingIds = $this->newMediaPivotQuery($collection)->pluck('media_id')->all();
            $now = now();

            foreach ($ids as $sortOrder => $mediaId) {
                if (in_array($mediaId, $existingIds, true)) {
                    $this->newMediaPivotQuery($collection)
                        ->where('media_id', $mediaId)
                        ->update(['sort_order' => $sortOrder, 'updated_at' => $now]);

                    continue;
                }

                DB::table($this->getMediaPivotTable())->insert([
                    'media_id' => $mediaId,
                    'mediable_type' => $this->getMorphClass(),
                    'mediable_id' => $this->getKey(),
                    'collection' => $collection,
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return $this;
    }

    /**
     * Detach media from this model.
     *
     * - `detachMedia(5)` (no collection): removes media #5 from EVERY
     *   collection on this model.
     * - `detachMedia(5, 'gallery')`: removes media #5 from the `gallery`
     *   collection only — leaves it attached in any other collection.
     *
     * Accepts an ID, a Media model, or an array of either/both. Never
     * deletes the underlying `media` row — only `mediables` pivot rows.
     */
    public function detachMedia(int|Media|array $media, ?string $collection = null): static
    {
        $ids = $this->normalizeMediaIdsInput($media);

        if (empty($ids)) {
            return $this;
        }

        $collection = $collection !== null ? $this->normalizeMediaCollectionName($collection) : null;

        $this->newMediaPivotQuery($collection)->whereIn('media_id', $ids)->delete();

        return $this;
    }

    /**
     * Remove every attachment in a single collection. Deletes ONLY
     * `mediables` pivot rows — never touches `media` files or rows, and
     * never touches any other collection.
     */
    public function clearMediaCollection(string $collection = 'default'): static
    {
        $collection = $this->normalizeMediaCollectionName($collection);

        $this->newMediaPivotQuery($collection)->delete();

        return $this;
    }

    /**
     * Whether this model has at least one media item in the given
     * collection. Uses the already-loaded `media` relation in memory if
     * available (same "loaded data is authoritative" rule as
     * mediaCollection()), otherwise runs a lightweight `exists()` query.
     */
    public function hasMedia(string $collection = 'default'): bool
    {
        $collection = $this->normalizeMediaCollectionName($collection);

        if ($this->relationLoaded('media')) {
            return $this->getRelation('media')
                ->contains(fn (Media $media) => $media->pivot->collection === $collection);
        }

        return $this->newMediaPivotQuery($collection)->exists();
    }

    /**
     * Name of the pivot table used by this trait. Not configurable via
     * config/media-library.php by design (see docs/HAS-MEDIA.md) — kept
     * as a single protected method so a host application could still
     * override it in the rare case of a genuine naming conflict, without
     * this package needing a dedicated config key for it.
     */
    protected function getMediaPivotTable(): string
    {
        return 'mediables';
    }

    /**
     * Fresh query builder over the `mediables` rows that belong to THIS
     * model instance, optionally further scoped to one collection.
     * Every attach/detach/sync/clear/hasMedia method funnels through
     * this so the "which rows belong to this model (+collection)" logic
     * exists in exactly one place.
     */
    protected function newMediaPivotQuery(?string $collection = null): QueryBuilder
    {
        $query = DB::table($this->getMediaPivotTable())
            ->where('mediable_type', $this->getMorphClass())
            ->where('mediable_id', $this->getKey());

        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        return $query;
    }

    /**
     * Normalize a collection name: trim whitespace, fall back to
     * `'default'` for an empty string. Deliberately does NOT lowercase,
     * slugify, or otherwise rewrite the value — a collection name like
     * `Gallery` and `gallery` are treated as genuinely different
     * collections, because arbitrary host-chosen names may be meaningful
     * to the host project in ways this package can't and shouldn't guess.
     *
     * @throws InvalidArgumentException if the name exceeds 255 characters
     *         (the `collection` column's length). Rejected instead of
     *         silently truncated, because truncating could make two
     *         distinct long names collide on the same 255-char prefix.
     */
    protected function normalizeMediaCollectionName(string $collection): string
    {
        $collection = trim($collection);

        if ($collection === '') {
            return 'default';
        }

        if (strlen($collection) > 255) {
            throw new InvalidArgumentException(
                'Palgoal\\MediaLibrary: media collection name exceeds 255 characters.'
            );
        }

        return $collection;
    }

    /**
     * Normalize attach/detach/sync input (int, Media, or a mixed array of
     * either/both — plus tolerated numeric strings) into a deduplicated
     * list of integer IDs, preserving first-occurrence order. Anything
     * that isn't a positive integer, a numeric string, or a Media
     * instance is silently skipped — this mirrors MediaSelection::parse()
     * and is a normalization step, not validation; see
     * docs/HAS-MEDIA.md "Validation" for why request input should still
     * be validated by the host application before it reaches here.
     *
     * @param  int|Media|array<int, int|string|Media>  $media
     * @return array<int, int>
     */
    protected function normalizeMediaIdsInput(int|Media|array $media): array
    {
        $items = is_array($media) ? $media : [$media];

        $ids = [];

        foreach ($items as $item) {
            if ($item instanceof Media) {
                $ids[] = (int) $item->getKey();

                continue;
            }

            if (is_int($item) && $item > 0) {
                $ids[] = $item;

                continue;
            }

            if (is_string($item) && ctype_digit(trim($item)) && (int) trim($item) > 0) {
                $ids[] = (int) trim($item);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Filter a list of IDs down to those that actually exist in the
     * `media` table (order preserved). Used by attachMedia()/syncMedia()
     * so a stale/invalid ID degrades to "silently not attached" instead
     * of a foreign-key constraint violation.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function filterExistingMediaIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $existing = Media::query()->whereIn('id', $ids)->pluck('id')->all();

        return array_values(array_intersect($ids, $existing));
    }
}

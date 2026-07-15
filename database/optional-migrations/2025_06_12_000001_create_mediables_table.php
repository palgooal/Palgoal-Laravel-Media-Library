<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPTIONAL relations migration — NOT loaded automatically by
 * MediaLibraryServiceProvider (no loadMigrationsFrom() call touches this
 * file). It only reaches a host application's database/migrations/
 * directory when the developer explicitly runs:
 *
 *   php artisan vendor:publish --tag=media-library-relations-migration
 *   php artisan migrate
 *
 * This is a deliberate opt-in: attaching media to host models via
 * Palgoal\MediaLibrary\Concerns\HasMedia is an additional feature, not a
 * requirement of the base package (upload/browse/pick still work with only
 * the `media` table). Projects that never `use HasMedia;` never need this
 * table at all.
 *
 * The filename's timestamp is fixed (not generated at publish time), so
 * publishing this tag more than once always writes to the same target
 * path (database/migrations/2025_06_12_000001_create_mediables_table.php)
 * and overwrites in place instead of creating duplicate migration files
 * with different timestamps.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema::hasTable() short-circuit, same convention as the package's
     * main `media` migration: if a `mediables` table already exists (e.g.
     * republishing after a manual edit, or a table created by other
     * means), this does nothing rather than failing on a duplicate-table
     * error. As with the `media` migration, this only checks the table
     * *name* — it does not verify columns/indexes match what this file
     * would have created.
     */
    public function up(): void
    {
        if (Schema::hasTable('mediables')) {
            return;
        }

        Schema::create('mediables', function (Blueprint $table) {
            $table->id();

            // Cascade delete: removing a Media row also removes every
            // pivot row that referenced it, so host models never end up
            // with a dangling media_id pointing at nothing.
            $table->foreignId('media_id')
                ->constrained('media')
                ->cascadeOnDelete();

            // Adds both `mediable_type` (string) and `mediable_id`
            // (unsigned big integer) plus Laravel's default index on the
            // pair. mediable_id is intentionally NOT a real foreign key
            // (it can point at any host table — Product, User, Company,
            // ... — which this package has no knowledge of).
            $table->morphs('mediable');

            // Free-form label chosen by the host application at call time
            // (attachMedia($id, 'gallery'), attachMedia($id, 'logo'), ...).
            // Deliberately NOT an enum / constrained list — this package
            // must not assume which collection names any given project
            // needs (see Concerns/HasMedia and docs/HAS-MEDIA.md).
            $table->string('collection')->default('default');

            // Manual ordering within a (mediable, collection) group.
            // Maintained by HasMedia::attachMedia()/syncMedia() — see
            // that trait for how values are assigned.
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Prevents the exact same (media, model, collection) triple
            // from being attached twice. The SAME media_id CAN still
            // appear multiple times for the same model as long as the
            // collection differs (e.g. media #5 as both `logo` and
            // `gallery` on the same Product) — that reuse is the whole
            // point of a shared media library. Attaching an
            // already-attached (media, model, collection) triple again is
            // treated as a no-op by HasMedia::attachMedia(), not an error.
            $table->unique(
                ['media_id', 'mediable_type', 'mediable_id', 'collection'],
                'mediables_unique_attachment'
            );

            // Covers the two query shapes HasMedia relies on: "all media
            // for this model in this collection" and "... ordered by
            // sort_order" — both satisfied by a single composite index
            // without an extra sort/filesort step.
            $table->index(
                ['mediable_type', 'mediable_id', 'collection', 'sort_order'],
                'mediables_collection_order_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops ONLY `mediables` — never touches `media` (that table belongs
     * to the base, always-loaded migration and has its own lifecycle).
     */
    public function down(): void
    {
        Schema::dropIfExists('mediables');
    }
};

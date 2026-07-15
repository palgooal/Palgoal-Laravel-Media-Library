<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Palgoal\MediaLibrary\Tests\TestCase;

class MediablesMigrationTest extends TestCase
{
    public function test_mediables_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('mediables'));

        $expected = [
            'id', 'media_id', 'mediable_type', 'mediable_id',
            'collection', 'sort_order', 'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(Schema::hasColumn('mediables', $column), "Missing column: {$column}");
        }
    }

    public function test_mediables_table_has_the_expected_unique_and_composite_indexes(): void
    {
        $indexes = collect(Schema::getIndexes('mediables'))->keyBy('name');

        $this->assertTrue($indexes->has('mediables_unique_attachment'), 'Missing mediables_unique_attachment index.');
        $this->assertTrue($indexes['mediables_unique_attachment']['unique']);
        $this->assertEqualsCanonicalizing(
            ['media_id', 'mediable_type', 'mediable_id', 'collection'],
            $indexes['mediables_unique_attachment']['columns']
        );

        $this->assertTrue($indexes->has('mediables_collection_order_index'), 'Missing mediables_collection_order_index index.');
        $this->assertEqualsCanonicalizing(
            ['mediable_type', 'mediable_id', 'collection', 'sort_order'],
            $indexes['mediables_collection_order_index']['columns']
        );
    }

    public function test_unique_index_rejects_the_exact_same_attachment_twice_at_the_database_level(): void
    {
        $media = $this->createMedia();

        DB::table('mediables')->insert([
            'media_id' => $media->id,
            'mediable_type' => 'App\\Models\\Product',
            'mediable_id' => 1,
            'collection' => 'default',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('mediables')->insert([
            'media_id' => $media->id,
            'mediable_type' => 'App\\Models\\Product',
            'mediable_id' => 1,
            'collection' => 'default',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_running_the_migration_again_is_a_safe_no_op(): void
    {
        $migration = require __DIR__ . '/../../database/optional-migrations/2025_06_12_000001_create_mediables_table.php';
        $migration->up();

        $this->assertTrue(Schema::hasTable('mediables'));
    }
}

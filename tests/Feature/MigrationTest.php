<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Palgoal\MediaLibrary\Tests\TestCase;

class MigrationTest extends TestCase
{
    public function test_media_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('media'));

        $expected = [
            'id', 'file_name', 'file_original_name', 'file_path',
            'file_extension', 'mime_type', 'size', 'file_type', 'disk',
            'width', 'height', 'uploader_id', 'alt', 'title', 'caption',
            'description', 'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(Schema::hasColumn('media', $column), "Missing column: {$column}");
        }
    }

    public function test_running_the_migration_again_is_a_safe_no_op(): void
    {
        // The migration already ran once via defineDatabaseMigrations().
        // Running up() a second time must not throw, because the table
        // now already exists (Schema::hasTable short-circuit).
        $migration = require __DIR__ . '/../../database/migrations/2025_06_11_000000_create_media_table.php';
        $migration->up();

        $this->assertTrue(Schema::hasTable('media'));
    }
}

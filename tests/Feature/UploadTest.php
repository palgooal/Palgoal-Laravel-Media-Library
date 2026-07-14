<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Palgoal\MediaLibrary\Tests\TestCase;
use RuntimeException;

class UploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_authenticated_user_can_upload_a_single_image(): void
    {
        $this->actingAsUser();

        $file = UploadedFile::fake()->image('logo.png', 100, 80);

        $response = $this->postJson(route('media-library.media.store'), [
            'image' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('file_original_name', 'logo.png');
        $response->assertJsonPath('file_type', 'image');
        $response->assertJsonPath('width', 100);
        $response->assertJsonPath('height', 80);

        $this->assertDatabaseCount('media', 1);

        $media = Media::first();
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_authenticated_user_can_upload_multiple_files(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('media-library.media.store'), [
            'files' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'uploaded');
        $this->assertDatabaseCount('media', 2);
    }

    public function test_disallowed_mime_type_is_rejected(): void
    {
        $this->actingAsUser();

        $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

        $response = $this->postJson(route('media-library.media.store'), [
            'image' => $file,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_file_larger_than_configured_limit_is_rejected(): void
    {
        $this->actingAsUser();
        config(['media-library.max_upload_size_kb' => 100]);

        $file = UploadedFile::fake()->image('big.jpg')->size(200); // KB, over the 100KB limit

        $response = $this->postJson(route('media-library.media.store'), [
            'image' => $file,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_svg_is_rejected_by_default(): void
    {
        $this->actingAsUser();

        $file = UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml');

        $response = $this->postJson(route('media-library.media.store'), [
            'image' => $file,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_guest_cannot_upload(): void
    {
        $file = UploadedFile::fake()->image('logo.png');

        $response = $this->postJson(route('media-library.media.store'), [
            'image' => $file,
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_without_any_file_field_returns_422(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(route('media-library.media.store'), []);

        $response->assertStatus(422);
    }

    public function test_no_orphan_file_is_left_when_the_database_insert_fails(): void
    {
        $this->actingAsUser();

        // Simulate a DB-layer failure that happens AFTER the file has
        // already been written to storage (e.g. a constraint violation,
        // connection drop, etc.) by throwing from the model's "creating"
        // event, which fires just before the INSERT.
        Media::creating(function () {
            throw new RuntimeException('Simulated database failure.');
        });

        $file = UploadedFile::fake()->image('logo.png');

        $response = $this->postJson(route('media-library.media.store'), [
            'image' => $file,
        ]);

        $response->assertStatus(500);
        $this->assertDatabaseCount('media', 0);

        $orphans = collect(Storage::disk('public')->allFiles())
            ->filter(fn ($path) => str_ends_with($path, '.png'));

        $this->assertCount(0, $orphans, 'An orphaned file was left on disk after a failed DB insert.');
    }
}

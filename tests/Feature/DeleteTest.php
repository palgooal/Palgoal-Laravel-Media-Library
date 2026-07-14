<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Palgoal\MediaLibrary\Tests\TestCase;

class DeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function uploadedMedia(): Media
    {
        $path = Storage::disk('public')->putFile('media', UploadedFile::fake()->image('x.png'));

        return Media::create([
            'file_name'           => basename($path),
            'file_original_name'  => 'x.png',
            'file_path'           => $path,
            'file_extension'      => 'png',
            'mime_type'           => 'image/png',
            'size'                => 1000,
            'file_type'           => 'image',
            'disk'                => 'public',
        ]);
    }

    public function test_authenticated_user_can_delete_a_single_media_item_and_its_file(): void
    {
        $this->actingAsUser();
        $media = $this->uploadedMedia();

        $response = $this->deleteJson(route('media-library.media.destroy', $media->id));

        $response->assertOk();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->file_path);
    }

    public function test_bulk_delete_removes_all_requested_items(): void
    {
        $this->actingAsUser();
        $a = $this->uploadedMedia();
        $b = $this->uploadedMedia();

        $response = $this->deleteJson(route('media-library.media.bulk-destroy'), [
            'ids' => [$a->id, $b->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('deleted', 2);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($a->file_path);
        Storage::disk('public')->assertMissing($b->file_path);
    }

    public function test_bulk_delete_rejects_unknown_ids_and_deletes_nothing(): void
    {
        $this->actingAsUser();
        $a = $this->uploadedMedia();

        $response = $this->deleteJson(route('media-library.media.bulk-destroy'), [
            'ids' => [$a->id, 999999],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 1);
        Storage::disk('public')->assertExists($a->file_path);
    }

    public function test_bulk_delete_requires_a_non_empty_ids_array(): void
    {
        $this->actingAsUser();

        $response = $this->deleteJson(route('media-library.media.bulk-destroy'), [
            'ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_delete(): void
    {
        $media = $this->uploadedMedia();

        $response = $this->deleteJson(route('media-library.media.destroy', $media->id));

        $response->assertStatus(401);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_deleting_a_nonexistent_media_item_returns_404(): void
    {
        $this->actingAsUser();

        $response = $this->deleteJson(route('media-library.media.destroy', 999999));

        $response->assertStatus(404);
    }
}

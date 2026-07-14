<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Palgoal\MediaLibrary\Tests\TestCase;

class UpdateAndListingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function makeMedia(array $overrides = []): Media
    {
        $path = Storage::disk('public')->putFile('media', UploadedFile::fake()->image('x.png'));

        return Media::create(array_merge([
            'file_name'          => basename($path),
            'file_original_name' => 'x.png',
            'file_path'          => $path,
            'file_extension'     => 'png',
            'mime_type'          => 'image/png',
            'size'               => 1234,
            'file_type'          => 'image',
            'disk'               => 'public',
        ], $overrides));
    }

    public function test_owner_can_update_media_metadata_without_touching_the_file(): void
    {
        $this->actingAsUser();
        $media = $this->makeMedia();

        $response = $this->putJson(route('media-library.media.update', $media->id), [
            'title'       => 'New title',
            'alt'         => 'Alt text',
            'caption'     => 'Caption',
            'description' => 'Description',
        ]);

        $response->assertOk();
        $response->assertJsonPath('media.title', 'New title');

        $media->refresh();
        $this->assertSame('New title', $media->title);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_update_validates_field_lengths(): void
    {
        $this->actingAsUser();
        $media = $this->makeMedia();

        $response = $this->putJson(route('media-library.media.update', $media->id), [
            'title' => str_repeat('a', 300),
        ]);

        $response->assertStatus(422);
    }

    public function test_index_filters_by_type(): void
    {
        $this->actingAsUser();
        $this->makeMedia(['file_type' => 'image']);
        $this->makeMedia(['file_type' => 'document', 'mime_type' => 'application/pdf', 'file_extension' => 'pdf']);

        $response = $this->getJson(route('media-library.media.index', ['type' => 'document']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.file_type', 'document');
    }

    public function test_index_rejects_a_type_value_outside_the_allowed_list(): void
    {
        $this->actingAsUser();

        $response = $this->getJson(route('media-library.media.index', ['type' => 'not-a-real-type']));

        $response->assertStatus(422);
    }

    public function test_index_search_matches_original_name_and_title(): void
    {
        $this->actingAsUser();
        $this->makeMedia(['file_original_name' => 'sunset-beach.png', 'title' => null]);
        $this->makeMedia(['file_original_name' => 'other.png', 'title' => 'Mountain view']);

        $response = $this->getJson(route('media-library.media.index', ['search' => 'sunset']));
        $response->assertOk()->assertJsonCount(1, 'data');

        $response = $this->getJson(route('media-library.media.index', ['search' => 'Mountain']));
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_index_paginates_results(): void
    {
        $this->actingAsUser();
        foreach (range(1, 5) as $i) {
            $this->makeMedia();
        }

        $response = $this->getJson(route('media-library.media.index', ['per_page' => 2]));

        $response->assertOk();
        $response->assertJsonPath('per_page', 2);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('total', 5);
    }

    public function test_per_page_outside_valid_range_falls_back_to_the_default(): void
    {
        $this->actingAsUser();
        $this->makeMedia();

        $response = $this->getJson(route('media-library.media.index', ['per_page' => 999]));

        $response->assertOk();
        $response->assertJsonPath('per_page', 40);
    }

    public function test_index_returns_the_blade_view_for_non_json_requests(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('media-library.page'));

        $response->assertOk();
        $response->assertViewIs('media-library::media');
    }

    public function test_media_resource_exposes_the_expected_json_shape(): void
    {
        $this->actingAsUser();
        $media = $this->makeMedia(['title' => 'T', 'alt' => 'A']);

        $response = $this->getJson(route('media-library.media.show', $media->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'id', 'file_name', 'file_original_name', 'file_path',
            'file_extension', 'mime_type', 'size', 'file_type', 'disk',
            'width', 'height', 'uploader_id', 'alt', 'title', 'caption',
            'description', 'created_at', 'updated_at', 'url', 'readable_size',
        ]);
    }

    public function test_edit_endpoint_behaves_like_show_for_backward_compatibility(): void
    {
        $this->actingAsUser();
        $media = $this->makeMedia();

        $response = $this->getJson(route('media-library.media.edit', $media->id));

        $response->assertOk();
        $response->assertJsonPath('id', $media->id);
    }

    public function test_guest_cannot_list_media(): void
    {
        $response = $this->getJson(route('media-library.media.index'));

        $response->assertStatus(401);
    }
}

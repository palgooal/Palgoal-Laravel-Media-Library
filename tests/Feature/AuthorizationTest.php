<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Models\Media;
use Palgoal\MediaLibrary\Tests\Support\OwnerOnlyMediaPolicy;
use Palgoal\MediaLibrary\Tests\TestCase;

class AuthorizationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        // Policy class is read during service-provider boot(), so it must
        // be set before the application boots.
        $app['config']->set('media-library.policy', OwnerOnlyMediaPolicy::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function mediaFor(?int $uploaderId): Media
    {
        $path = Storage::disk('public')->putFile('media', UploadedFile::fake()->image('x.png'));

        return Media::create([
            'file_name'      => basename($path),
            'file_path'      => $path,
            'file_extension' => 'png',
            'mime_type'      => 'image/png',
            'size'           => 10,
            'file_type'      => 'image',
            'disk'           => 'public',
            'uploader_id'    => $uploaderId,
        ]);
    }

    public function test_user_cannot_update_media_uploaded_by_someone_else(): void
    {
        $user = $this->actingAsUser();
        $media = $this->mediaFor($user->id + 999);

        $response = $this->putJson(route('media-library.media.update', $media->id), [
            'title' => 'Hijacked title',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'title' => null]);
    }

    public function test_user_cannot_delete_media_uploaded_by_someone_else(): void
    {
        $user = $this->actingAsUser();
        $media = $this->mediaFor($user->id + 999);

        $response = $this->deleteJson(route('media-library.media.destroy', $media->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_bulk_delete_is_all_or_nothing_when_one_item_fails_authorization(): void
    {
        $user = $this->actingAsUser();
        $own = $this->mediaFor($user->id);
        $notOwn = $this->mediaFor($user->id + 999);

        $response = $this->deleteJson(route('media-library.media.bulk-destroy'), [
            'ids' => [$own->id, $notOwn->id],
        ]);

        $response->assertStatus(403);

        // Neither item should have been deleted: every item is authorized
        // before any deletion happens (no partial bulk-delete).
        $this->assertDatabaseHas('media', ['id' => $own->id]);
        $this->assertDatabaseHas('media', ['id' => $notOwn->id]);
        Storage::disk('public')->assertExists($own->file_path);
        Storage::disk('public')->assertExists($notOwn->file_path);
    }

    public function test_owner_can_still_delete_their_own_media(): void
    {
        $user = $this->actingAsUser();
        $own = $this->mediaFor($user->id);

        $response = $this->deleteJson(route('media-library.media.destroy', $own->id));

        $response->assertOk();
        $this->assertDatabaseMissing('media', ['id' => $own->id]);
    }
}

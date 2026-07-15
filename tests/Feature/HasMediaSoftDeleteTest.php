<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Palgoal\MediaLibrary\Tests\Support\Post;
use Palgoal\MediaLibrary\Tests\Support\Product;
use Palgoal\MediaLibrary\Tests\TestCase;

class HasMediaSoftDeleteTest extends TestCase
{
    public function test_deleting_a_model_without_soft_deletes_cleans_up_its_mediables_rows(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $product->attachMedia($this->createMedia()->id, 'gallery');

        $this->assertDatabaseCount('mediables', 1);

        $product->delete();

        $this->assertDatabaseCount('mediables', 0);
    }

    public function test_soft_deleting_a_model_does_not_touch_its_mediables_rows(): void
    {
        $post = Post::create(['title' => 'Hello World']);
        $post->attachMedia($this->createMedia()->id, 'gallery');

        $post->delete(); // soft delete: sets deleted_at, row still exists

        $this->assertNotNull($post->fresh()->deleted_at);
        $this->assertDatabaseCount('mediables', 1);
        $this->assertDatabaseHas('mediables', [
            'mediable_type' => Post::class,
            'mediable_id' => $post->id,
            'collection' => 'gallery',
        ]);
    }

    public function test_restoring_a_soft_deleted_model_leaves_its_media_attachments_intact(): void
    {
        $post = Post::create(['title' => 'Hello World']);
        $media = $this->createMedia();
        $post->attachMedia($media->id, 'gallery');

        $post->delete();
        $post->restore();

        $this->assertNull($post->fresh()->deleted_at);
        $this->assertTrue($post->hasMedia('gallery'));
        $this->assertSame($media->id, $post->firstMedia('gallery')->id);
    }

    public function test_force_deleting_a_soft_deletable_model_cleans_up_its_mediables_rows(): void
    {
        $post = Post::create(['title' => 'Hello World']);
        $post->attachMedia($this->createMedia()->id, 'gallery');

        $post->delete();
        $this->assertDatabaseCount('mediables', 1, 'Soft delete must not have removed the pivot row yet.');

        $post->forceDelete();

        $this->assertDatabaseCount('mediables', 0);
    }

    public function test_force_deleting_directly_without_a_prior_soft_delete_also_cleans_up(): void
    {
        $post = Post::create(['title' => 'Hello World']);
        $post->attachMedia($this->createMedia()->id, 'gallery');

        $post->forceDelete();

        $this->assertDatabaseCount('mediables', 0);
    }

    public function test_deleting_one_model_does_not_affect_mediables_rows_of_another_instance(): void
    {
        $productA = Product::create(['name' => 'Chair']);
        $productB = Product::create(['name' => 'Table']);

        $productA->attachMedia($this->createMedia()->id, 'gallery');
        $productB->attachMedia($this->createMedia()->id, 'gallery');

        $productA->delete();

        $this->assertDatabaseCount('mediables', 1);
        $this->assertTrue($productB->hasMedia('gallery'));
    }
}

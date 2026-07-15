<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Palgoal\MediaLibrary\Tests\Support\Post;
use Palgoal\MediaLibrary\Tests\Support\Product;
use Palgoal\MediaLibrary\Tests\TestCase;

class HasMediaIsolationTest extends TestCase
{
    public function test_attachments_are_isolated_between_two_instances_of_the_same_model_type(): void
    {
        $productA = Product::create(['name' => 'Chair']);
        $productB = Product::create(['name' => 'Table']);

        $mediaA = $this->createMedia();
        $mediaB = $this->createMedia();

        $productA->attachMedia($mediaA->id, 'gallery');
        $productB->attachMedia($mediaB->id, 'gallery');

        $this->assertSame([$mediaA->id], $productA->mediaCollection('gallery')->pluck('id')->all());
        $this->assertSame([$mediaB->id], $productB->mediaCollection('gallery')->pluck('id')->all());
    }

    public function test_detaching_from_one_model_instance_does_not_affect_another_instance(): void
    {
        $productA = Product::create(['name' => 'Chair']);
        $productB = Product::create(['name' => 'Table']);
        $shared = $this->createMedia();

        $productA->attachMedia($shared->id, 'gallery');
        $productB->attachMedia($shared->id, 'gallery');

        $productA->detachMedia($shared->id, 'gallery');

        $this->assertFalse($productA->hasMedia('gallery'));
        $this->assertTrue($productB->hasMedia('gallery'));
    }

    /**
     * Product #1 and Post #1 legitimately share the same numeric primary
     * key (both tables auto-increment independently, starting at 1).
     * `mediable_type` is what must keep their attachments apart — this
     * is the whole reason mediable_type is part of both the unique index
     * and every query this trait builds.
     */
    public function test_two_different_model_types_sharing_the_same_numeric_id_do_not_collide(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $post = Post::create(['title' => 'Hello World']);

        $this->assertSame(1, $product->id);
        $this->assertSame(1, $post->id);

        $productMedia = $this->createMedia();
        $postMedia = $this->createMedia();

        $product->attachMedia($productMedia->id, 'gallery');
        $post->attachMedia($postMedia->id, 'gallery');

        $this->assertSame([$productMedia->id], $product->mediaCollection('gallery')->pluck('id')->all());
        $this->assertSame([$postMedia->id], $post->mediaCollection('gallery')->pluck('id')->all());

        // Clearing Product #1's gallery must not touch Post #1's gallery.
        $product->clearMediaCollection('gallery');

        $this->assertFalse($product->hasMedia('gallery'));
        $this->assertTrue($post->hasMedia('gallery'));
    }

    public function test_the_same_media_item_can_be_reused_across_two_different_models(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $post = Post::create(['title' => 'Hello World']);
        $shared = $this->createMedia();

        $product->attachMedia($shared->id, 'cover');
        $post->attachMedia($shared->id, 'cover');

        $this->assertSame($shared->id, $product->firstMedia('cover')->id);
        $this->assertSame($shared->id, $post->firstMedia('cover')->id);
        $this->assertDatabaseCount('mediables', 2);
    }
}

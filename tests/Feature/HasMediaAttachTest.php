<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Palgoal\MediaLibrary\Tests\Support\Product;
use Palgoal\MediaLibrary\Tests\TestCase;

class HasMediaAttachTest extends TestCase
{
    public function test_attach_media_by_integer_id(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia();

        $result = $product->attachMedia($media->id, 'gallery');

        $this->assertSame($product, $result);
        $this->assertDatabaseHas('mediables', [
            'media_id' => $media->id,
            'mediable_type' => Product::class,
            'mediable_id' => $product->id,
            'collection' => 'gallery',
        ]);
    }

    public function test_attach_media_by_model_instance(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia();

        $product->attachMedia($media, 'gallery');

        $this->assertDatabaseHas('mediables', [
            'media_id' => $media->id,
            'mediable_type' => Product::class,
            'mediable_id' => $product->id,
            'collection' => 'gallery',
        ]);
    }

    public function test_attach_media_by_mixed_array_of_ids_and_models(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $a = $this->createMedia();
        $b = $this->createMedia();
        $c = $this->createMedia();

        $product->attachMedia([$a->id, $b, (string) $c->id], 'gallery');

        $this->assertCount(3, $product->mediaCollection('gallery'));
    }

    public function test_attaching_the_same_id_to_the_same_collection_twice_does_not_error_or_duplicate(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia();

        $product->attachMedia($media->id, 'gallery');
        $product->attachMedia($media->id, 'gallery');

        $this->assertSame(1, $product->mediaCollection('gallery')->count());
        $this->assertDatabaseCount('mediables', 1);
    }

    public function test_the_same_media_can_be_attached_to_a_different_collection_on_the_same_model(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia();

        $product->attachMedia($media->id, 'logo');
        $product->attachMedia($media->id, 'gallery');

        $this->assertTrue($product->hasMedia('logo'));
        $this->assertTrue($product->hasMedia('gallery'));
        $this->assertDatabaseCount('mediables', 2);
    }

    public function test_attach_assigns_sort_order_after_the_current_highest_in_the_collection(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $first = $this->createMedia();
        $second = $this->createMedia();
        $third = $this->createMedia();

        $product->attachMedia($first->id, 'gallery');
        $product->attachMedia([$second->id, $third->id], 'gallery');

        $ordered = $product->mediaCollection('gallery')->pluck('id')->all();

        $this->assertSame([$first->id, $second->id, $third->id], $ordered);
    }

    public function test_attach_never_removes_an_existing_attachment(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $existing = $this->createMedia();
        $new = $this->createMedia();

        $product->attachMedia($existing->id, 'gallery');
        $product->attachMedia($new->id, 'gallery');

        $this->assertCount(2, $product->mediaCollection('gallery'));
    }

    public function test_attach_silently_ignores_a_media_id_that_does_not_exist(): void
    {
        $product = Product::create(['name' => 'Chair']);

        $product->attachMedia(999999, 'gallery');

        $this->assertFalse($product->hasMedia('gallery'));
        $this->assertDatabaseCount('mediables', 0);
    }

    public function test_attach_with_an_empty_array_is_a_no_op(): void
    {
        $product = Product::create(['name' => 'Chair']);

        $product->attachMedia([], 'gallery');

        $this->assertDatabaseCount('mediables', 0);
    }
}

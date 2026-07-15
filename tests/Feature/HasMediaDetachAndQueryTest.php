<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Palgoal\MediaLibrary\Tests\Support\Product;
use Palgoal\MediaLibrary\Tests\TestCase;

class HasMediaDetachAndQueryTest extends TestCase
{
    public function test_detach_from_a_single_collection_leaves_other_collections_untouched(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia();

        $product->attachMedia($media->id, 'logo');
        $product->attachMedia($media->id, 'gallery');

        $result = $product->detachMedia($media->id, 'gallery');

        $this->assertSame($product, $result);
        $this->assertFalse($product->hasMedia('gallery'));
        $this->assertTrue($product->hasMedia('logo'));
    }

    public function test_detach_without_a_collection_removes_the_media_from_every_collection(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia();

        $product->attachMedia($media->id, 'logo');
        $product->attachMedia($media->id, 'gallery');

        $product->detachMedia($media->id);

        $this->assertFalse($product->hasMedia('logo'));
        $this->assertFalse($product->hasMedia('gallery'));
        $this->assertDatabaseCount('mediables', 0);
    }

    public function test_detach_accepts_a_media_model_instance(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia();
        $product->attachMedia($media->id, 'gallery');

        $product->detachMedia($media);

        $this->assertFalse($product->hasMedia('gallery'));
    }

    public function test_clear_media_collection_removes_only_that_collections_pivot_rows(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $logo = $this->createMedia();
        $galleryItem = $this->createMedia();

        $product->attachMedia($logo->id, 'logo');
        $product->attachMedia($galleryItem->id, 'gallery');

        $result = $product->clearMediaCollection('gallery');

        $this->assertSame($product, $result);
        $this->assertFalse($product->hasMedia('gallery'));
        $this->assertTrue($product->hasMedia('logo'));

        // Only the pivot row is gone — the Media record itself must survive.
        $this->assertDatabaseHas('media', ['id' => $galleryItem->id]);
    }

    public function test_has_media_reflects_collection_state_accurately(): void
    {
        $product = Product::create(['name' => 'Chair']);

        $this->assertFalse($product->hasMedia('logo'));

        $product->attachMedia($this->createMedia()->id, 'logo');

        $this->assertTrue($product->hasMedia('logo'));
        $this->assertFalse($product->hasMedia('cover'));
    }

    public function test_first_media_returns_the_lowest_sort_order_item_or_null(): void
    {
        $product = Product::create(['name' => 'Chair']);

        $this->assertNull($product->firstMedia('cover'));

        $first = $this->createMedia();
        $second = $this->createMedia();
        $product->attachMedia([$first->id, $second->id], 'cover');

        $this->assertSame($first->id, $product->firstMedia('cover')->id);
    }

    public function test_first_media_url_returns_the_url_of_the_first_item(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia(['disk' => 'public', 'file_path' => 'media/cover.png']);
        $product->attachMedia($media->id, 'cover');

        $this->assertSame($media->url, $product->firstMediaUrl('cover'));
        $this->assertNotNull($product->firstMediaUrl('cover'));
    }

    public function test_first_media_url_returns_the_given_default_when_the_collection_is_empty(): void
    {
        $product = Product::create(['name' => 'Chair']);

        $this->assertNull($product->firstMediaUrl('cover'));
        $this->assertSame('/images/default-cover.png', $product->firstMediaUrl('cover', '/images/default-cover.png'));
    }

    public function test_first_media_url_falls_back_to_default_when_url_accessor_itself_is_null(): void
    {
        $product = Product::create(['name' => 'Chair']);
        // A disk that isn't configured makes Media::getUrlAttribute() return null.
        $media = $this->createMedia(['disk' => 'this-disk-does-not-exist', 'file_path' => 'a/b.png']);
        $product->attachMedia($media->id, 'cover');

        $this->assertSame('/fallback.png', $product->firstMediaUrl('cover', '/fallback.png'));
    }

    public function test_media_collection_returns_a_support_collection_ordered_by_sort_order(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $third = $this->createMedia();
        $first = $this->createMedia();
        $second = $this->createMedia();

        // Attach out of the intended final order, then fix order via sync.
        $product->attachMedia([$third->id, $first->id, $second->id], 'gallery');
        $product->syncMedia([$first->id, $second->id, $third->id], 'gallery');

        $result = $product->mediaCollection('gallery');

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertSame([$first->id, $second->id, $third->id], $result->pluck('id')->all());
    }

    public function test_media_collection_filters_in_memory_when_the_media_relation_is_already_loaded(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $logo = $this->createMedia();
        $galleryItem = $this->createMedia();
        $product->attachMedia($logo->id, 'logo');
        $product->attachMedia($galleryItem->id, 'gallery');

        $loaded = Product::with('media')->find($product->id);

        $this->assertTrue($loaded->relationLoaded('media'));
        $this->assertSame([$logo->id], $loaded->mediaCollection('logo')->pluck('id')->all());
        $this->assertSame([$galleryItem->id], $loaded->mediaCollection('gallery')->pluck('id')->all());
    }

    /**
     * Documents the "loaded data is authoritative" rule: a CONSTRAINED
     * eager-load that only fetched the `logo` pivot rows still marks the
     * relation as loaded, so mediaCollection('gallery') filters that
     * (incomplete) in-memory set instead of re-querying — returning empty
     * even though a `gallery` attachment actually exists in the database.
     */
    public function test_media_collection_does_not_silently_requery_a_constrained_eager_load(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $logo = $this->createMedia();
        $galleryItem = $this->createMedia();
        $product->attachMedia($logo->id, 'logo');
        $product->attachMedia($galleryItem->id, 'gallery');

        $loaded = Product::with(['media' => function ($query) {
            $query->wherePivot('collection', 'logo');
        }])->find($product->id);

        $this->assertTrue($loaded->relationLoaded('media'));
        $this->assertSame([$logo->id], $loaded->mediaCollection('logo')->pluck('id')->all());
        $this->assertCount(0, $loaded->mediaCollection('gallery'));
    }
}

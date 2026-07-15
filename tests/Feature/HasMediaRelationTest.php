<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Palgoal\MediaLibrary\Models\Media;
use Palgoal\MediaLibrary\Tests\Support\Product;
use Palgoal\MediaLibrary\Tests\TestCase;

class HasMediaRelationTest extends TestCase
{
    public function test_media_relation_is_a_morph_to_many(): void
    {
        $product = Product::create(['name' => 'Chair']);

        $this->assertInstanceOf(MorphToMany::class, $product->media());
    }

    public function test_media_relation_exposes_collection_and_sort_order_via_pivot(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $media = $this->createMedia();
        $product->attachMedia($media->id, 'gallery');

        /** @var Media $attached */
        $attached = $product->media()->first();

        $this->assertSame('gallery', $attached->pivot->collection);
        $this->assertSame(0, (int) $attached->pivot->sort_order);
        $this->assertNotNull($attached->pivot->created_at);
    }

    public function test_media_relation_includes_items_from_every_collection(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $logo = $this->createMedia();
        $galleryItem = $this->createMedia();

        $product->attachMedia($logo->id, 'logo');
        $product->attachMedia($galleryItem->id, 'gallery');

        $ids = $product->media()->pluck('media.id')->all();

        $this->assertEqualsCanonicalizing([$logo->id, $galleryItem->id], $ids);
    }

    public function test_media_relation_is_ordered_by_sort_order_by_default(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $a = $this->createMedia();
        $b = $this->createMedia();
        $c = $this->createMedia();

        $product->attachMedia([$a->id, $b->id, $c->id], 'gallery');

        $this->assertSame(
            [$a->id, $b->id, $c->id],
            $product->media()->get()->pluck('id')->all()
        );
    }
}

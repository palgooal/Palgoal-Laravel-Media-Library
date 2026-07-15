<?php

namespace Palgoal\MediaLibrary\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Palgoal\MediaLibrary\Tests\Support\Product;
use Palgoal\MediaLibrary\Tests\TestCase;

class HasMediaSyncTest extends TestCase
{
    public function test_sync_replaces_the_contents_of_a_single_collection(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $a = $this->createMedia();
        $b = $this->createMedia();
        $c = $this->createMedia();

        $product->attachMedia([$a->id, $b->id], 'gallery');

        $result = $product->syncMedia([$b->id, $c->id], 'gallery');

        $this->assertSame($product, $result);
        $ids = $product->mediaCollection('gallery')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $ids);
    }

    public function test_sync_removes_ids_no_longer_present_in_the_new_list(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $a = $this->createMedia();
        $b = $this->createMedia();

        $product->attachMedia([$a->id, $b->id], 'gallery');
        $product->syncMedia([$b->id], 'gallery');

        $this->assertFalse($product->mediaCollection('gallery')->contains('id', $a->id));
        $this->assertTrue($product->mediaCollection('gallery')->contains('id', $b->id));
    }

    public function test_sync_does_not_touch_other_collections_on_the_same_model(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $logo = $this->createMedia();
        $cover = $this->createMedia();
        $doc = $this->createMedia();
        $galleryOld = $this->createMedia();
        $galleryNew = $this->createMedia();

        $product->attachMedia($logo->id, 'logo');
        $product->attachMedia($cover->id, 'cover');
        $product->attachMedia($doc->id, 'documents');
        $product->attachMedia($galleryOld->id, 'gallery');

        $product->syncMedia([$galleryNew->id], 'gallery');

        $this->assertTrue($product->hasMedia('logo'));
        $this->assertSame($logo->id, $product->firstMedia('logo')->id);

        $this->assertTrue($product->hasMedia('cover'));
        $this->assertSame($cover->id, $product->firstMedia('cover')->id);

        $this->assertTrue($product->hasMedia('documents'));
        $this->assertSame($doc->id, $product->firstMedia('documents')->id);

        $ids = $product->mediaCollection('gallery')->pluck('id')->all();
        $this->assertSame([$galleryNew->id], $ids);
    }

    public function test_sync_with_an_empty_array_clears_the_collection(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $a = $this->createMedia();
        $b = $this->createMedia();

        $product->attachMedia([$a->id, $b->id], 'gallery');

        $product->syncMedia([], 'gallery');

        $this->assertFalse($product->hasMedia('gallery'));
        $this->assertDatabaseCount('mediables', 0);
    }

    public function test_sync_updates_sort_order_to_match_array_position(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $five = $this->createMedia();
        $eight = $this->createMedia();
        $ten = $this->createMedia();

        $product->syncMedia([$five->id, $eight->id, $ten->id], 'gallery');

        $rows = DB::table('mediables')
            ->where('collection', 'gallery')
            ->orderBy('sort_order')
            ->get(['media_id', 'sort_order'])
            ->map(fn ($row) => [$row->media_id, $row->sort_order])
            ->all();

        $this->assertSame([
            [$five->id, 0],
            [$eight->id, 1],
            [$ten->id, 2],
        ], $rows);
    }

    public function test_sync_keeps_a_shared_id_and_only_renumbers_its_sort_order(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $a = $this->createMedia();
        $b = $this->createMedia();
        $c = $this->createMedia();

        $product->attachMedia([$a->id, $b->id], 'gallery'); // a=0, b=1

        $product->syncMedia([$c->id, $a->id], 'gallery'); // new order: c=0, a=1

        $ids = $product->mediaCollection('gallery')->pluck('id')->all();
        $this->assertSame([$c->id, $a->id], $ids);
    }

    public function test_sync_is_transactional_and_ignores_non_existent_ids(): void
    {
        $product = Product::create(['name' => 'Chair']);
        $real = $this->createMedia();

        $product->syncMedia([$real->id, 999999], 'gallery');

        $ids = $product->mediaCollection('gallery')->pluck('id')->all();
        $this->assertSame([$real->id], $ids);
    }
}

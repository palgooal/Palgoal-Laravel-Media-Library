<?php

namespace Palgoal\MediaLibrary\Tests\Unit;

use Palgoal\MediaLibrary\Models\Media;
use Palgoal\MediaLibrary\Tests\TestCase;
use RuntimeException;

class MediaModelTest extends TestCase
{
    public function test_readable_size_formats_bytes_kb_mb_gb(): void
    {
        $this->assertSame('500 B', (new Media(['size' => 500]))->readable_size);
        $this->assertSame('1.00 KB', (new Media(['size' => 1024]))->readable_size);
        $this->assertSame('1.00 MB', (new Media(['size' => 1048576]))->readable_size);
        $this->assertSame('1.00 GB', (new Media(['size' => 1073741824]))->readable_size);
    }

    public function test_url_returns_null_when_file_path_is_empty(): void
    {
        $media = new Media(['disk' => 'public', 'file_path' => null]);

        $this->assertNull($media->url);
    }

    public function test_url_returns_null_instead_of_throwing_for_an_unconfigured_disk(): void
    {
        $media = new Media(['disk' => 'this-disk-does-not-exist', 'file_path' => 'a/b.png']);

        $this->assertNull($media->url);
    }

    public function test_detect_type_classifies_by_mime_and_extension(): void
    {
        $this->assertSame('image', (new Media(['mime_type' => 'image/png']))->detectType());
        $this->assertSame('video', (new Media(['mime_type' => 'video/mp4']))->detectType());
        $this->assertSame('audio', (new Media(['mime_type' => 'audio/mpeg']))->detectType());
        $this->assertSame('document', (new Media(['mime_type' => 'application/pdf', 'file_extension' => 'pdf']))->detectType());
        $this->assertSame('other', (new Media(['mime_type' => 'application/zip', 'file_extension' => 'zip']))->detectType());
    }

    public function test_uploader_throws_a_clear_exception_when_no_user_model_can_be_resolved(): void
    {
        config(['media-library.user_model' => null]);
        config(['auth.providers.users.model' => 'This\\Class\\Does\\Not\\Exist']);

        $media = new Media(['uploader_id' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no valid user model/');

        $media->uploader();
    }

    public function test_search_scope_matches_across_several_columns(): void
    {
        Media::create(['file_name' => 'a.png', 'file_path' => 'a.png', 'title' => 'Great Sunset']);
        Media::create(['file_name' => 'b.png', 'file_path' => 'b.png', 'title' => 'Mountains']);

        $results = Media::search('sunset')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Great Sunset', $results->first()->title);
    }

    public function test_of_type_scope_filters_by_file_type(): void
    {
        Media::create(['file_name' => 'a.png', 'file_path' => 'a.png', 'file_type' => 'image']);
        Media::create(['file_name' => 'b.pdf', 'file_path' => 'b.pdf', 'file_type' => 'document']);

        $this->assertCount(1, Media::ofType('document')->get());
        $this->assertCount(1, Media::images()->get());
    }
}

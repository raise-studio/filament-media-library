<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use RaiseStudio\FilamentMediaLibrary\Facades\MediaLibrary;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Models\MediaReference;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Tests\Stubs\FakeUser;
use RaiseStudio\FilamentMediaLibrary\Tests\TestCase;

class MediaReferenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Config::mediaDisk());
    }

    private function makeMedia(array $extra = []): Media
    {
        return Media::create(array_merge([
            'name' => 'm',
            'disk' => Config::mediaDisk(),
            'path' => 'media/t.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
            'hash' => uniqid('h', true),
            'created_by' => 1,
        ], $extra));
    }

    public function test_attach_writes_pivot_and_usage_count(): void
    {
        $media = $this->makeMedia();
        $host = new FakeUser(99);

        MediaLibrary::attach($media, $host, 'avatar');

        $this->assertDatabaseCount(Config::table('media_references'), 1);
        $this->assertSame(1, $media->refresh()->usageCount());
    }

    public function test_detach_clears_pivot(): void
    {
        $media = $this->makeMedia();
        $host = new FakeUser(99);

        MediaLibrary::attach($media, $host, 'avatar');
        MediaLibrary::detach($media, $host, 'avatar');

        $this->assertDatabaseCount(Config::table('media_references'), 0);
        $this->assertSame(0, $media->refresh()->usageCount());
    }

    public function test_delete_guard_blocks_when_referenced(): void
    {
        $media = $this->makeMedia();
        $host = new FakeUser(99);
        MediaLibrary::attach($media, $host, 'avatar');

        $result = $media->delete();
        $this->assertFalse($result);
        $this->assertNotNull(Media::find($media->getKey()));
    }

    public function test_delete_succeeds_when_unreferenced_and_cleans_file(): void
    {
        $media = $this->makeMedia();
        Storage::disk(Config::mediaDisk())->put($media->path, 'x');
        $this->assertTrue(Storage::disk(Config::mediaDisk())->exists($media->path));

        $media->delete();

        $this->assertNull(Media::find($media->getKey()));
        $this->assertFalse(Storage::disk(Config::mediaDisk())->exists($media->path));
    }

    public function test_sync_field_diff(): void
    {
        $host = new FakeUser(99);
        $m1 = $this->makeMedia();
        $m2 = $this->makeMedia();
        $m3 = $this->makeMedia();

        // 模拟旧状态：m1、m2 已挂载
        MediaLibrary::attach($m1, $host, 'gallery');
        MediaLibrary::attach($m2, $host, 'gallery');

        // 旧集 [m1,m2] → 新集 [m2,m3]：m1 解挂、m2 保留、m3 新挂
        MediaLibrary::syncField($host, 'gallery', [$m1->id, $m2->id], [$m2->id, $m3->id]);

        $this->assertDatabaseCount(Config::table('media_references'), 2);
        $this->assertSame(0, MediaReference::where('media_id', $m1->id)->count());
        $this->assertSame(1, MediaReference::where('media_id', $m2->id)->count());
        $this->assertSame(1, MediaReference::where('media_id', $m3->id)->count());
    }
}

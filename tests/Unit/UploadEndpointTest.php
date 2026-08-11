<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Tests\Stubs\FakeUser;
use RaiseStudio\FilamentMediaLibrary\Tests\TestCase;

/**
 * 上传端点（POST /media-library/upload）契约测试：
 *  - 未登录 → 401（auth 中间件）
 *  - 非法 MIME → 422（allowed_mimes 白名单）
 *  - 合法 MIME → 200 + 落库 media 记录
 */
class UploadEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Config::mediaDisk());
    }

    public function test_unauthenticated_upload_is_rejected(): void
    {
        $response = $this->postJson('/media-library/upload', [
            'file' => UploadedFile::fake()->create('a.png', 10, 'image/png'),
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount(Config::table('media'), 0);
    }

    public function test_authenticated_upload_rejects_disallowed_mime(): void
    {
        $this->actingAs(new FakeUser(1));

        $response = $this->postJson('/media-library/upload', [
            // 文本/脚本类不在默认白名单内
            'file' => UploadedFile::fake()->create('evil.php', 10, 'text/plain'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
        $this->assertDatabaseCount(Config::table('media'), 0);
    }

    public function test_authenticated_upload_succeeds_for_allowed_mime(): void
    {
        $this->actingAs(new FakeUser(7));

        $response = $this->postJson('/media-library/upload', [
            'file' => UploadedFile::fake()->create('photo.png', 20, 'image/png'),
            'name' => 'My Photo',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'url', 'name', 'isImage']);

        $this->assertDatabaseCount(Config::table('media'), 1);
        $media = Media::withoutGlobalScopes()->first();
        $this->assertSame(7, (int) $media->created_by);
        $this->assertTrue(Storage::disk(Config::mediaDisk())->exists($media->path));
    }
}

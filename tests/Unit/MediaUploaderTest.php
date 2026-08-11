<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Support\MediaUploader;
use RaiseStudio\FilamentMediaLibrary\Tenancy\NullTenantResolver;
use RaiseStudio\FilamentMediaLibrary\Tests\Stubs\ConfigurableTenantResolver;
use RaiseStudio\FilamentMediaLibrary\Tests\TestCase;

class MediaUploaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Config::mediaDisk());
    }

    public function test_dedup_reuses_existing_record_without_writing_file(): void
    {
        $content = 'media-bytes-identical';
        $m1 = MediaUploader::store(UploadedFile::fake()->createWithContent('a.jpg', $content), 1);
        $this->assertNotNull($m1);

        $m2 = MediaUploader::store(UploadedFile::fake()->createWithContent('a.jpg', $content), 1);

        // 同用户同内容 → 复用既有记录（同 id）
        $this->assertSame($m1->getKey(), $m2->getKey());
        // 物理文件仅落盘一份（不重复）
        $this->assertCount(1, Storage::disk(Config::mediaDisk())->allFiles());
        // 不维护任何计数（无 ref_count 列；仅 1 条 media 记录）
        $this->assertDatabaseCount(Config::table('media'), 1);
    }

    public function test_diff_user_does_not_dedup(): void
    {
        $content = 'shared-content';
        MediaUploader::store(UploadedFile::fake()->createWithContent('x.jpg', $content), 1);
        $m2 = MediaUploader::store(UploadedFile::fake()->createWithContent('x.jpg', $content), 2);

        $this->assertNotNull($m2);
        $this->assertDatabaseCount(Config::table('media'), 2);
    }

    public function test_concurrency_unique_guard_reuses_existing(): void
    {
        // 关闭预查重，模拟并发：非 null 租户下唯一索引 (tenant_id, hash, created_by) 作 CREATE 守卫，
        // 第二次写入撞唯一冲突 → 捕获后重查复用。
        config(['media-library.dedup' => false]);
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
        ConfigurableTenantResolver::$tenantId = 5;

        $content = 'concurrent-bytes';
        $m1 = MediaUploader::store(UploadedFile::fake()->createWithContent('c.jpg', $content), 1);
        $m2 = MediaUploader::store(UploadedFile::fake()->createWithContent('c.jpg', $content), 1);

        $this->assertSame($m1->getKey(), $m2->getKey());
        $this->assertSame(1, Media::withoutGlobalScopes()->count());

        ConfigurableTenantResolver::reset();
    }

    public function test_dedup_scoped_per_tenant(): void
    {
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
        ConfigurableTenantResolver::$tenantId = 9;

        $content = 'tenant-content';
        $a = MediaUploader::store(UploadedFile::fake()->createWithContent('t.jpg', $content), 1);
        $b = MediaUploader::store(UploadedFile::fake()->createWithContent('t.jpg', $content), 1);
        $this->assertSame($a->getKey(), $b->getKey());
        $this->assertSame(9, $a->tenant_id);

        // 不同租户同内容 → 不命中，新建
        ConfigurableTenantResolver::$tenantId = 7;
        $c = MediaUploader::store(UploadedFile::fake()->createWithContent('t.jpg', $content), 1);
        $this->assertNotSame($a->getKey(), $c->getKey());
        $this->assertSame(2, Media::withoutGlobalScopes()->count());

        ConfigurableTenantResolver::reset();
    }

    public function test_tenant_prefix_applied_to_storage_path(): void
    {
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
        ConfigurableTenantResolver::$tenantId = 5;

        $m = MediaUploader::store(UploadedFile::fake()->createWithContent('p.jpg', 'tenant-bytes'), 1);

        $this->assertNotNull($m);
        // 物理隔离：对象 key 前置 t-{id}/，落库 path 自动带前缀（磁盘无关）。
        $this->assertStringStartsWith('t-5/media/', $m->path);
        // url()/delete() 经 Storage 抽象透明生效：文件确实落在带前缀的路径上。
        $this->assertTrue(Storage::disk(Config::mediaDisk())->exists($m->path));

        ConfigurableTenantResolver::reset();
    }

    public function test_null_tenant_has_no_prefix_backward_compatible(): void
    {
        // 单租户（NullTenantResolver → currentTenantId null）：不加前缀，保持既有 media/ 布局。
        config(['media-library.tenant_resolver' => NullTenantResolver::class]);

        $m = MediaUploader::store(UploadedFile::fake()->createWithContent('n.jpg', 'null-tenant-bytes'), 1);

        $this->assertNotNull($m);
        $this->assertStringStartsNotWith('t-', $m->path);
        $this->assertStringStartsWith('media/', $m->path);

        ConfigurableTenantResolver::reset();
    }

    public function test_picker_directory_nested_under_media_root(): void
    {
        // 统一根：表单组件 picker 传业务目录 'avatars' → 收敛到 media/avatars/（不带重复根）。
        config(['media-library.tenant_resolver' => NullTenantResolver::class]);

        $m = MediaUploader::store(
            UploadedFile::fake()->createWithContent('a.jpg', 'avatar-bytes'),
            1,
            ['directory' => 'avatars'],
        );

        $this->assertNotNull($m);
        $this->assertStringStartsWith('media/avatars/', $m->path);
        $this->assertTrue(Storage::disk(Config::mediaDisk())->exists($m->path));

        ConfigurableTenantResolver::reset();
    }

    public function test_picker_directory_nested_under_media_root_with_tenant(): void
    {
        // 统一根 + 多租户：业务目录 'attachments' 在租户 3 下 → t-3/media/attachments/。
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
        ConfigurableTenantResolver::$tenantId = 3;

        $m = MediaUploader::store(
            UploadedFile::fake()->createWithContent('a.jpg', 'attachment-bytes'),
            1,
            ['directory' => 'attachments'],
        );

        $this->assertNotNull($m);
        $this->assertStringStartsWith('t-3/media/attachments/', $m->path);

        ConfigurableTenantResolver::reset();
    }

    public function test_explicit_media_root_directory_not_duplicated(): void
    {
        // 防御性：调用方已传完整 'media/avatars' 时不应产生 media/media/avatars 重复根。
        config(['media-library.tenant_resolver' => NullTenantResolver::class]);

        $m = MediaUploader::store(
            UploadedFile::fake()->createWithContent('a.jpg', 'dup-root-bytes'),
            1,
            ['directory' => 'media/avatars'],
        );

        $this->assertNotNull($m);
        $this->assertStringStartsWith('media/avatars/', $m->path);
        $this->assertStringStartsNotWith('media/media/', $m->path);

        ConfigurableTenantResolver::reset();
    }

    public function test_resolveDirectory_null_tenant_returns_media_root(): void
    {
        // 单租户：resolveDirectory() 返回统一根 media/（文件管理页 FileUpload 委托此方法）。
        config(['media-library.tenant_resolver' => NullTenantResolver::class]);

        $this->assertSame('media', MediaUploader::resolveDirectory());
        $this->assertSame('media', MediaUploader::resolveDirectory(null));
        $this->assertSame('media', MediaUploader::resolveDirectory('media'));

        ConfigurableTenantResolver::reset();
    }

    public function test_resolveDirectory_tenant_prefix_applied(): void
    {
        // 多租户：文件管理页与 picker 共用 → 整体前置 t-{id}/media。
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
        ConfigurableTenantResolver::$tenantId = 5;

        $this->assertSame('t-5/media', MediaUploader::resolveDirectory());
        $this->assertSame('t-5/media/avatars', MediaUploader::resolveDirectory('avatars'));

        ConfigurableTenantResolver::reset();
    }

    public function test_resolveDirectory_no_duplicate_root(): void
    {
        // 防御性：调用方已带完整 media/ 前缀时不应重复。
        config(['media-library.tenant_resolver' => NullTenantResolver::class]);

        $this->assertSame('media/avatars', MediaUploader::resolveDirectory('media/avatars'));
        $this->assertSame('media/avatars/thumb', MediaUploader::resolveDirectory('media/avatars/thumb'));

        ConfigurableTenantResolver::reset();
    }
}

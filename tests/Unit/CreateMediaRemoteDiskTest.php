<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests\Unit;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource\Pages\CreateMedia;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Tests\TestCase;

/**
 * 回归测试：CreateMedia 在远端磁盘（oss/s3）下不能依赖 path() 做本地 stat。
 *
 * 机制：注册一个“真实本地存储 + bogus root”的磁盘。Laravel 的 path() 用磁盘配置的
 * root 作 prefixer，因此返回 /__nonexistent_remote_root__/media/... 这种不存在路径；
 * 而 exists/size/readStream 走真实后端（临时目录）。这精确复现 S3/OSS 盘行为：
 * 原代码用 path() 结果调 filesize() 会 stat 失败。
 */
class CreateMediaRemoteDiskTest extends TestCase
{
    protected string $realDir;

    protected string $originalMediaDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->realDir = sys_get_temp_dir().'/ml_remote_'.uniqid();
        @mkdir($this->realDir, 0777, true);

        $this->originalMediaDisk = Config::get('media-library.media_disk', 'public');

        Storage::extend('remote', function ($app, $config) {
            $adapter = new LocalFilesystemAdapter($this->realDir);
            $fly = new Flysystem($adapter);

            return new FilesystemAdapter($fly, $adapter, $config);
        });

        Config::set('filesystems.disks.remote', [
            'driver' => 'remote',
            'root' => '/__nonexistent_remote_root__',
        ]);
        Config::set('media-library.media_disk', 'remote');
    }

    protected function tearDown(): void
    {
        // 还原 media_disk，避免污染同进程后续测试（如 MediaUploaderTest 的 setUp fake）。
        Config::set('media-library.media_disk', $this->originalMediaDisk);

        if (is_dir($this->realDir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->realDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
            }
            @rmdir($this->realDir);
        }

        parent::tearDown();
    }

    public function test_handle_record_creation_computes_metadata_on_remote_disk(): void
    {
        $file = 'media/'.uniqid().'.txt';
        $content = 'hello-world-content';
        Storage::disk('remote')->put($file, $content);

        // path() 在远端盘返回伪路径——确认我们的测试装置确实复现了“path 不可 stat”的前提。
        $this->assertFileDoesNotExist(Storage::disk('remote')->path($file));

        $page = new CreateMedia();
        $ref = new \ReflectionMethod($page, 'handleRecordCreation');
        $ref->setAccessible(true);

        $media = $ref->invoke($page, ['file' => $file, 'name' => 'x']);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertNotNull($media->hash, '远端盘下去重哈希必须算出（不能用 filesize/hash_file 本地函数）');
        $this->assertSame(strlen($content), $media->size, 'size 必须来自 Storage::size()（远端盘可用）');
        $this->assertSame($file, $media->path);

        Storage::disk('remote')->delete($file);
    }

    public function test_handle_record_creation_dedup_returns_existing_on_same_hash(): void
    {
        $file = 'media/'.uniqid().'.txt';
        Storage::disk('remote')->put($file, 'dedup-payload');

        $page = new CreateMedia();
        $ref = new \ReflectionMethod($page, 'handleRecordCreation');
        $ref->setAccessible(true);

        $first = $ref->invoke($page, ['file' => $file, 'name' => 'x']);
        $second = $ref->invoke($page, ['file' => $file, 'name' => 'x']);

        $this->assertSame($first->id, $second->id, '同 hash 命中既有记录，不重复落库');
        $this->assertSame(1, Media::count());

        Storage::disk('remote')->delete($file);
    }
}

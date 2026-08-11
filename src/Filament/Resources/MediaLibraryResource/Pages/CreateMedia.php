<?php

namespace RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaLibraryResource::class;

    /**
     * 复用 MediaUploader 的去重逻辑：同用户同 hash 命中即返回既有记录（不重复落盘）。
     * 因唯一索引 (tenant_id, hash, created_by)，必须显式查重，否则并发会抛唯一冲突。
     */
    protected function handleRecordCreation(array $data): Media
    {
        $path = $data['file'] ?? null;
        // disk 由配置决定（MEDIA_LIBRARY_DISK），不接收前端输入，避免落盘磁盘与记录磁盘不一致。
        $disk = Config::mediaDisk();
        $userId = Auth::id();
        $tenantId = App::make(Config::tenantResolver())->currentTenantId();

        $hash = null;
        $size = null;
        $mime = null;

        if ($path && Storage::disk($disk)->exists($path)) {
            $size = Storage::disk($disk)->size($path);
            $mime = Storage::disk($disk)->mimeType($path);

            // 磁盘无关去重哈希：流式 hash，避免对远端盘（oss/s3）调用本地文件系统函数。
            // path() 在 S3/OSS 盘返回伪路径，filesize()/hash_file() 会 stat 失败（见 issue）。
            $hash = null;
            $stream = Storage::disk($disk)->readStream($path);
            if ($stream !== null && $stream !== false) {
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);
                $hash = hash_final($ctx);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        // 去重：命中既有记录则直接返回（不新建、不重复落盘）。
        if ($hash !== null) {
            $existing = Media::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_by', $userId)
                ->where('hash', $hash)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Media::create([
            'tenant_id' => $tenantId,
            'folder_id' => $data['folder_id'] ?? null,
            'name' => $data['name'] ?? ($path ? basename($path) : null),
            'original_name' => $path ? basename($path) : null,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'size' => $size,
            'hash' => $hash,
            'created_by' => $userId,
        ]);
    }
}

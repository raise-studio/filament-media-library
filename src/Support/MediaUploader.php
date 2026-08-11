<?php

namespace RaiseStudio\FilamentMediaLibrary\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use RaiseStudio\FilamentMediaLibrary\Models\Media;

/**
 * 上传 + 内容去重统一入口（层 1）。
 *
 * - 写前算 sha256 → 在去重范围内（tenant_id + created_by，per-user）查重。
 * - 命中即返回既有 Media，物理文件不落盘、不维护任何计数。
 * - miss 才落盘 + 建记录；并发同文件由唯一索引守卫，捕获冲突后重查复用。
 */
class MediaUploader
{
    /**
     * 所有上传的统一根目录。文件管理页与表单组件（picker）均落到此根下，
     * 业务子目录（avatars / attachments 等）作为其子路径，保证两条上传链路共用同一根。
     */
    public const MEDIA_ROOT = 'media';

    /**
     * @param  array{disk?:string,directory?:string,name?:string,folder_id?:int|null}  $opts
     */
    public static function store(UploadedFile $file, ?int $userId, array $opts = []): ?Media
    {
        $disk = $opts['disk'] ?? Config::mediaDisk();
        $tenantId = App::make(Config::tenantResolver())->currentTenantId();
        $directory = self::resolveDirectory($opts['directory'] ?? null);

        $hash = null;

        try {
            $hash = hash_file('sha256', $file->getRealPath());
        } catch (\Throwable) {
            $hash = null;
        }

        // 去重：显式带 tenant_id / created_by 绕过全局 scope（保证 per-user 维度）。
        if (Config::dedup() && $hash !== null) {
            $existing = Media::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_by', $userId)
                ->where('hash', $hash)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $path = null;
        try {
            // 单一写入点：显式指定 public 可见性，保证任意云端盘（S3/OSS/COS 兼容网关）
            // 落盘对象默认 public-read，admin 直链预览无需签名。本地盘忽略该选项（0644）。
            $path = Storage::disk($disk)->putFile($directory, $file, ['visibility' => 'public']);
        } catch (\Throwable) {
            return null;
        }

        if ($path === null || $path === false) {
            return null;
        }

        // 兜底：部分云端盘（如阿里云 OSS 的 S3 兼容网关）对「流式 PutObject」的
        // x-amz-acl 支持不稳定，putFile 的 visibility 选项可能未生效；显式 setVisibility
        // 走 PutObjectAcl（已实测在 OSS 上可靠），确保对象 public-read。本地盘为 chmod，无害。
        // rescue 包裹：个别不支持可见性的适配器失败时不影响落盘结果。
        rescue(fn () => Storage::disk($disk)->setVisibility($path, 'public'), report: false);

        $name = $opts['name'] ?? $file->getClientOriginalName() ?? basename($path);

        try {
            return Media::create([
                'tenant_id' => $tenantId,
                'folder_id' => $opts['folder_id'] ?? null,
                'name' => $name,
                'original_name' => $file->getClientOriginalName(),
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'hash' => $hash,
                'created_by' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // 并发同文件：重查并复用既有记录，清理刚写入的孤儿文件。
            if ($path !== null) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (\Throwable) {
                }
            }

            return Media::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('created_by', $userId)
                ->where('hash', $hash)
                ->first();
        }
    }

    /**
     * 计算最终存储目录（统一 media/ 根 + 多租户前缀）。
     *
     * 文件管理页的 FileUpload 字段与表单组件 picker 的 MediaUploader::store 共用本方法，
     * 保证两条上传链路产出的物理目录完全一致（同根 + 同租户隔离），避免：
     *  - “文件管理在 media/、picker 在 avatars/” 这类根不一致；
     *  - “picker 带 t-{id}/ 前缀、文件管理却平铺在 media/” 的隔离缺口。
     *
     * @param  string|null  $business  业务子目录（如 avatars / attachments）；null / 空 / 'media' 视为仅根。
     */
    public static function resolveDirectory(?string $business = null): string
    {
        $tenantId = App::make(Config::tenantResolver())->currentTenantId();

        // 统一 media/ 根：归一化业务目录，去除调用方可能带入的 media/ 前缀，避免 media/media/x 重复根。
        $dir = trim($business ?? self::MEDIA_ROOT, '/');
        if ($dir === self::MEDIA_ROOT) {
            $dir = '';
        } elseif (str_starts_with($dir, self::MEDIA_ROOT.'/')) {
            $dir = substr($dir, strlen(self::MEDIA_ROOT) + 1);
        }
        $directory = $dir === '' ? self::MEDIA_ROOT : self::MEDIA_ROOT.'/'.$dir;

        // 多租户物理隔离（磁盘无关）：tenant_id 非 null 时整体前置 t-{id}/，本地盘与任意云端盘通用。
        if ($tenantId !== null) {
            $directory = 't-'.$tenantId.'/'.$directory;
        }

        return $directory;
    }
}

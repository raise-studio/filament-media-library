<?php

namespace RaiseStudio\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Tenancy\ResolvesTenant;

/**
 * 媒体主模型（去重不计数；引用跟踪走 media_references；删除守卫被引用不放行）。
 */
class Media extends Model
{
    use ResolvesTenant;

    protected $fillable = [
        'tenant_id', 'folder_id', 'name', 'original_name',
        'disk', 'path', 'mime_type', 'size', 'hash', 'created_by',
    ];

    public function getTable(): string
    {
        return Config::table('media');
    }

    protected static function booted(): void
    {
        // 删除守卫：被引用则静默中止删除（双保险，配合管理台禁用删除按钮）。
        static::deleting(function (Media $media): ?bool {
            if ($media->references()->exists()) {
                return false;
            }

            return null;
        });

        // 无引用才真删：清理物理文件（try/catch 防文件已失）。
        static::deleted(function (Media $media): void {
            try {
                if (filled($media->disk) && filled($media->path)) {
                    Storage::disk($media->disk)->delete($media->path);
                }
            } catch (\Throwable) {
                // 文件可能已不存在，忽略。
            }
        });
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * 上传人（created_by 指向宿主用户模型，配置驱动）。
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Config::userModel(), 'created_by');
    }

    public function references(): HasMany
    {
        return $this->hasMany(MediaReference::class, 'media_id');
    }

    public function usageCount(): int
    {
        return $this->references()->count();
    }

    public function url(): string
    {
        if (! filled($this->disk) || ! filled($this->path)) {
            return '';
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function extension(): string
    {
        return pathinfo((string) $this->path, PATHINFO_EXTENSION);
    }
}

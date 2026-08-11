<?php

namespace RaiseStudio\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Tenancy\ResolvesTenant;

/**
 * 文件夹树（自引用 parent_id；buildParentTreeOptions 防环）。
 */
class MediaFolder extends Model
{
    use ResolvesTenant;

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'slug', 'disk', 'is_public', 'created_by',
    ];

    public function getTable(): string
    {
        return Config::table('media_folders');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * 树形父级选项，排除自身及后代防环（分层缩进）。
     */
    public static function buildParentTreeOptions(?int $excludeId = null): array
    {
        $options = [];

        $roots = static::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        foreach ($roots as $root) {
            static::appendTreeOption($root, $options, 0, $excludeId);
        }

        return $options;
    }

    protected static function appendTreeOption(self $node, array &$options, int $depth, ?int $excludeId): void
    {
        if ($excludeId !== null && $node->getKey() === $excludeId) {
            return; // 跳过自身（后代随之自然排除）
        }

        $indent = $depth === 0 ? '' : str_repeat('│  ', $depth - 1).'├─ ';
        $options[$node->getKey()] = $indent.$node->name;

        foreach ($node->children()->orderBy('name')->get() as $child) {
            static::appendTreeOption($child, $options, $depth + 1, $excludeId);
        }
    }
}

<?php

namespace RaiseStudio\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Tenancy\ResolvesTenant;

/**
 * 标签（与 Media 经 media_tag_media 透视表关联，非 morph）。
 */
class MediaTag extends Model
{
    use ResolvesTenant;

    protected $fillable = [
        'tenant_id', 'name', 'slug',
    ];

    public function getTable(): string
    {
        return Config::table('media_tags');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(
            Media::class,
            Config::table('media_tag_media'),
            'media_tag_id',
            'media_id'
        );
    }
}

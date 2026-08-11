<?php

namespace RaiseStudio\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

/**
 * 引用透视表行（media_id, referable_type, referable_id, field）——引用跟踪唯一真相源。
 */
class MediaReference extends Model
{
    protected $fillable = [
        'media_id', 'referable_type', 'referable_id', 'field',
    ];

    public function getTable(): string
    {
        return Config::table('media_references');
    }
}

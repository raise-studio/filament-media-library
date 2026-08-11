<?php

namespace RaiseStudio\FilamentMediaLibrary\Concerns;

use Illuminate\Database\Eloquent\Model;
use RaiseStudio\FilamentMediaLibrary\Contracts\MediaLibrary as MediaLibraryContract;
use RaiseStudio\FilamentMediaLibrary\Models\MediaReference;

/**
 * 宿主模型引用自注册（M5 接线，方案 B / dev-plan §M5-1）。
 *
 * 用法：宿主 Model `use HasMediaReferences;` 并声明
 *   protected array $mediaReferenceFields = ['avatar_media_id', 'gallery_media_ids'];
 * - 单值字段存 int；多值（multiple Picker）字段存 array<int>。
 *
 * 行为：
 * - saved：对每个声明字段取「新值 vs 原值」经 MediaLibraryManager::syncField 写 media_references（单一写入点）。
 * - deleted：清理该宿主的全部引用（referable_type + referable_id）。
 *
 * 表单组件（Resource）零改动；任何页面用到媒体字段即自动登记/清理，杜绝「每个表单各自写钩子」的负担与漂移（§8 #9）。
 *
 * 注意：Laravel 以静态方式调用 `boot{Trait}`（`$class::$method()`），闭包内不可用 `$this`，
 * 故 normalizeIds 为 static，事件回调一律用 `$host` 参数 / `static::` 调用。
 */
trait HasMediaReferences
{
    public static function bootHasMediaReferences(): void
    {
        static::saved(function (Model $host) {
            $fields = property_exists($host, 'mediaReferenceFields') ? $host->mediaReferenceFields : [];

            if ($fields === []) {
                return;
            }

            $lib = app(MediaLibraryContract::class);

            foreach ($fields as $field) {
                $old = self::normalizeIds($host->getOriginal($field));
                $new = self::normalizeIds($host->{$field});

                if ($old === $new) {
                    continue;
                }

                $lib->syncField($host, $field, $old, $new);
            }
        });

        static::deleted(function (Model $host) {
            MediaReference::where('referable_type', $host::class)
                ->where('referable_id', $host->getKey())
                ->delete();
        });
    }

    /**
     * 把字段值归一化为 int 数组。支持单值(int/string/null)、多值(array)、多值 JSON 字符串。
     *
     * @param  mixed  $value
     * @return array<int>
     */
    protected static function normalizeIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value) && str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        $ids = [];
        foreach ($value as $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $ids[] = (int) $v;
        }

        return $ids;
    }
}

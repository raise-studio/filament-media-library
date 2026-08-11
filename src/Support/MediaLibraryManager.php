<?php

namespace RaiseStudio\FilamentMediaLibrary\Support;

use Illuminate\Database\Eloquent\Model;
use RaiseStudio\FilamentMediaLibrary\Contracts\MediaLibrary as MediaLibraryContract;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Models\MediaReference;

/**
 * 业务引用跟踪单一写入点（层 2）：attach / detach / syncField 写 media_references。
 * 杜绝宿主各自增减导致的双真相源 / 引用漂移。
 */
class MediaLibraryManager implements MediaLibraryContract
{
    public function url($id): ?string
    {
        $media = $this->find($id);

        return $media?->url();
    }

    public function find($id): ?Media
    {
        return Media::find($id);
    }

    public function store($file, $userId): ?Media
    {
        return MediaUploader::store($file, $userId, []);
    }

    /**
     * @param  Media|int|null  $media
     */
    public function attach($media, Model $host, string $field): void
    {
        $mediaId = $media instanceof Media ? $media->getKey() : $media;

        if ($mediaId === null) {
            return;
        }

        MediaReference::firstOrCreate([
            'media_id' => $mediaId,
            'referable_type' => $host::class,
            'referable_id' => $host->getKey(),
            'field' => $field,
        ]);
    }

    /**
     * @param  Media|int|null  $media
     */
    public function detach($media, Model $host, string $field): void
    {
        $mediaId = $media instanceof Media ? $media->getKey() : $media;

        if ($mediaId === null) {
            return;
        }

        MediaReference::where('media_id', $mediaId)
            ->where('referable_type', $host::class)
            ->where('referable_id', $host->getKey())
            ->where('field', $field)
            ->delete();
    }

    /**
     * 字段级 diff：旧有新无 → detach；新有旧无 → attach。供 EDIT 保存钩子统一调用。
     *
     * @param  array<int>  $oldIds
     * @param  array<int>  $newIds
     */
    public function syncField(Model $host, string $field, array $oldIds, array $newIds): void
    {
        $old = array_map('intval', $oldIds);
        $new = array_map('intval', $newIds);

        foreach (array_diff($old, $new) as $id) {
            $this->detach($id, $host, $field);
        }

        foreach (array_diff($new, $old) as $id) {
            $this->attach($id, $host, $field);
        }
    }
}

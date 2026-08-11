<?php

namespace RaiseStudio\FilamentMediaLibrary\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use RaiseStudio\FilamentMediaLibrary\Models\Media;

interface MediaLibrary
{
    public function url($id): ?string;

    public function find($id): ?Media;

    /**
     * @param  UploadedFile  $file
     */
    public function store($file, $userId): ?Media;

    /**
     * 登记业务引用（写 media_references）。
     */
    public function attach($media, Model $host, string $field): void;

    /**
     * 解除业务引用。
     */
    public function detach($media, Model $host, string $field): void;

    /**
     * 字段级 diff：旧有新无 → detach，新有旧无 → attach（供 EDIT 流程调用）。
     */
    public function syncField(Model $host, string $field, array $oldIds, array $newIds): void;
}

<?php

namespace RaiseStudio\FilamentMediaLibrary\Facades;

use Illuminate\Support\Facades\Facade;
use RaiseStudio\FilamentMediaLibrary\Contracts\MediaLibrary as MediaLibraryContract;

/**
 * Facade → MediaLibraryManager（Facade 与契约接口分离）。
 *
 * @method static string|null url($id)
 * @method static \RaiseStudio\FilamentMediaLibrary\Models\Media|null find($id)
 * @method static \RaiseStudio\FilamentMediaLibrary\Models\Media|null store(\Illuminate\Http\UploadedFile $file, $userId)
 * @method static void attach($media, \Illuminate\Database\Eloquent\Model $host, string $field)
 * @method static void detach($media, \Illuminate\Database\Eloquent\Model $host, string $field)
 * @method static void syncField(\Illuminate\Database\Eloquent\Model $host, string $field, array $oldIds, array $newIds)
 */
class MediaLibrary extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MediaLibraryContract::class;
    }
}

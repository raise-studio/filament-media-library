<?php

namespace RaiseStudio\FilamentMediaLibrary\Filament\Forms\Components\Concerns;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

/**
 * 媒体选择器弹窗共用逻辑：可选媒体查询 + 默认图片/文件过滤 + 前端 JS 数组压平。
 * MediaPicker 共用；按 isImagePicker() 决定默认网格过滤。
 */
trait MediaPickerModal
{
    /**
     * 弹窗内可选媒体：最新 60 条。
     * - 非超管仅见自己创建的（per-user 可见性）。
     * - 未设置 allowedTypes 时按字段类型（图片/文件）默认过滤；
     *   设置了 allowedTypes 则按 MIME 前缀匹配过滤。
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    public function getPickerMedia()
    {
        $query = Media::query()->latest()->limit($this->getPickerLimit());

        if (! App::make(Config::tenantResolver())->isSuperAdmin()) {
            $query->where('created_by', Auth::id());
        }

        $allowed = $this->getAllowedTypes();
        if (! empty($allowed)) {
            // 硬约束：只显示允许的 MIME（如 image/* 或 pdf/doc/xlsx/zip）
            $query->where(function ($q) use ($allowed): void {
                foreach ($allowed as $type) {
                    $q->orWhere('mime_type', 'like', str_replace('*', '%', $type));
                }
            });
        } elseif ($this->defaultFilterMode === 'image') {
            // 单类型：图片选择器，服务端直查图片
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($this->defaultFilterMode === 'file') {
            // 单类型：文件选择器，服务端直查非图片
            $query->where('mime_type', 'not like', 'image/%');
        } elseif ($this->mergeTypes) {
            // 合并模式：加载全部，由弹窗 tab 客户端过滤（filterMode）
        }

        return $query->get();
    }

    /**
     * 把 Media 集合压成弹窗前端用的 JS 数组（id/url/name/isImage/ext/size）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPickerMediaForJs(): array
    {
        return $this->getPickerMedia()->map(function (Media $m): array {
            return [
                'id' => $m->getKey(),
                'url' => $m->url(),
                'name' => $m->name,
                'isImage' => $m->isImage(),
                'ext' => $m->extension(),
                'size' => $m->size,
            ];
        })->values()->all();
    }
}

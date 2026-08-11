<?php

namespace RaiseStudio\FilamentMediaLibrary\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Route;
use RaiseStudio\FilamentMediaLibrary\Filament\Forms\Components\Concerns\MediaPickerModal;

/**
 * 图片型媒体选择器：点击触发弹窗，弹窗内缩略图网格选已有 + 上传（走 MediaUploader 去重）。
 * 字段只显示「当前选择预览 + 选择按钮」，点按钮才弹出媒体网格；单选选完即关。
 * 仅持久化 media_id（单值）或 id 数组（multiple）；回写通过 Alpine $entangle 推到 Livewire。
 */
class MediaPicker extends Field
{
    use MediaPickerModal;

    protected string $view = 'media-library::picker';

    protected bool $isMultiple = false;

    protected ?string $directory = null;

    protected int $imagePreviewHeight = 100;

    /** @var array<int,string> */
    protected array $allowedTypes = [];

    protected bool $isImageType = true;

    /**
     * 合并模式：true=弹窗内显示「全部/图片/文件」tab，加载全部媒体由前端过滤；
     * false=单类型选择器，按 defaultFilterMode 服务端硬过滤、不显示 tab。
     */
    protected bool $mergeTypes = true;

    /**
     * 默认过滤：'all' | 'image' | 'file'。合并模式决定初始 tab；
     * 非合并模式决定服务端查询类型。
     */
    protected string $defaultFilterMode = 'all';

    /** 弹窗一次加载的媒体上限（分页在前端切片）。 */
    protected int $pickerLimit = 120;

    protected function setUp(): void
    {
        parent::setUp();

        // 隐藏字段默认不脱水 —— 作为"看不见的数据载体"必须显式脱水，否则保存时值蒸发。
        $this->dehydratedWhenHidden();
    }

    public function multiple(bool $multiple = true): static
    {
        $this->isMultiple = $multiple;

        return $this;
    }

    public function directory(?string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function imagePreviewHeight(int $height): static
    {
        $this->imagePreviewHeight = $height;

        return $this;
    }

    /**
     * @param  array<int,string>  $types
     */
    public function allowedTypes(array $types): static
    {
        $this->allowedTypes = $types;

        return $this;
    }

    /**
     * 设为合并模式（默认即为合并）。可链式 ->mergeTypes(false) 退化为单类型选择器。
     */
    public function mergeTypes(bool $merge = true): static
    {
        $this->mergeTypes = $merge;

        return $this;
    }

    /**
     * 设置默认过滤 tab：'all' | 'image' | 'file'。
     */
    public function defaultFilterMode(string $mode): static
    {
        $this->defaultFilterMode = $mode;

        return $this;
    }

    /**
     * 设置弹窗一次加载的媒体上限。
     */
    public function pickerLimit(int $limit): static
    {
        $this->pickerLimit = max(1, $limit);

        return $this;
    }

    public function isMultipleMode(): bool
    {
        return $this->isMultiple;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    public function getImagePreviewHeight(): int
    {
        return $this->imagePreviewHeight;
    }

    /**
     * @return array<int,string>
     */
    public function getAllowedTypes(): array
    {
        return $this->allowedTypes;
    }

    public function isMergeTypes(): bool
    {
        return $this->mergeTypes;
    }

    public function getDefaultFilterMode(): string
    {
        return $this->defaultFilterMode;
    }

    public function getPickerLimit(): int
    {
        return $this->pickerLimit;
    }

    /**
     * 是否显示「全部/图片/文件」tab：
     * 有 allowedTypes 单类型硬约束、或 defaultFilterMode 为 image/file → 不显示；
     * 仅 mergeTypes 且 defaultFilterMode 为 all（真正的混合选择器）才显示。
     */
    public function shouldShowTabs(): bool
    {
        if (! empty($this->allowedTypes)) {
            return false;
        }

        if ($this->defaultFilterMode !== 'all') {
            return false;
        }

        return $this->mergeTypes;
    }

    /**
     * 弹窗标题：混合模式用「媒体库」，单类型用「选择图片/选择文件」。
     */
    public function getPickerTitle(): string
    {
        if ($this->shouldShowTabs()) {
            return __('media-library::picker.title');
        }

        return $this->defaultFilterMode === 'image'
            ? __('media-library::picker.title_image')
            : __('media-library::picker.title_file');
    }

    /**
     * 是否为图片型选择器（true=图片缩略图网格；false=文件图标网格）。
     * 当前仅由 defaultFilterMode 决定（'image' 为图片型）。
     */
    public function isImagePicker(): bool
    {
        return $this->defaultFilterMode === 'image';
    }

    public function getUploadUrl(): string
    {
        return Route::has('media-library.upload')
            ? route('media-library.upload', [], false)
            : '/media-library/upload';
    }
}

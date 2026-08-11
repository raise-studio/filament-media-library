<?php

namespace RaiseStudio\FilamentMediaLibrary\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use Illuminate\Support\HtmlString;
use RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource\Pages\CreateMedia;
use RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource\Pages\EditMedia;
use RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource\Pages\ListMedia;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Models\MediaFolder;
use RaiseStudio\FilamentMediaLibrary\Support\MediaUploader;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentForge\ForgePlugin;

class MediaLibraryResource extends Resource
{
    protected static ?string $model = Media::class;

    // 显式 slug：归位到 /admin/system/（与 forge「系统管理」分组下的兄弟节点
    // tenants/users/menus/... 同源），避免落在根路径成为异类。slug 支持 '/' 嵌套，
    // 无需移动文件或耦合 forge 的 System\* 命名空间。Shield 权限键取自 model，不受影响。
    protected static ?string $slug = 'system/media-libraries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    public static function getNavigationLabel(): string
    {
        return __('media-library::nav.media');
    }

    public static function getModelLabel(): string
    {
        return __('media-library::resources.media.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('media-library::resources.media.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('media-library::nav.group');
    }

    /**
     * 导航注册策略（forge 单一来源原则）：
     *  - 若 filament-forge 已安装且将本资源登记为菜单模块 'Medias'
     *    （moduleResourceMap 含 'Medias' => self），导航完全交由 forge 引擎
     *    （rs_menus 驱动侧栏）推导，自身不再注册，杜绝重复 / 孤儿菜单；
     *  - 反之（第三方独立环境，未引入 forge）按配置自注册（默认 true），
     *    兼容各类第三方 Filament 面板（forge 集成时由 ForgePanelProvider 调
     *    registerNavigation(false) 仅为显式声明，真正的决策以本探测为准）。
     */
    public static function shouldRegisterNavigation(): bool
    {
        if (class_exists(ForgePlugin::class)
            && method_exists(ForgePlugin::class, 'isModuleManagedByForge')
            && ForgePlugin::isModuleManagedByForge('Medias', static::class)) {
            return false;
        }

        return (bool) config('media-library.register_navigation', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 整张表单限制在 50% 宽度（左侧留白），后期可改此值或移除。
                // 内部单列排布；Name 置于 File 之前。
                Grid::make(1)
                    ->extraAttributes(['style' => 'width: 100%;'])
                    ->schema([
                        TextInput::make('name')
                            ->label(__('media-library::fields.name'))
                            ->maxLength(255),
                        FileUpload::make('file')
                            ->label(__('media-library::fields.file'))
                            ->disk(Config::mediaDisk())
                            // 目录委托给 MediaUploader::resolveDirectory：与 picker 共用同一逻辑，
                            // 自动带统一 media/ 根 + 多租户 t-{id}/ 前缀，补齐“文件管理页不上前缀”的隔离缺口。
                            ->directory(fn () => MediaUploader::resolveDirectory())
                            ->visibility('public')
                            ->required()
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        // 目录（文件夹）选择：暂隐藏，待 R2 / P2-1 文件夹树 UI 升级再启用。
                        // 字段定义保留，后期移除 ->hidden() 即可恢复，无需重写；
                        // options 用惰性闭包，隐藏期间不触发 media_folders 查询。
                        Select::make('folder_id')
                            ->label(__('media-library::fields.folder'))
                            ->options(fn () => MediaFolder::buildParentTreeOptions())
                            ->searchable()
                            ->nullable()
                            ->hidden(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('path')
                    ->label(__('media-library::fields.preview'))
                    ->formatStateUsing(function ($state, ?Media $record) {
                        if (! $record) {
                            return '';
                        }

                        if ($record->isImage()) {
                            return '<img src="'.e($record->url()).'" style="height:40px;border-radius:6px;object-fit:cover" loading="lazy">';
                        }

                        return '<span class="inline-flex items-center justify-center h-10 w-10 rounded-md bg-gray-100 text-xs font-medium text-gray-500">'.e($record->extension()).'</span>';
                    })
                    ->html()
                    ->sortable(false)
                    ->action(
                        Action::make('preview')
                            ->modalHeading(fn (?Media $record): string => $record?->name
                                ?: ($record?->original_name ?? __('media-library::fields.preview')))
                            ->modalContent(function (?Media $record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('');
                                }

                                $url = $record->url();
                                $uploader = $record->creator?->name ?? $record->created_by;

                                // 文件信息 + 下载链接，图片/非图片共用，保证弹窗始终有详情。
                                $info = '
                                    <dl style="margin:1rem 0 0;display:grid;grid-template-columns:auto 1fr;gap:0.5rem 1rem;font-size:0.875rem">
                                        <dt style="color:#6b7280;white-space:nowrap">'.e(__('media-library::fields.name')).'</dt>
                                        <dd style="margin:0;word-break:break-all">'.e($record->name).'</dd>
                                        <dt style="color:#6b7280;white-space:nowrap">'.e(__('media-library::fields.file_name')).'</dt>
                                        <dd style="margin:0;word-break:break-all">'.e($record->original_name ?? '-').'</dd>
                                        <dt style="color:#6b7280;white-space:nowrap">'.e(__('media-library::fields.file_type')).'</dt>
                                        <dd style="margin:0">'.e($record->mime_type ?? '-').'</dd>
                                        <dt style="color:#6b7280;white-space:nowrap">'.e(__('media-library::fields.file_size')).'</dt>
                                        <dd style="margin:0">'.e(static::formatBytes($record->size)).'</dd>
                                        <dt style="color:#6b7280;white-space:nowrap">'.e(__('media-library::fields.disk')).'</dt>
                                        <dd style="margin:0">'.e($record->disk).'</dd>
                                        <dt style="color:#6b7280;white-space:nowrap">'.e(__('media-library::fields.uploader')).'</dt>
                                        <dd style="margin:0">'.e($uploader ?? '-').'</dd>
                                        <dt style="color:#6b7280;white-space:nowrap">'.e(__('media-library::fields.created_at')).'</dt>
                                        <dd style="margin:0">'.e($record->created_at?->format('Y-m-d H:i') ?? '-').'</dd>
                                    </dl>
                                    <div style="margin-top:1rem">
                                        <a href="'.e($url).'" target="_blank" rel="noopener" download
                                           style="display:inline-block;padding:0.5rem 1.25rem;border-radius:0.5rem;background:#2563eb;color:#fff;text-decoration:none;font-weight:500">
                                            '.e(__('media-library::fields.open_file')).'
                                        </a>
                                    </div>';

                                if ($record->isImage()) {
                                    return new HtmlString(
                                        '<img src="'.e($url).'" alt="'.e($record->original_name ?? '').'" '
                                        .'style="max-width:100%;max-height:60vh;height:auto;display:block;margin:0 auto;border-radius:8px">'
                                        .$info
                                    );
                                }

                                return new HtmlString(
                                    '<div style="text-align:center;padding:0.5rem 0 0">
                                        <div style="font-size:3rem;font-weight:700;color:#9ca3af;text-transform:uppercase;line-height:1">'.e($record->extension()).'</div>
                                        <p style="margin:0.75rem 0 0;color:#6b7280">'.e($record->original_name ?? '').'</p>
                                    </div>'
                                    .$info
                                );
                            })
                            ->modalWidth('2xl')
                            ->modalSubmitAction(false)
                    ),
                TextColumn::make('name')
                    ->label(__('media-library::fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('disk')
                    ->label(__('media-library::fields.disk'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('size')
                    ->label(__('media-library::fields.size'))
                    ->formatStateUsing(fn ($state) => static::formatBytes($state))
                    ->sortable(),
                TextColumn::make('mime_type')
                    ->label(__('media-library::fields.mime_type'))
                    ->badge()
                    ->limit(32)
                    ->toggleable(),
                TextColumn::make('usage_count')
                    ->label(__('media-library::fields.usage_count'))
                    ->getStateUsing(fn (Media $record): int => $record->usageCount())
                    ->visible(function (): bool {
                        $resolver = App::make(Config::tenantResolver());
                        // 单租户（无租户隔离）或中央超管可见；多租户下仅超管可见，避免泄露跨租户引用信息。
                        return $resolver->isSuperAdmin() || $resolver->currentTenantId() === null;
                    }),
                TextColumn::make('creator.name')
                    ->label(__('media-library::fields.created_by'))
                    ->toggleable()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('tenant_id')
                    ->label(__('media-library::fields.tenant_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('media-library::fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('creator'))
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('disk')
                    ->label(__('media-library::fields.disk'))
                    ->options(function (): array {
                        // 选项从实际落库磁盘派生，避免 OSS 切换后筛选失效；
                        // 无记录时回退到当前配置的媒体磁盘，保证筛选器可用。
                        $disks = Media::query()->withoutGlobalScopes()
                            ->distinct()->pluck('disk', 'disk')->toArray();

                        return $disks ?: [Config::mediaDisk() => Config::mediaDisk()];
                    }),
                \Filament\Tables\Filters\Filter::make('images')
                    ->label(__('media-library::fields.images_only'))
                    ->query(fn ($query) => $query->where('mime_type', 'like', 'image/%')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Media $record): bool => $record->references()->exists())
                    ->tooltip(fn (Media $record): ?string => $record->references()->exists()
                        ? __('media-library::fields.in_use')
                        : null),
            ])
            ->toolbarActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }
}

# filament-media-library

Decoupled, independently publishable **Filament 4 media library**: a central media store + reusable picker (`MediaPicker`) + folders + tags + multi-disk / OSS + multi-tenancy + content dedup + rich-text insertion.

> **与 spatie/laravel-medialibrary 的区别**：本包不提供「模型附媒体」的 `InteractsWithMedia` 模式，而是提供**集中式媒体库 + 弹窗选择器**。表单字段只持久化 `media_id`（或 id 数组），媒体文件统一存于中央 `media` 表，便于去重、跨模块引用追踪与统一磁盘/租户管理。二者不冲突，可按需并存。

---

## 要求

- PHP `^8.2`
- Laravel `11` 或 `12`
- Filament `^4`

---

## 安装

```bash
composer require raise-studio/filament-media-library
```

迁移会自动加载。如要自定义表前缀 / 磁盘 / 语言包，可发布资源：

```bash
php artisan vendor:publish --tag=media-library-config
php artisan vendor:publish --tag=media-library-views
php artisan vendor:publish --tag=media-library-translations
```

---

## 面板注册

在任意 Filament 面板里挂上插件（picker 的视图 / 上传路由 / 迁移依赖它，**必须注册**）：

```php
use RaiseStudio\FilamentMediaLibrary\FilamentMediaLibraryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentMediaLibraryPlugin::make());
}
```

插件会自动注册 `MediaLibraryResource`（媒体管理后台）。

---

## 在你的表单里使用 MediaPicker

`MediaPicker` 是标准 Filament `Field` 子类，**无需面板注册即可直接使用**：

```php
use RaiseStudio\FilamentMediaLibrary\Filament\Forms\Components\MediaPicker;
use Filament\Schemas\Schema;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        MediaPicker::make('avatar')
            ->multiple(false)
            ->defaultFilterMode('image'),   // 'all' | 'image' | 'file'

        MediaPicker::make('attachments')
            ->multiple()
            ->defaultFilterMode('file'),
    ]);
}
```

字段只持久化 `media_id`（单值）或 id 数组（multiple）；回写经 Alpine `$entangle` 推到 Livewire。

> forge 用户可用更薄的封装 `RaiseStudio\FilamentForge\Fields\ForgeMediaField::avatar()/image()/file()`，它在未装本包时降级为原生 `FileUpload`。

---

## 配置

`config/media-library.php` 关键项（发布后可覆盖）：

| 键 | 默认 | 说明 |
|----|------|------|
| `table_prefix` | `''` | 表前缀。**建议设为 `rs_` 一类前缀**，避免与 spatie/laravel-medialibrary 的 `media` 表撞名。 |
| `media_disk` | `public` | 写入磁盘；切换 OSS/COS/S3 只需在宿主注册对应磁盘并设 `MEDIA_LIBRARY_DISK`。 |
| `user_model` | `App\Models\User::class` | 上传人模型（去重归属 `created_by`）。 |
| `register_navigation` | `true` | 独立使用时自带导航；forge 集成设 `false`。 |
| `tenant_resolver` | `NullTenantResolver` | 多租户解析器契约实现；单租户用 `NullTenantResolver`。 |
| `use_shield` | `null` | 留空=自动探测：装了 Filament Shield 则交由 Shield，否则注册自带 Policy 自我保护。 |
| `dedup` | `true` | 按 sha256 复用，已存在则不重复落盘。 |
| `allowed_mimes` | 图片/文档/压缩包/音视频 | 上传白名单（不含可执行文件）。 |

---

## 多租户

实现 `RaiseStudio\FilamentMediaLibrary\Tenancy\ResolvesTenant` 契约，在配置里指定：

```php
'tenant_resolver' => App\Tenancy\MyTenantResolver::class,
```

解析器负责返回当前 `tenant_id` 与超管判定；媒体路径会自动加 `t-{id}/` 前缀（磁盘无关）。

---

## OSS / 对象存储

本包**不绑定**任何 S3/OSS/COS 适配器。在宿主 `config/filesystems.php` 注册对应磁盘后，设 `MEDIA_LIBRARY_DISK=oss` 即可整体切换，媒体库读盘与 URL 生成全部经 Laravel Storage 抽象透明生效。详见 `docs/oss-storage-integration.md`。

---

## 安全说明

- 上传端点 `POST /media-library/upload` 受 `auth` 中间件保护，**匿名上传被拒**（未登录返回 401）。
- 上传按 `allowed_mimes` 白名单做 `mimes` 校验，默认不含可执行文件。
- `use_shield` 留空时自动探测 Shield；未装 Shield 则注册自带 `MediaPolicy`，保证媒体模型有授权。

---

## 测试

```bash
vendor/bin/pest
```

---

## License

MIT © RaiseStudio

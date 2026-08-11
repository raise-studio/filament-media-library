# filament-media-library

> 🌐 Other languages: [中文](README.zh-CN.md)

Decoupled, independently publishable **Filament 4 media library**: a central media store + reusable picker (`MediaPicker`) + folders + tags + multi-disk / OSS + multi-tenancy + content dedup + rich-text insertion.

> **How it differs from `spatie/laravel-medialibrary`**: This package does *not* provide the `InteractsWithMedia` "attach media to a model" pattern. Instead it offers a **central media library + popup picker**. A form field only persists the `media_id` (or an array of ids); the actual files live in a central `media` table, which makes dedup, cross-module reference tracking, and unified disk/tenant management straightforward. The two packages don't conflict and can coexist.

---

## Requirements

- PHP `^8.2`
- Laravel `11` or `12`
- Filament `^4`

---

## Installation

```bash
composer require raise-studio/filament-media-library
```

Migrations are loaded automatically. To customize the table prefix / disk / language files, publish the assets:

```bash
php artisan vendor:publish --tag=media-library-config
php artisan vendor:publish --tag=media-library-views
php artisan vendor:publish --tag=media-library-translations
```

---

## Panel registration

Attach the plugin in any Filament panel (the picker's views / upload routes / migrations depend on it, so registration is **required**):

```php
use RaiseStudio\FilamentMediaLibrary\FilamentMediaLibraryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentMediaLibraryPlugin::make());
}
```

The plugin auto-registers `MediaLibraryResource` (the media management admin).

---

## Using MediaPicker in your forms

`MediaPicker` is a standard Filament `Field` subclass and **works directly without any panel registration**:

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

The field only persists `media_id` (single value) or an array of ids (multiple); the write-back is pushed to Livewire via Alpine `$entangle`.

> forge users may use the thinner wrapper `RaiseStudio\FilamentForge\Fields\ForgeMediaField::avatar()/image()/file()`, which falls back to the native `FileUpload` when this package is not installed.

---

## Configuration

Key entries in `config/media-library.php` (overridable after publishing):

| Key | Default | Description |
|----|------|------|
| `table_prefix` | `''` | Table prefix. **Recommended: set something like `rs_`** to avoid colliding with `spatie/laravel-medialibrary`'s `media` table. |
| `media_disk` | `public` | Write disk; switch to OSS/COS/S3 by registering the disk in the host and setting `MEDIA_LIBRARY_DISK`. |
| `user_model` | `App\Models\User::class` | Uploader model (dedup ownership `created_by`). |
| `register_navigation` | `true` | Ships its own navigation when used standalone; set `false` for forge integration. |
| `tenant_resolver` | `NullTenantResolver` | Multi-tenancy resolver contract implementation; use `NullTenantResolver` for single-tenant. |
| `use_shield` | `null` | Leave empty = auto-detect: if Filament Shield is installed, defer to Shield; otherwise register the built-in Policy for self-protection. |
| `dedup` | `true` | Reuse by sha256; if it already exists, don't write again. |
| `allowed_mimes` | images / docs / archives / av | Upload allowlist (no executables). |

---

## Multi-tenancy

Implement the `RaiseStudio\FilamentMediaLibrary\Tenancy\ResolvesTenant` contract and reference it in config:

```php
'tenant_resolver' => App\Tenancy\MyTenantResolver::class,
```

The resolver returns the current `tenant_id` and the super-admin check; media paths automatically get a `t-{id}/` prefix (disk-agnostic).

---

## OSS / Object storage

This package is **not bound** to any S3/OSS/COS adapter. After registering the corresponding disk in the host `config/filesystems.php`, set `MEDIA_LIBRARY_DISK=oss` to switch everything over; the library's disk reads and URL generation all go through the Laravel Storage abstraction transparently. See `docs/oss-storage-integration.md` for details.

---

## Security

- The upload endpoint `POST /media-library/upload` is protected by the `auth` middleware; **anonymous uploads are rejected** (401 if not logged in).
- Uploads are validated against the `allowed_mimes` allowlist via `mimes`; executables are excluded by default.
- When `use_shield` is left empty it auto-detects Shield; if Shield is not installed, the built-in `MediaPolicy` is registered so the media model is always authorized.

---

## Testing

```bash
vendor/bin/pest
```

---

## License

MIT © RaiseStudio

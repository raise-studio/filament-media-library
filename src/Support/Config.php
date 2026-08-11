<?php

namespace RaiseStudio\FilamentMediaLibrary\Support;

/**
 * 配置读取封装：所有表名经 Config::table() 取（统一表前缀解耦），禁止在模型/迁移里硬编码表名。
 */
final class Config
{
    /**
     * 取带前缀的表名。
     */
    public static function table(string $name): string
    {
        return (string) (config('media-library.table_prefix', '') ?: '').$name;
    }

    public static function mediaDisk(): string
    {
        return (string) (config('media-library.media_disk', 'public') ?: 'public');
    }

    public static function userModel(): string
    {
        return (string) (config('media-library.user_model', \App\Models\User::class) ?: \App\Models\User::class);
    }

    public static function tenantResolver(): string
    {
        return (string) (config('media-library.tenant_resolver', \RaiseStudio\FilamentMediaLibrary\Tenancy\NullTenantResolver::class)
            ?: \RaiseStudio\FilamentMediaLibrary\Tenancy\NullTenantResolver::class);
    }

    public static function useShield(): bool
    {
        $value = config('media-library.use_shield');

        // 留空 → 自动探测：已装 Filament Shield 则交由 Shield 接管，否则自带 Policy 自我保护。
        if ($value === null) {
            return class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class);
        }

        return (bool) $value;
    }

    /**
     * 上传白名单扩展名（数组）。供上传端点 mimes 校验。
     */
    public static function allowedMimes(): array
    {
        $raw = config('media-library.allowed_mimes', '');

        if (is_array($raw)) {
            return $raw;
        }

        return array_filter(array_map('trim', explode(',', (string) $raw)));
    }

    public static function dedup(): bool
    {
        return (bool) (config('media-library.dedup', true) ?? true);
    }
}

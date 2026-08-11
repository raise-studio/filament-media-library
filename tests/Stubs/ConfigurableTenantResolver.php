<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests\Stubs;

use RaiseStudio\FilamentMediaLibrary\Tenancy\TenantResolver;

/**
 * 测试用可配置租户解析器：通过静态属性切换 tenant_id / 超管，避免为每个场景建类。
 * 由 ResolvesTenant 经 App::make 解析，静态属性跨实例共享，单次设置即生效。
 */
class ConfigurableTenantResolver implements TenantResolver
{
    public static ?int $tenantId = null;

    public static bool $superAdmin = false;

    public function currentTenantId(): ?int
    {
        return self::$tenantId;
    }

    public function isSuperAdmin(): bool
    {
        return self::$superAdmin;
    }

    public static function reset(): void
    {
        self::$tenantId = null;
        self::$superAdmin = false;
    }
}

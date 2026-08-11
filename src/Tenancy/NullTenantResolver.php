<?php

namespace RaiseStudio\FilamentMediaLibrary\Tenancy;

/**
 * 单租户默认实现：currentTenantId 返回 null（全站共享媒体，不加 WHERE），无超管概念。
 */
class NullTenantResolver implements TenantResolver
{
    public function currentTenantId(): ?int
    {
        return null;
    }

    public function isSuperAdmin(): bool
    {
        return false;
    }
}

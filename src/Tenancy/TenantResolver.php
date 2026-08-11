<?php

namespace RaiseStudio\FilamentMediaLibrary\Tenancy;

interface TenantResolver
{
    /**
     * 当前租户 ID；单租户返回 null（全可见）。
     */
    public function currentTenantId(): ?int;

    /**
     * 是否为中央超管（看全部租户）。
     */
    public function isSuperAdmin(): bool;
}

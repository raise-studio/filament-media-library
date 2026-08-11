<?php

namespace RaiseStudio\FilamentMediaLibrary\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;

/**
 * 模型租户隔离 trait：自动为三模型（Media / MediaFolder / MediaTag）加 WHERE tenant_id=?。
 *
 * - 单租户（NullTenantResolver → currentTenantId null）：不加 WHERE（全可见）。
 * - 中央超管（isSuperAdmin true）：bypass（看全部租户）。
 * - 多租户：自动加 WHERE tenant_id=?。
 *
 * 作用域闭包在每次查询时动态解析 resolver（不缓存 resolver 实例），切换 resolver 配置即时生效。
 */
trait ResolvesTenant
{
    public static function bootResolvesTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $resolverClass = config('media-library.tenant_resolver', NullTenantResolver::class);
            $resolver = App::make($resolverClass);

            if ($resolver->isSuperAdmin()) {
                return; // 超管 bypass
            }

            $tenantId = $resolver->currentTenantId();

            if ($tenantId !== null) {
                $query->where((new static())->getTable().'.tenant_id', $tenantId);
            }
            // null → 单租户全可见，不加 WHERE
        });
    }
}

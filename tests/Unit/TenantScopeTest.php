<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests\Unit;

use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Tenancy\NullTenantResolver;
use RaiseStudio\FilamentMediaLibrary\Tests\Stubs\ConfigurableTenantResolver;
use RaiseStudio\FilamentMediaLibrary\Tests\TestCase;

class TenantScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
    }

    protected function tearDown(): void
    {
        ConfigurableTenantResolver::reset();
        parent::tearDown();
    }

    private function seedMedia(int $tenant, int $n = 1): void
    {
        for ($i = 0; $i < $n; $i++) {
            Media::withoutGlobalScopes()->create([
                'tenant_id' => $tenant,
                'name' => 'm',
                'disk' => 'public',
                'path' => 'p',
                'created_by' => 1,
                'hash' => uniqid('h', true),
            ]);
        }
    }

    public function test_multi_tenant_scope_filters_by_tenant(): void
    {
        ConfigurableTenantResolver::$tenantId = 5;
        ConfigurableTenantResolver::$superAdmin = false;
        $this->seedMedia(5);
        $this->seedMedia(7);

        $this->assertSame(1, (int) Media::count());
    }

    public function test_superadmin_bypasses_scope(): void
    {
        ConfigurableTenantResolver::$tenantId = 5;
        ConfigurableTenantResolver::$superAdmin = true;
        $this->seedMedia(5);
        $this->seedMedia(7);

        $this->assertSame(2, (int) Media::count());
    }

    public function test_null_resolver_shows_all(): void
    {
        // 切回 NullTenantResolver（默认单租户：不加 WHERE，全可见）
        config(['media-library.tenant_resolver' => NullTenantResolver::class]);
        $this->seedMedia(5);
        $this->seedMedia(7);

        $this->assertSame(2, (int) Media::count());
    }
}

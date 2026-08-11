<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests\Unit;

use Illuminate\Support\Facades\Gate;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Policies\MediaPolicy;
use RaiseStudio\FilamentMediaLibrary\Tests\Stubs\ConfigurableTenantResolver;
use RaiseStudio\FilamentMediaLibrary\Tests\Stubs\FakeUser;
use RaiseStudio\FilamentMediaLibrary\Tests\TestCase;

class PolicyTest extends TestCase
{
    private function makeMedia(int $owner): Media
    {
        return Media::create([
            'name' => 'm',
            'disk' => 'public',
            'path' => 'p',
            'created_by' => $owner,
            'hash' => uniqid('h', true),
        ]);
    }

    public function test_owner_can_act_on_own_media(): void
    {
        config(['media-library.use_shield' => false]);
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
        ConfigurableTenantResolver::$superAdmin = false;

        $owner = new FakeUser(10);
        $media = $this->makeMedia($owner->getAuthIdentifier());
        $policy = new MediaPolicy();

        $this->assertTrue($policy->view($owner, $media));
        $this->assertTrue($policy->update($owner, $media));
        $this->assertTrue($policy->delete($owner, $media));

        ConfigurableTenantResolver::reset();
    }

    public function test_non_owner_cannot_act(): void
    {
        config(['media-library.use_shield' => false]);
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
        ConfigurableTenantResolver::$superAdmin = false;

        $owner = new FakeUser(10);
        $other = new FakeUser(20);
        $media = $this->makeMedia($owner->getAuthIdentifier());
        $policy = new MediaPolicy();

        $this->assertFalse($policy->view($other, $media));
        $this->assertFalse($policy->delete($other, $media));

        ConfigurableTenantResolver::reset();
    }

    public function test_superadmin_bypasses(): void
    {
        config(['media-library.tenant_resolver' => ConfigurableTenantResolver::class]);
        ConfigurableTenantResolver::$superAdmin = true;

        $owner = new FakeUser(10);
        $other = new FakeUser(20);
        $media = $this->makeMedia($owner->getAuthIdentifier());
        $policy = new MediaPolicy();

        $this->assertTrue($policy->view($other, $media));
        $this->assertTrue($policy->delete($other, $media));

        ConfigurableTenantResolver::reset();
    }

    public function test_use_shield_true_does_not_register_own_policy(): void
    {
        // 默认 use_shield=true：provider 不注册自带 MediaPolicy（交由 Shield 接管，杜绝双 Policy 冲突）。
        // 注意 Gate::getPolicyFor 会按命名约定「推断」策略类，故查显式注册表 Gate::policies()。
        $this->assertArrayNotHasKey(Media::class, Gate::policies());
    }
}

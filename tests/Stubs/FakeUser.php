<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 测试用假用户：继承 Eloquent Model（满足 MediaLibraryManager::attach/detach/syncField 的 Model $host 契约），
 * 实现 Authenticatable，无需真实 rs_users 表即可驱动 Policy / 引用宿主断言。
 * 仅用 getKey() 与 ::class，不查询该"表"。
 */
class FakeUser extends Model implements Authenticatable
{
    protected $guarded = [];

    public function __construct(int $id = 0)
    {
        parent::__construct();
        $this->id = $id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}

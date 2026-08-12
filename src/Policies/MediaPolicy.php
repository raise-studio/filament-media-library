<?php

declare(strict_types=1);

namespace RaiseStudio\FilamentMediaLibrary\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

class MediaPolicy
{
    use HandlesAuthorization;

    /**
     * 权限解析：未启用 Filament Shield 时（独立使用 / 未安装 Shield）直接放行，
     * 因为后台面板已由 Authenticate 中间件保证登录态，无需依赖 Shield 的能力名；
     * 启用 Shield 时（use_shield=true）才委托给 Shield 的 `ViewAny:Media` 等能力。
     */
    protected function resolve(AuthUser $authUser, string $ability): bool
    {
        if (! Config::useShield()) {
            return true;
        }

        return $authUser->can($ability);
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->resolve($authUser, 'ViewAny:Media');
    }

    public function view(AuthUser $authUser, Media $media): bool
    {
        return $this->resolve($authUser, 'View:Media');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->resolve($authUser, 'Create:Media');
    }

    public function update(AuthUser $authUser, Media $media): bool
    {
        return $this->resolve($authUser, 'Update:Media');
    }

    public function delete(AuthUser $authUser, Media $media): bool
    {
        return $this->resolve($authUser, 'Delete:Media');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->resolve($authUser, 'DeleteAny:Media');
    }

}
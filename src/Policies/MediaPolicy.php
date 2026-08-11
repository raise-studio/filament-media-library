<?php

declare(strict_types=1);

namespace RaiseStudio\FilamentMediaLibrary\Policies;

use Illuminate\Contracts\Auth\Authenticatable as AuthUser;
use Illuminate\Support\Facades\App;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * 自带媒体授权策略：在 use_shield=false（未装 Filament Shield）时由 ServiceProvider 注册。
 *
 * 设计为「自包含」——不依赖 $authUser->can()（无 Shield 时无对应 ability），而是直接按
 * 归属（created_by）与超管判定。超管经租户解析器 isSuperAdmin() 推导。
 *
 * 注意：use_shield=true（已装 Shield）时本策略不被注册，权限完全交由 Shield 生成的策略，
 * 二者互不冲突。
 */
class MediaPolicy
{
    use HandlesAuthorization;

    protected function isSuperAdmin(): bool
    {
        return App::make(Config::tenantResolver())->isSuperAdmin();
    }

    protected function isOwner(AuthUser $authUser, Media $media): bool
    {
        return (int) $authUser->getAuthIdentifier() === (int) $media->created_by;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        // 媒体库为登录用户共享资源；路由已受 auth 中间件保护。
        return true;
    }

    public function create(AuthUser $authUser): bool
    {
        return true;
    }

    public function view(AuthUser $authUser, Media $media): bool
    {
        return $this->isSuperAdmin() || $this->isOwner($authUser, $media);
    }

    public function update(AuthUser $authUser, Media $media): bool
    {
        return $this->isSuperAdmin() || $this->isOwner($authUser, $media);
    }

    public function delete(AuthUser $authUser, Media $media): bool
    {
        return $this->isSuperAdmin() || $this->isOwner($authUser, $media);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin();
    }

    public function restore(AuthUser $authUser, Media $media): bool
    {
        return $this->isSuperAdmin() || $this->isOwner($authUser, $media);
    }

    public function forceDelete(AuthUser $authUser, Media $media): bool
    {
        return $this->isSuperAdmin();
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin();
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin();
    }

    public function replicate(AuthUser $authUser, Media $media): bool
    {
        return $this->isSuperAdmin() || $this->isOwner($authUser, $media);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->isSuperAdmin();
    }
}

<?php

namespace RaiseStudio\FilamentMediaLibrary;

use Filament\Contracts\Plugin;
use Filament\Panel;
use RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;
use RaiseStudio\FilamentMediaLibrary\Tenancy\NullTenantResolver;

class FilamentMediaLibraryPlugin implements Plugin
{
    protected bool $registerNavigation = true;

    protected string $tenantResolver = NullTenantResolver::class;

    protected ?bool $useShield = null;

    public static function make(): static
    {
        return new static();
    }

    public function registerNavigation(bool $value = true): static
    {
        $this->registerNavigation = $value;

        return $this;
    }

    public function tenantResolver(string $class): static
    {
        $this->tenantResolver = $class;

        return $this;
    }

    public function useShield(bool $value = true): static
    {
        $this->useShield = $value;

        return $this;
    }

    public function getId(): string
    {
        return 'filament-media-library';
    }

    public function register(Panel $panel): void
    {
        // 运行时覆盖配置（资源注册 / 租户解析由插件 fluent 配置驱动）。
        config([
            'media-library.register_navigation' => $this->registerNavigation,
            'media-library.tenant_resolver' => $this->tenantResolver,
        ]);

        // use_shield 仅在宿主显式调用 ->useShield(...) 时覆盖配置；
        // 保留 null 即尊重 config 文件「留空=自动探测（是否安装 Shield）」的语义。
        if ($this->useShield !== null) {
            config(['media-library.use_shield' => $this->useShield]);
        }

        // 资源始终注册进面板：保证其路由与 getUrl() 可用（ forge 集成时菜单树驱动导航、
        // 独立使用时资源自身 shouldRegisterNavigation() 决定是否自动出导航，二者互不绑架）。
        $panel->resources([
            ...$panel->getResources(),
            MediaLibraryResource::class,
        ]);

        // 演示资源「用户管理(Demo)」已从本插件迁出至宿主项目（App\Filament\Resources\DemoUserResource），
        // 由宿主面板按 config('media-library.enable_demo_crud') 自行注册，本插件不再内置 Demo CRUD。
    }

    public function boot(Panel $panel): void
    {
        //
    }
}

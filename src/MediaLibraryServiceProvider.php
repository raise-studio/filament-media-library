<?php

namespace RaiseStudio\FilamentMediaLibrary;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RaiseStudio\FilamentMediaLibrary\Contracts\MediaLibrary as MediaLibraryContract;
use RaiseStudio\FilamentMediaLibrary\Facades\MediaLibrary;
use RaiseStudio\FilamentMediaLibrary\Models\Media;
use RaiseStudio\FilamentMediaLibrary\Policies\MediaPolicy;
use RaiseStudio\FilamentMediaLibrary\Support\Config;
use RaiseStudio\FilamentMediaLibrary\Support\MediaLibraryManager;

class MediaLibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/media-library.php',
            'media-library'
        );

        // 服务契约单一写入点：Facade → Manager。
        $this->app->singleton(MediaLibraryContract::class, fn () => new MediaLibraryManager());
        $this->app->alias(MediaLibraryContract::class, 'media-library.manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'media-library');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'media-library');

        // 可发布资源：允许用户覆盖默认配置 / 视图 / 语言包。
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/media-library.php' => config_path('media-library.php'),
            ], 'media-library-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/media-library'),
            ], 'media-library-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/media-library'),
            ], 'media-library-translations');
        }

        // use_shield=true 时由 Shield 接管权限，不注册自带 Policy（杜绝双 Policy 冲突）。
        if (! Config::useShield()) {
            Gate::policy(Media::class, MediaPolicy::class);
        }

        // Picker 上传端点（XHR）。受 web + auth 保护；csrf token 由前端携带。
        // auth 中间件强制登录态，杜绝匿名上传；JSON 请求未登录返回 401。
        Route::middleware(['web', 'auth'])
            ->prefix('media-library')
            ->group(function (): void {
                Route::post('/upload', \RaiseStudio\FilamentMediaLibrary\Http\Controllers\UploadController::class)
                    ->name('media-library.upload');
            });
    }

}

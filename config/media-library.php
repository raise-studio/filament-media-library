<?php

return [
    /*
     * 表前缀：默认空。forge 集成时设 'rs_' 复用既有数据（模型/迁移均经 Config::table() 读，不硬编码）。
     */
    'table_prefix' => env('MEDIA_LIBRARY_TABLE_PREFIX', ''),

    /*
     * 写入所用磁盘：默认 Laravel 'public' 磁盘。
     * 本包不绑定任何 S3/OSS/COS 适配器；OSS/COS/S3 等由宿主应用注册对应磁盘后，设 MEDIA_LIBRARY_DISK 切换即可（磁盘无关）。
     */
    'media_disk' => env('MEDIA_LIBRARY_DISK', 'public'),

    /*
     * 上传人/创建人模型（去重归属 created_by）。
     */
    'user_model' => env('MEDIA_LIBRARY_USER_MODEL', App\Models\User::class),

    /*
     * 自带导航开关：默认 true（独立插件自带导航）；forge 集成设 false。
     */
    'register_navigation' => true,

    /*
     * 租户解析器契约实现类。单租户用 NullTenantResolver（currentTenantId 返回 null → 全可见）。
     */
    'tenant_resolver' => RaiseStudio\FilamentMediaLibrary\Tenancy\NullTenantResolver::class,

    /*
     * 内容去重开关：按 sha256 复用、不计数。
     */
    'dedup' => true,

    /*
     * Shield 软依赖开关：
     *  - 设为 true：不注册自带 Policy，权限完全由 Filament Shield 接管（杜绝双 Policy 冲突）。
     *  - 设为 false：注册标准 Laravel Policy（无 Shield 也能用）。
     *  - 留空（默认）：自动探测 —— 若 bezhansalleh/filament-shield 已安装则视为 true，否则 false。
     *    这样独立安装（无 Shield）默认自我保护，forge 集成（已装 Shield）默认交由 Shield。
     */
    'use_shield' => env('MEDIA_LIBRARY_USE_SHIELD'),

    /*
     * 文件夹级盘（P2）：默认 false（用全局 media_disk）。
     */
    'default_folder_disk' => false,

    /*
     * 演示资源开关：默认 false（不注册 DemoUserResource）。
     * 设 true 时宿主面板（app/Providers/Filament/AdminPanelProvider）按此键注册
     * 「用户管理(Demo)」CRUD 资源（/admin/system/demo-users），用于验证 MediaPicker 真实用法。
     * 注意：此键由宿主消费，插件自身已不再内置 DemoUser（DemoUser 已迁出至宿主）。
     */
    'enable_demo_crud' => env('MEDIA_LIBRARY_ENABLE_DEMO_CRUD', false),

    /*
     * 上传允许的扩展名（白名单）。Picker 上传端点据此做 mimes 校验，默认涵盖图片/文档/压缩包/音视频，
     * 不含可执行文件（php/exe/sh 等）。可按宿主需求收紧或放宽（如 env MEDIA_LIBRARY_ALLOWED_MIMES）。
     */
    'allowed_mimes' => env('MEDIA_LIBRARY_ALLOWED_MIMES',
        'jpeg,jpg,png,gif,webp,bmp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp3,mp4,webm,mov'),

];

# Changelog

本文件记录 filament-media-library 的发布变更。格式参考 [Keep a Changelog](https://keepachangelog.com/)，版本遵循 [SemVer](https://semver.org/)。

## [1.0.0] - 2026-08-11

首个稳定发布。从 raise-forge 单体中解耦为独立可发布的媒体库插件。

### 新增
- 集中式媒体库（`media` / `media_folders` / `media_tags` / `media_references` 等表，支持表前缀解耦）。
- `MediaPicker` 表单组件：弹窗式媒体网格选择 + 上传，持久化 `media_id`（单值 / 数组），支持 `defaultFilterMode('all'|'image'|'file')` 与 `multiple()`。
- 内容去重（按 sha256 复用）、多租户隔离（路径 `t-{id}/` 前缀）、文件夹 / 标签。
- 多磁盘 / OSS（磁盘无关，经 Larament Storage 抽象）。
- `MediaLibrary` Facade：`url()` / `find()` / `store()` / `attach()` / `detach()` / `syncField()`。
- `HasMediaReferences` 引用追踪 trait。
- `vendor:publish` 支持：`media-library-config` / `media-library-views` / `media-library-translations`。

### 安全
- 上传端点 `POST /media-library/upload` 加 `auth` 中间件，拒绝匿名上传（未登录返回 401）。
- 上传按 `allowed_mimes` 白名单做校验，默认不含可执行文件。
- `use_shield` 默认改为自动探测：安装 Filament Shield 则交由 Shield，否则注册自带 `MediaPolicy` 自我保护（此前无 Shield 时媒体模型无授权）。

### 修复
- `MediaPolicy` 改为自包含授权（归属 `created_by` + 超管），不再委托 `$user->can()`（无 Shield 时无对应 ability，会导致全部媒体不可访问）；方法类型提示由具体 `User` 改为 `Authenticatable`，兼容任意认证模型。
- 修正菜单补齐迁移：目录节点的 `module` / `shape` 补唯一非空值，兼容 `rs_menus` 的 NOT NULL 约束（此前在 sqlite 测试库插入失败）。

### 变更
- 依赖 `illuminate/contracts` 由 `^12` 放宽至 `^11.0 || ^12.0`（兼容 Laravel 11/12）。
- 删除 `FilePicker`（仅保留 `MediaPicker`）。
- 演示资源 DemoUser 已迁出至宿主项目，插件不再内置；`enable_demo_crud` 配置键保留，由宿主 `AdminPanelProvider` 消费以按需注册 DemoUserResource。

[1.0.0]: https://github.com/raise-studio/raise-forge/releases/tag/filament-media-library/v1.0.0

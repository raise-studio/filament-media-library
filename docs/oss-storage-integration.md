# 存储后端接入约定（宿主职责）

> 状态：**架构原则已定（2026-08-09）** — `filament-media-library` 不绑定任何 S3 / OSS / COS 适配器，物理磁盘由宿主应用注册。
> 包名：`RaiseStudio\FilamentMediaLibrary`
> 作者：小尹工 / AI 协作
> 日期：2026-08-09

---

## 1. 核心原则

**媒体库只认 `Storage::disk($media_disk)`，不认任何存储厂商。**

- 所有物理读写经 Laravel `Storage` 抽象：
  - `MediaUploader::store()` → `Storage::disk($disk)->putFile(...)`
  - `Media::url()` → `Storage::disk($disk)->url(...)`
  - `Media` 模型 `deleted` 事件 → `Storage::disk($disk)->delete(...)`
- 包内**不**包含任何厂商适配器、厂商 SDK 依赖，也**不**在包内 `Storage::extend(...)`。
- 接入 OSS / 腾讯 COS / 七牛 Kodo / S3 / 本地，全部是**宿主应用职责**：宿主注册磁盘 + 设 `MEDIA_LIBRARY_DISK`。

> 历史说明：早期曾留一份 iidestiny 社区包草稿（`registerOssDisk()` + `oss` 配置块），经 2026-08-09 决议判定为越界代码，已从包中移除（见 `docs/media-library-b1-oss-plan.md`）。本文件即其替代约定。

---

## 2. 包实际用到的存储操作（宿主适配器实现参考）

| 场景 | 调用点 | Flysystem 方法 |
|---|---|---|
| 上传 | `MediaUploader::store()` → `putFile()` | `write` / `writeStream` |
| 删媒体 / 清孤儿 | `Media` 模型 `deleted`、`MediaUploader` 并发清理 | `delete` |
| URL 预览 | `Media::url()`（picker 缩略图、上传响应） | `publicUrl` |
| 存在性 | 去重 / 复用 | `fileExists` |
| 文件夹删除 | 删目录 | `deleteDirectory`（按前缀批量） |

**删除守卫天然生效**：`Media::deleted` 调 `Storage::disk($disk)->delete($path)`；且 `deleting` 在 `references()->exists()` 时返回 `false` 中止。适配器只要正确实现 `delete()` / `deleteDirectory()` 即可，不判断引用关系。

---

## 3. 宿主接入指引

### 3.1 注册磁盘

宿主 `config/filesystems.php` 增加磁盘，驱动任选：

- **阿里 OSS**：community adapter（`league/flysystem-aliyun-oss` 或 `larva/laravel-flysystem-oss`）或官方 SDK 自写适配器。
- **腾讯 COS / 七牛 Kodo**：各自 community Flysystem 包（`larva/laravel-flysystem-cos` / `larva/laravel-flysystem-kodo` 等）。
- **三云通用（S3 兼容）**：`league/flysystem-aws-s3-v3` 指各厂 S3 网关（`use_path_style_endpoint => true`）。一个适配器吃三家，代价是拿不到厂商独有能力（七牛图片处理 / 阿里 IMG / 原生私有 URL 签名细节）。

### 3.2 指向媒体库

宿主设 `MEDIA_LIBRARY_DISK=oss`（或 `media-library.media_disk=oss`）。媒体库零代码改动即切换。

### 3.3 多租户（可选，磁盘无关）

若需按租户物理隔离，建议在 `MediaUploader` 对目录前置 `t-{id}/`（见 B1 计划 §4），对本地 / OSS 通用；也可在宿主自写适配器内做前缀装饰器。**包默认不强制**。

---

## 4. 风险与注意（供宿主适配器作者）

- **OSS / COS 无真实目录**：`deleteDirectory` 必须按前缀列对象批量删，避免漏删 / 误删跨前缀对象。多租户激活时前缀须含 `t-{id}/`。
- **URL 拼接**：`domain` 优先；`endpoint` 兜底确认不含 `https://` 前缀，避免双协议头。
- **私有桶**：`Media::url()` 现状直出 `url()`，私有桶需宿主在适配器层提供 `temporaryUrl` 或在 `Media::url()` 加分支（属宿主定制，不在包内）。
- **SDK 版本**：若宿主自写 OSS 适配器，锁定 `aliyuncs/oss-sdk-php ^2.6`（V1 稳定线），勿引 `alibabacloud/oss-v2`（命名空间 / API 不同）。

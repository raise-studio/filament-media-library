<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::table('media'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('folder_id')->nullable()->index(); // MVP 首装即带，无需 alter
            $table->string('name');
            $table->string('original_name')->nullable();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('hash')->nullable()->index(); // sha256 去重；允许 null（历史未算）
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // 去重唯一索引（并发 CREATE 守卫）：(tenant_id, hash, created_by)。hash 允许 null。
            $table->unique(
                ['tenant_id', 'hash', 'created_by'],
                'media_tenant_hash_user_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::table('media'));
    }
};

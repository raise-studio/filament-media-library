<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

return new class extends Migration
{
    /**
     * P3 集成 forge 时补齐 folder_id（forge 既有 rs_media 无此列）。
     * MVP 首装 media 表已含 folder_id，本迁移 hasColumn 守卫跳过，零副作用。
     */
    public function up(): void
    {
        $table = Config::table('media');

        if (! Schema::hasColumn($table, 'folder_id')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->unsignedBigInteger('folder_id')->nullable()->index()->after('tenant_id');
            });
        }
    }

    public function down(): void
    {
        $table = Config::table('media');

        if (Schema::hasColumn($table, 'folder_id')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('folder_id');
            });
        }
    }
};

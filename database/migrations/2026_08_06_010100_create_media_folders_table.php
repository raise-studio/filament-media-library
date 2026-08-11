<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::table('media_folders'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('disk')->nullable(); // P2：文件夹级默认盘
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::table('media_folders'));
    }
};

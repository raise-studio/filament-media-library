<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

return new class extends Migration
{
    public function up(): void
    {
        // 引用跟踪透视表（MVP 即引入，非 P3）。组合唯一约束防重复 attach。
        Schema::create(Config::table('media_references'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('media_id');
            $table->string('referable_type');
            $table->unsignedBigInteger('referable_id');
            $table->string('field');
            $table->timestamps();

            $table->unique(
                ['media_id', 'referable_type', 'referable_id', 'field'],
                'media_ref_unique'
            );
            $table->foreign('media_id')
                ->references('id')
                ->on(Config::table('media'))
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::table('media_references'));
    }
};

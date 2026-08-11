<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RaiseStudio\FilamentMediaLibrary\Support\Config;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::table('media_tag_media'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('media_id');
            $table->unsignedBigInteger('media_tag_id');
            $table->timestamps();

            $table->unique(['media_id', 'media_tag_id']);
            $table->foreign('media_id')
                ->references('id')
                ->on(Config::table('media'))
                ->cascadeOnDelete();
            $table->foreign('media_tag_id')
                ->references('id')
                ->on(Config::table('media_tags'))
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::table('media_tag_media'));
    }
};

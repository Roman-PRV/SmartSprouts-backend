<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('find_the_wrong_levels', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('title_audio_url');
            $table->string('image_url');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `find_the_wrong_levels` ALTER COLUMN `title` SET DEFAULT (JSON_OBJECT())');
            DB::statement('ALTER TABLE `find_the_wrong_levels` ALTER COLUMN `title_audio_url` SET DEFAULT (JSON_OBJECT())');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('find_the_wrong_levels');
    }
};

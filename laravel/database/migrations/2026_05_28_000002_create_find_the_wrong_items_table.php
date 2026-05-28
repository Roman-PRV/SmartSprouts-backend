<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('find_the_wrong_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('find_the_wrong_levels')->cascadeOnDelete();
            $table->json('polygon');
            $table->json('name');
            $table->json('name_audio_url');
            $table->json('explanation');
            $table->json('explanation_audio_url');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            foreach (['name', 'name_audio_url', 'explanation', 'explanation_audio_url'] as $column) {
                DB::statement("ALTER TABLE `find_the_wrong_items` ALTER COLUMN `{$column}` SET DEFAULT (JSON_OBJECT())");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('find_the_wrong_items');
    }
};

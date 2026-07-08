<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin level service persists the row first and stores the cover image
     * second (the image path needs the level's id). That transient insert has a
     * null image_url, so the column must allow null — matching find_the_wrong.
     * The required-image rule still lives at the request boundary.
     */
    public function up(): void
    {
        Schema::table('true_false_image_levels', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('true_false_image_levels', function (Blueprint $table) {
            $table->string('image_url')->nullable(false)->change();
        });
    }
};

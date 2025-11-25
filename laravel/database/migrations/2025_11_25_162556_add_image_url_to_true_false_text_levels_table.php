<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('true_false_text_levels', function (Blueprint $table) {
            $table->string('image_url')->after('title')->default('/icons/game2.png');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('true_false_text_levels', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};

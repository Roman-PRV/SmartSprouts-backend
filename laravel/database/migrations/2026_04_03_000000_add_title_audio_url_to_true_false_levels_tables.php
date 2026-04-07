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
        // Note: MySQL/MariaDB does not support transactional DDL — any Schema::table
        // call causes an implicit commit and cannot be rolled back automatically.
        Schema::table('true_false_image_levels', function (Blueprint $table) {
            $table->json('title_audio_url')->nullable()->after('title');
        });

        Schema::table('true_false_text_levels', function (Blueprint $table) {
            $table->json('title_audio_url')->nullable()->after('title');
        });

        DB::table('true_false_image_levels')->whereNull('title_audio_url')->update(['title_audio_url' => '{}']);
        DB::table('true_false_text_levels')->whereNull('title_audio_url')->update(['title_audio_url' => '{}']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('true_false_image_levels', function (Blueprint $table) {
            $table->dropColumn('title_audio_url');
        });

        Schema::table('true_false_text_levels', function (Blueprint $table) {
            $table->dropColumn('title_audio_url');
        });
    }
};

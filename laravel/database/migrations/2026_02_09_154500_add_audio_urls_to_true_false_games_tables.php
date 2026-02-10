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
            $table->json('text_audio_url')->nullable()->after('text');
        });

        Schema::table('true_false_text_statements', function (Blueprint $table) {
            $table->json('statement_audio_url')->nullable()->after('statement');
            $table->json('explanation_audio_url')->nullable()->after('explanation');
        });

        Schema::table('true_false_image_statements', function (Blueprint $table) {
            $table->json('statement_audio_url')->nullable()->after('statement');
            $table->json('explanation_audio_url')->nullable()->after('explanation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('true_false_text_levels', function (Blueprint $table) {
            $table->dropColumn('text_audio_url');
        });

        Schema::table('true_false_text_statements', function (Blueprint $table) {
            $table->dropColumn(['statement_audio_url', 'explanation_audio_url']);
        });

        Schema::table('true_false_image_statements', function (Blueprint $table) {
            $table->dropColumn(['statement_audio_url', 'explanation_audio_url']);
        });
    }
};

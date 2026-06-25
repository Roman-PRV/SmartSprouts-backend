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
        Schema::table('game_results', function (Blueprint $table) {
            // Progress lookups always filter by (user_id, game_id, level_id);
            // the existing (game_id, level_id) index does not lead with user_id.
            $table->index(['user_id', 'game_id', 'level_id'], 'game_results_user_game_level_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_results', function (Blueprint $table) {
            $table->dropIndex('game_results_user_game_level_index');
        });
    }
};

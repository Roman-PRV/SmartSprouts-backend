<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per level a user touched on a given day. See the model for what
     * the rows are counted for and who they are written for.
     *
     * Operational data with no retention duty — cascade on delete, and pruned
     * on a schedule.
     */
    public function up(): void
    {
        Schema::create('level_daily_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            // Not a foreign key, mirroring game_results: each game keeps its own
            // levels table and arithmetic levels are generated, so there is no
            // single table to point at.
            $table->unsignedInteger('level_id');
            $table->dateTime('opened_at');
            $table->dateTime('completed_at')->nullable();

            // Makes replay free as a property of the schema rather than a branch
            // in the code, and arbitrates the two-device race: the loser of an
            // insert learns it lost from the collision.
            //
            // Its leading (user_id, usage_date) columns also serve both daily
            // counters, so a separate index over that pair would be a second
            // tree paid for on the hottest insert in the system and read by
            // nothing.
            $table->unique(['user_id', 'usage_date', 'game_id', 'level_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('level_daily_usage');
    }
};

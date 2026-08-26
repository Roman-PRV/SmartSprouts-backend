<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The current state of an account's commercial relationship — one row per
     * account that has ever subscribed. See the model for the rules that govern
     * what may be written here.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tier', 20);
            // Two columns because a scheduled downgrade means the tier in force
            // and the tier charged next are different, and one field cannot
            // hold both.
            $table->string('pending_tier', 20)->nullable();
            $table->string('status', 20);
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            // Unique because the provider's identifier must resolve to one
            // account: two rows sharing it would send a webhook to the wrong
            // one.
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The transaction log. What the money event was — amount, currency, tier,
     * provider_reference — is written once and never rewritten; the refund
     * columns are the only later write, and they append an outcome rather than
     * revise the event. That is what lets a payment be resolved through
     * provider_reference long after the subscription that produced it is gone.
     * See the model for how a purchase differs from a subscription.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            // nullOnDelete, never cascade. game_results cascades and copying that
            // word here would silently erase records Spanish law requires kept
            // for six years — no error, no failure, the rows are simply gone.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Computed by EmailHasher, the same value the consent trail
            // stores. Populated on every row, not only orphaned ones: it is the
            // only handle left once the account is gone.
            $table->string('user_email_hash', 64);
            $table->string('tier', 20);
            // The tier held immediately before this purchase, and not
            // recoverable afterwards: the upgrade overwrote subscriptions.tier,
            // and downgrades write no purchase row, so purchase history is not
            // tier history. The refund threshold is computed from it and a
            // refunded upgrade reverts to it.
            $table->string('previous_tier', 20)->nullable();
            $table->string('kind', 20);
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('tax_minor');
            $table->string('provider_reference')->unique();
            $table->dateTime('purchased_at');
            $table->dateTime('refunded_at')->nullable();
            // Null until refunded; RefundKindEnum holds the two values and why
            // the difference between them matters.
            $table->string('refund_kind', 20)->nullable();
            $table->timestamps();

            $table->index('user_email_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

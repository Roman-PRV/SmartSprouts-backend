<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The refusal side of the consent gate, kept in its own table so
     * user_consents stays what it is — an append-only trail of acceptances,
     * which is the part that carries evidentiary weight.
     *
     * Deleted with the account, unlike an acceptance: a refusal proves
     * nothing anyone may later need, so there is no ground to keep it past
     * the account it belongs to.
     */
    public function up(): void
    {
        Schema::create('user_consent_declines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('document_version', 32);
            $table->timestamp('declined_at');
            $table->timestamps();

            // Mirrors the acceptance trail's key, so refusing twice writes one
            // row and the two tables answer the same question the same way.
            $table->unique(['user_id', 'type', 'document_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_consent_declines');
    }
};

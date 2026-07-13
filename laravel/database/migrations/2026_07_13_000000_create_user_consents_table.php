<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only consent audit trail. Rows must survive account deletion
     * (proof of consent for the defense of legal claims, GDPR Art. 17(3)(e)):
     * hence user_id is nullable with nullOnDelete, and email_hash is
     * reserved for the deletion flow (PRIVL-BE-03) to anonymize rows.
     *
     * One row per (user, type, version) is enforced by the unique index, so
     * a check-then-act race can never duplicate evidence rows; anonymized
     * rows do not collide (repeated NULLs are allowed in a unique index).
     */
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email_hash', 64)->nullable();
            $table->string('type', 20);
            $table->string('document_version', 32);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type', 'document_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};

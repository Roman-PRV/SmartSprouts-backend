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
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->unique()->nullable()->after('email');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * NOTE: password is intentionally left nullable after rollback.
     * Google-authenticated users have password = NULL, so restoring NOT NULL
     * would violate the constraint on any existing rows. Removing that nullability
     * requires manual intervention (e.g., deleting Google-only accounts) before
     * the constraint can be safely re-applied.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'avatar']);
        });
    }
};

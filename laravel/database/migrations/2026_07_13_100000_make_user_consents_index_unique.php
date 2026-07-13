<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One consent row per (user, type, version): enforced in the schema so a
     * check-then-act race can never duplicate evidence rows. Anonymized rows
     * (user_id NULL after account deletion) do not collide - MySQL allows
     * repeated NULLs in a unique index.
     */
    public function up(): void
    {
        Schema::table('user_consents', function (Blueprint $table) {
            // Unique first, drop second: user_id keeps an index for its FK
            // at every point in between.
            $table->unique(['user_id', 'type', 'document_version']);
            $table->dropIndex(['user_id', 'type', 'document_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_consents', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'document_version']);
            $table->dropUnique(['user_id', 'type', 'document_version']);
        });
    }
};

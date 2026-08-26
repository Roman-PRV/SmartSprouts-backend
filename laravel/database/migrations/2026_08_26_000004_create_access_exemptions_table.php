<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unlimited access granted without payment — staff and testers. See the
     * model for why this is a table rather than a flag on `users`.
     */
    public function up(): void
    {
        Schema::create('access_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reason', 20);
            $table->string('note', 500)->nullable();
            // Detached rather than cascaded when the granting admin's account
            // goes: the exemption belongs to its holder, and cascading here
            // would revoke someone's access as a side effect of an unrelated
            // deletion.
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('granted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_exemptions');
    }
};

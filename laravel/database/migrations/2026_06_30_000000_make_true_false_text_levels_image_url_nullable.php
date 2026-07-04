<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The text game centres on the statement, not an image — the cover image is
     * optional. The column was created NOT NULL; relax it so text levels can be
     * authored without one (the image game keeps its required image).
     */
    public function up(): void
    {
        Schema::table('true_false_text_levels', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('true_false_text_levels', function (Blueprint $table) {
            $table->string('image_url')->nullable(false)->change();
        });
    }
};

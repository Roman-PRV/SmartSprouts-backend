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
        Schema::create('true_false_text_statements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('level_id')
                ->constrained('true_false_text_levels')
                ->cascadeOnDelete();

            $table->text('statement');
            $table->boolean('is_true');
            $table->text('explanation')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('true_false_text_statements');
    }
};

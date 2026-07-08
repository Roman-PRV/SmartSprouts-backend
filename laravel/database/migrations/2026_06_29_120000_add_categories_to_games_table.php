<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categories each existing game belongs to, keyed by game `key`. A game may
     * appear in several categories (true_false_image is both logic and reading).
     *
     * @var array<string, list<string>>
     */
    private array $backfill = [
        'addition_table' => ['math'],
        'multiplication_table' => ['math'],
        'true_false_text' => ['reading'],
        'find_the_wrong' => ['logic'],
        'true_false_image' => ['logic', 'reading'],
    ];

    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->json('categories')->nullable()->after('is_active');
        });

        foreach ($this->backfill as $key => $categories) {
            DB::table('games')->where('key', $key)->update([
                'categories' => json_encode($categories),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('categories');
        });
    }
};

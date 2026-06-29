<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // insert() bypasses model casts, so categories are json-encoded here.
        Game::insert([
            [
                'table_prefix' => 'find_the_wrong',
                'key' => 'find_the_wrong',
                'icon_url' => 'https://example.com/icons/find_the_wrong.png',
                'is_active' => true,
                'categories' => json_encode(['logic']),
            ],
            [
                'table_prefix' => 'true_false_image',
                'key' => 'true_false_image',
                'icon_url' => 'https://example.com/icons/true-false-image.png',
                'is_active' => true,
                'categories' => json_encode(['logic', 'reading']),
            ],
            [
                'table_prefix' => 'true_false_text',
                'key' => 'true_false_text',
                'icon_url' => 'https://example.com/icons/true-false-text.png',
                'is_active' => true,
                'categories' => json_encode(['reading']),
            ],
            [
                'table_prefix' => 'multiplication_table',
                'key' => 'multiplication_table',
                'icon_url' => 'https://example.com/icons/multiplication-table.png',
                'is_active' => true,
                'categories' => json_encode(['math']),
            ],
            [
                'table_prefix' => 'addition_table',
                'key' => 'addition_table',
                'icon_url' => 'https://example.com/icons/addition-table.png',
                'is_active' => true,
                'categories' => json_encode(['math']),
            ],
        ]);
    }
}

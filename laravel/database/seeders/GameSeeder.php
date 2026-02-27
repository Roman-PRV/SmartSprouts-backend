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
        Game::insert([
            [
                'table_prefix' => 'find_the_wrong',
                'key' => 'find_the_wrong',
                'icon_url' => 'https://example.com/icons/find_the_wrong.png',
                'is_active' => true,
            ],
            [
                'table_prefix' => 'true_false_image',
                'key' => 'true_false_image',
                'icon_url' => 'https://example.com/icons/true-false-image.png',
                'is_active' => true,
            ],
            [
                'table_prefix' => 'true_false_text',
                'key' => 'true_false_text',
                'icon_url' => 'https://example.com/icons/true-false-text.png',
                'is_active' => true,
            ],
        ]);
    }
}

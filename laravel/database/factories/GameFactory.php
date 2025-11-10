<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        $attrs = [
            'key' => $this->faker->unique()->lexify('game_????'),
            'table_prefix' => Str::slug($this->faker->unique()->words(2, true), '_'),
            // ensure icon_url is NOT NULL in tests: provide a default placeholder path
            'icon_url' => $this->faker->boolean(70) ? 'games/icons/'.$this->faker->word().'.png' : '',
            'is_active' => $this->faker->boolean(80),
        ];

        if (! Schema::hasTable('games')) {
            // якщо таблиці ще немає, повертаємо мінімальний набір
            return Arr::only($attrs, ['key', 'table_prefix', 'icon_url', 'is_active']);
        }

        $columns = Schema::getColumnListing('games');

        // лишаємо тільки ті атрибути, що реально існують у схемі
        return Arr::only($attrs, array_intersect(array_keys($attrs), $columns));
    }

    public function withPrefix(string $prefix): static
    {
        return $this->state(fn (array $attributes) => [
            'table_prefix' => $prefix,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LevelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function routeFor(Game|int|string $game): string
    {
        $id = $game instanceof Game ? $game->id : (int) $game;

        return "/api/games/{$id}/levels";
    }

    public function test_missing_game_returns_404(): void
    {
        $this->json('GET', $this->routeFor(99999))
            ->assertStatus(404);
    }

    public function test_missing_service_returns_400(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'no_such_prefix',
        ]);

        $response = $this->json('GET', $this->routeFor($game));
        $expectedMessage = "No game service configured for table prefix: {$game->table_prefix}";

        $response->assertStatus(400)
            ->assertJsonStructure(['message'])
            ->assertJsonFragment(['message' => $expectedMessage]);
    }

    public function test_index_returns_levels(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        DB::table('true_false_image_levels')->truncate();

        DB::table('true_false_image_levels')->insert([
            [
                'id' => 1,
                'title' => 'First level',
                'image_url' => 'levels/first.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'title' => 'Second level',
                'image_url' => 'levels/second.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson("/api/games/{$game->id}/levels");

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                ['id', 'title', 'image_url'],
            ]);

        $titles = array_column($response->json(), 'title');
        $this->assertEqualsCanonicalizing(['First level', 'Second level'], $titles);
    }

    public function test_show_returns_single_level_with_file_url_and_statements(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('levels/first.png', 'dummy');

        $game = Game::factory()->create([
            'table_prefix' => 'true_false_image',
        ]);

        config(['filesystems.default' => 'public']);

        DB::table('true_false_image_levels')->truncate();
        DB::table('true_false_image_statements')->truncate();

        DB::table('true_false_image_levels')->insert([
            'id' => 1,
            'title' => 'First level',
            'image_url' => 'levels/first.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('true_false_image_statements')->insert([
            [
                'id' => 10,
                'level_id' => 1,
                'statement' => 'The sky is blue',
                'is_true' => true,
                'explanation' => 'Because of Rayleigh scattering',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'level_id' => 1,
                'statement' => 'Cats can fly',
                'is_true' => false,
                'explanation' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson("/api/games/{$game->id}/levels/1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'image_url',
                'statements' => [
                    ['id', 'level_id', 'statement', 'is_true', 'explanation'],
                ],
            ])
            ->assertJson([
                'id' => 1,
                'title' => 'First level',
                'image_url' => 'http://localhost/storage/levels/first.png',
            ]);

        $response->assertJsonFragment([
            'id' => 10,
            'level_id' => 1,
            'statement' => 'The sky is blue',
            'is_true' => true,
            'explanation' => 'Because of Rayleigh scattering',
        ]);

        $response->assertJsonFragment([
            'id' => 11,
            'level_id' => 1,
            'statement' => 'Cats can fly',
            'is_true' => false,
            'explanation' => null,
        ]);

        $this->assertCount(2, $response->json('statements'));
    }
}

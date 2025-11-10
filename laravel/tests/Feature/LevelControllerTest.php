<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LevelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function routeFor(Game|int|string $game): string
    {
        $id = $game instanceof Game ? $game->id : $game;

        return "/api/games/{$id}/levels";
    }

    public function test_missing_game_returns_404(): void
    {
        $this->json('GET', $this->routeFor(99999))
            ->assertStatus(404);
    }

    public function test_missing_table_returns_404(): void
    {
        $game = Game::factory()->create([
            'table_prefix' => 'no_such_prefix',
        ]);

        $response = $this->json('GET', $this->routeFor($game));

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Levels table not found',
            ]);
    }

    public function test_empty_levels_table_returns_empty_array(): void
    {
        $prefix = 'emptytest';
        $game = Game::factory()->create([
            'table_prefix' => $prefix,
        ]);

        $table = $prefix.'_levels';

        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->bigIncrements('id');
            $tableBlueprint->string('title')->nullable();
            $tableBlueprint->string('image_url')->nullable();
            $tableBlueprint->timestamps();
        });

        $this->json('GET', $this->routeFor($game))
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_happy_path_returns_levels(): void
    {
        $prefix = 'happytest';
        $game = Game::factory()->create([
            'table_prefix' => $prefix,
        ]);

        $table = $prefix.'_levels';

        Schema::create($table, function ($tableBlueprint) {
            $tableBlueprint->bigIncrements('id');
            $tableBlueprint->string('title')->nullable();
            $tableBlueprint->string('image_url')->nullable();
            $tableBlueprint->timestamps();
        });

        DB::table($table)->insert([
            [
                'title' => 'First level',
                'image_url' => 'levels/first.png',
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'title' => 'Second level',
                'image_url' => 'levels/second.png',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
        ]);

        $response = $this->json('GET', $this->routeFor($game));

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                [
                    'id',
                    'title',
                    'image_url',
                ],
            ]);

        $json = $response->json();

        $this->assertSame('First level', $json[0]['title']);
        $this->assertSame('Second level', $json[1]['title']);

        // image_url is produced by Level accessor: either full URL to file or default icon URL.
        $this->assertIsString($json[0]['image_url']);
        $this->assertStringStartsWith('http', $json[0]['image_url']);
        $this->assertIsString($json[1]['image_url']);
        $this->assertStringStartsWith('http', $json[1]['image_url']);

        // Accept either the expected path inside URL or the default icon filename
        $this->assertTrue(
            str_contains($json[0]['image_url'], 'levels/first.png') ||
              str_contains($json[0]['image_url'], 'default-icon.png'),
            'First image_url should contain expected path or default icon'
        );

        $this->assertTrue(
            str_contains($json[1]['image_url'], 'levels/second.png') ||
              str_contains($json[1]['image_url'], 'default-icon.png'),
            'Second image_url should contain expected path or default icon'
        );
    }
}

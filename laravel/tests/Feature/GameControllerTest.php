<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_missing_game_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/games/99999')
            ->assertStatus(404);
    }

    public function test_non_numeric_game_id_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/games/abc')
            ->assertStatus(404);
    }
}

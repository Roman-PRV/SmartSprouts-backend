<?php

namespace Tests\Feature\Resources;

use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameResourceTest extends TestCase
{
    /**
     * Verifies that GameResource returns the correct structure,
     * including localized title and description, icon URL, and active status.
     */
    public function test_game_resource_returns_expected_structure(): void
    {
        Storage::fake('public');

        $filePath = 'icons/game1.png';
        $expectedUrl = url('/storage/'.$filePath);

        Storage::disk('public')->put($filePath, 'fake-content');

        Lang::shouldReceive('get')
            ->once()
            ->with('games.find_the_wrong')
            ->andReturn([
                'title' => 'Find the Wrong',
                'description' => 'Choose the wrong item',
            ]);

        $game = new Game([
            'id' => 1,
            'key' => 'find_the_wrong',
            'icon_url' => $filePath,
            'is_active' => true,
        ]);

        $resource = GameResource::make($game)->resolve();

        $this->assertEquals([
            'id' => 1,
            'title' => 'Find the Wrong',
            'key' => 'find_the_wrong',
            'description' => 'Choose the wrong item',
            'icon_url' => $expectedUrl,
            'is_active' => true,
        ], $resource);
    }
}

<?php

namespace Tests\Unit\Models;

use App\Models\Game;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameTest extends TestCase
{
    /**
     * Ensures that the icon_url accessor returns a valid public URL
     * when a file path is set and the file exists.
     */
    public function test_icon_url_returns_valid_path_when_set(): void
    {
        Storage::fake('public');

        $filePath = 'icons/game1.png';
        $expectedUrl = '/storage/icons/game1.png';

        Storage::disk('public')->put($filePath, 'fake-content');

        $game = new Game([
            'icon_url' => $filePath,
        ]);

        $this->assertStringContainsString($expectedUrl, $game->icon_url);
        $this->assertTrue(Storage::disk('public')->exists($filePath));
    }

    /**
     * Ensures that the icon_url accessor returns the default icon URL
     * when no file path is provided.
     */
    public function test_icon_url_returns_default_when_missing(): void
    {
        $defaultPath = 'icons/default-icon.png';
        $expectedUrl = '/storage/'.$defaultPath;

        $game = new Game([
            'icon_url' => null,
        ]);

        $this->assertStringContainsString($expectedUrl, $game->icon_url);
    }
}

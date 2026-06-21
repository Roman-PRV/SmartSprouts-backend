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
        Storage::fake('static', ['url' => config('app.url')]);

        $filePath = 'icons/game1.png';
        $expectedUrl = '/icons/game1.png';

        Storage::disk('static')->put($filePath, 'fake-content');

        $game = new Game([
            'icon_url' => $filePath,
        ]);

        $this->assertStringContainsString($expectedUrl, $game->icon_url);
        $this->assertTrue(Storage::disk('static')->exists($filePath));
    }

    /**
     * Ensures that the icon_url accessor returns the default icon URL
     * when no file path is provided.
     */
    public function test_icon_url_returns_default_when_missing(): void
    {
        $defaultPath = 'icons/default-icon.png';
        $expectedUrl = '/'.$defaultPath;

        $game = new Game([
            'icon_url' => null,
        ]);

        $this->assertStringContainsString($expectedUrl, $game->icon_url);
    }

    /**
     * The accessor builds the URL straight from the stored path with no
     * exists() probe: a missing file yields a constructed URL (404 at fetch
     * time), never a silent default-icon swap. The frontend handles the 404.
     */
    public function test_icon_url_is_built_without_existence_probe(): void
    {
        Storage::fake('static', ['url' => config('app.url')]);
        // deliberately do NOT put the file on the disk

        $game = new Game([
            'icon_url' => 'icons/missing.png',
        ]);

        $this->assertStringContainsString('/icons/missing.png', $game->icon_url);
        $this->assertStringNotContainsString('default-icon', $game->icon_url);
    }
}

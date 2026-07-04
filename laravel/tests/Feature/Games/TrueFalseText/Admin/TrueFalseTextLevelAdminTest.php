<?php

namespace Tests\Feature\Games\TrueFalseText\Admin;

use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TrueFalseText levels also flow through the generic dispatcher, but the
 * game-aware request requires a translatable `text` body and treats the image
 * as optional.
 */
class TrueFalseTextLevelAdminTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private string $route;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
        config(['games.upload_disk' => 'public']);

        $this->game = Game::factory()->create(['table_prefix' => 'true_false_text']);
        $this->route = "/api/admin/games/{$this->game->id}/levels";
    }

    public function test_admin_creates_text_level_without_image(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson($this->route, [
            'title' => ['uk' => 'Назва', 'en' => 'Name', 'es' => 'Nombre'],
            'text' => ['uk' => 'Текст', 'en' => 'Body', 'es' => 'Cuerpo'],
        ]);

        $response->assertStatus(201);

        $level = TrueFalseTextLevel::query()->findOrFail($response->json('id'));
        $this->assertSame('Body', $level->getTranslation('text', 'en'));
        $this->assertNull($level->getRawOriginal('image_url'));
    }

    public function test_store_requires_text_for_text_game(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson($this->route, ['title' => ['uk' => 'a', 'en' => 'b', 'es' => 'c']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }

    public function test_admin_creates_text_level_with_optional_image(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson($this->route, [
            'title' => ['uk' => 'Назва', 'en' => 'Name', 'es' => 'Nombre'],
            'text' => ['uk' => 'Текст', 'en' => 'Body', 'es' => 'Cuerpo'],
            'image' => UploadedFile::fake()->image('cover.png', 400, 300),
        ]);

        $response->assertStatus(201);

        $level = TrueFalseTextLevel::query()->findOrFail($response->json('id'));
        Storage::disk('public')->assertExists($level->storageDirectory().'/image.png');
    }

    public function test_show_returns_text_and_audio_status(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseTextLevel::factory()->create([
            'text' => ['uk' => 'Текст', 'en' => 'Body', 'es' => 'Cuerpo'],
        ]);

        $this->actingAs($admin)->getJson("{$this->route}/{$level->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'text',
                'title_audio' => ['en' => ['url', 'is_stale']],
                'text_audio' => ['en' => ['url', 'is_stale']],
                'statements',
            ])
            ->assertJsonPath('text.en', 'Body')
            ->assertJsonPath('text_audio.en.is_stale', true);
    }

    public function test_admin_updates_text_level(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseTextLevel::factory()->create([
            'title' => ['uk' => 'Стара', 'en' => 'Old', 'es' => 'Vieja'],
            'text' => ['uk' => 'Старий', 'en' => 'Old body', 'es' => 'Viejo'],
        ]);

        $this->actingAs($admin)->patchJson("{$this->route}/{$level->id}", [
            'title' => ['uk' => 'Нова', 'en' => 'New', 'es' => 'Nueva'],
            'text' => ['uk' => 'Новий', 'en' => 'New body', 'es' => 'Nuevo'],
        ])->assertStatus(200)->assertJsonPath('text.en', 'New body');

        $this->assertSame('New', $level->fresh()->getTranslation('title', 'en'));
    }
}

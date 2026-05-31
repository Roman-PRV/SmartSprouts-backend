<?php

namespace Tests\Feature\Games\FindTheWrong\Admin;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FindTheWrongLevelAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private string $route;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['games.upload_disk' => 'public']);

        $this->game = Game::factory()->create(['table_prefix' => 'find_the_wrong']);
        $this->route = "/api/admin/games/{$this->game->id}/levels";
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson($this->route)->assertStatus(401);
        $this->postJson($this->route, [])->assertStatus(401);
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson($this->route)->assertStatus(403);
        $this->actingAs($user)->postJson($this->route, [])->assertStatus(403);
    }

    public function test_admin_creates_level_with_image(): void
    {
        $admin = User::factory()->admin()->create();
        $image = UploadedFile::fake()->image('kitchen.png', 800, 600);

        $response = $this->actingAs($admin)->postJson($this->route, [
            'title' => ['uk' => 'Кухня', 'en' => 'Kitchen', 'es' => 'Cocina'],
            'image' => $image,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'title', 'image_url'])
            ->assertJsonPath('title.uk', 'Кухня')
            ->assertJsonPath('title.en', 'Kitchen');

        $level = FindTheWrongLevel::query()->findOrFail($response->json('id'));
        $expectedPath = $level->storageDirectory().'/image.png';

        Storage::disk('public')->assertExists($expectedPath);
        $this->assertSame($expectedPath, $level->getRawOriginal('image_url'));
    }

    public function test_admin_update_title_without_image_keeps_existing_image(): void
    {
        $admin = User::factory()->admin()->create();
        $level = FindTheWrongLevel::factory()->create([
            'title' => ['uk' => 'Старе', 'en' => 'Old', 'es' => 'Viejo'],
        ]);
        $existingPath = $level->storageDirectory().'/image.jpg';
        Storage::disk('public')->put($existingPath, 'fake-image-bytes');
        $level->update(['image_url' => $existingPath]);

        $response = $this->actingAs($admin)->patchJson("{$this->route}/{$level->id}", [
            'title' => ['uk' => 'Нове', 'en' => 'New', 'es' => 'Nuevo'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('title.en', 'New');

        $level->refresh();
        $this->assertSame($existingPath, $level->getRawOriginal('image_url'));
        Storage::disk('public')->assertExists($existingPath);
    }

    public function test_admin_update_with_new_image_replaces_old_file(): void
    {
        $admin = User::factory()->admin()->create();
        $level = FindTheWrongLevel::factory()->create([
            'title' => ['uk' => 'Кухня', 'en' => 'Kitchen', 'es' => 'Cocina'],
            'image_url' => null,
        ]);
        $oldPath = $level->storageDirectory().'/image.jpg';
        Storage::disk('public')->put($oldPath, 'old');
        $level->update(['image_url' => $oldPath]);

        $newImage = UploadedFile::fake()->image('bathroom.png', 800, 600);

        $response = $this->actingAs($admin)->postJson("{$this->route}/{$level->id}?_method=PATCH", [
            'title' => ['uk' => 'Кухня', 'en' => 'Kitchen', 'es' => 'Cocina'],
            'image' => $newImage,
        ]);

        $response->assertStatus(200);

        $level->refresh();
        $newPath = $level->storageDirectory().'/image.png';
        $this->assertSame($newPath, $level->getRawOriginal('image_url'));
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_destroy_cascades_items_and_cleans_storage(): void
    {
        $admin = User::factory()->admin()->create();
        $level = FindTheWrongLevel::factory()->create();
        $items = FindTheWrongItem::factory()->count(3)->create(['level_id' => $level->id]);

        $path = $level->storageDirectory().'/image.png';
        Storage::disk('public')->put($path, 'bytes');
        $level->update(['image_url' => $path]);

        $response = $this->actingAs($admin)->deleteJson("{$this->route}/{$level->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('find_the_wrong_levels', ['id' => $level->id]);
        foreach ($items as $item) {
            $this->assertDatabaseMissing('find_the_wrong_items', ['id' => $item->id]);
        }
        Storage::disk('public')->assertMissing($path);
    }

    public function test_store_validation_requires_title_and_image(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson($this->route, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'image']);

        $this->actingAs($admin)
            ->postJson($this->route, [
                'title' => ['en' => 'Only English'],
                'image' => UploadedFile::fake()->image('foo.png'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title.uk', 'title.es']);
    }

    public function test_index_returns_levels_with_items_count(): void
    {
        $admin = User::factory()->admin()->create();
        $level = FindTheWrongLevel::factory()->create();
        FindTheWrongItem::factory()->count(2)->create(['level_id' => $level->id]);

        $response = $this->actingAs($admin)->getJson($this->route);

        $response->assertStatus(200)
            ->assertJsonStructure([['id', 'title', 'image_url', 'items_count']])
            ->assertJsonPath('0.items_count', 2);
    }

    public function test_admin_endpoint_rejects_game_without_admin_service(): void
    {
        $admin = User::factory()->admin()->create();
        $otherGame = Game::factory()->create(['table_prefix' => 'definitely_not_registered']);

        $this->actingAs($admin)
            ->getJson("/api/admin/games/{$otherGame->id}/levels")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Admin operations are not available for this game.');
    }
}

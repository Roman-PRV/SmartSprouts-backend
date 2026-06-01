<?php

namespace Tests\Feature\Games\FindTheWrong\Admin;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FindTheWrongItemAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private FindTheWrongLevel $level;

    private string $storeRoute;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['games.upload_disk' => 'public']);

        $this->game = Game::factory()->create(['table_prefix' => 'find_the_wrong']);
        $this->level = FindTheWrongLevel::factory()->create();
        $this->storeRoute = "/api/admin/games/{$this->game->id}/levels/{$this->level->id}/items";
    }

    private function itemRoute(int $itemId): string
    {
        return "/api/admin/games/{$this->game->id}/items/{$itemId}";
    }

    private function validPayload(): array
    {
        return [
            'polygon' => [
                [0.1, 0.1],
                [0.4, 0.1],
                [0.4, 0.4],
                [0.1, 0.4],
            ],
            'name' => [
                'uk' => 'Тарілка',
                'en' => 'Plate',
                'es' => 'Plato',
            ],
            'explanation' => [
                'uk' => 'Це тарілка',
                'en' => 'It is a plate',
                'es' => 'Es un plato',
            ],
        ];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson($this->storeRoute, $this->validPayload())->assertStatus(401);
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson($this->storeRoute, $this->validPayload())
            ->assertStatus(403);
    }

    public function test_admin_creates_item_with_valid_polygon(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson($this->storeRoute, $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'level_id', 'polygon', 'name', 'explanation'])
            ->assertJsonPath('level_id', $this->level->id)
            ->assertJsonPath('name.uk', 'Тарілка')
            ->assertJsonPath('name.en', 'Plate')
            ->assertJsonPath('name.es', 'Plato')
            ->assertJsonPath('explanation.uk', 'Це тарілка');

        $this->assertDatabaseHas('find_the_wrong_items', [
            'id' => $response->json('id'),
            'level_id' => $this->level->id,
        ]);

        $item = FindTheWrongItem::query()->findOrFail($response->json('id'));
        $this->assertSame(
            [
                [0.1, 0.1],
                [0.4, 0.1],
                [0.4, 0.4],
                [0.1, 0.4],
            ],
            $item->polygon
        );
    }

    public function test_store_rejects_polygon_with_fewer_than_three_points(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = $this->validPayload();
        $payload['polygon'] = [[0.1, 0.1], [0.2, 0.2]];

        $this->actingAs($admin)
            ->postJson($this->storeRoute, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['polygon']);
    }

    public function test_store_rejects_coordinate_out_of_range(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = $this->validPayload();
        $payload['polygon'] = [[1.5, 0.1], [0.4, 0.1], [0.4, 0.4]];

        $this->actingAs($admin)
            ->postJson($this->storeRoute, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['polygon.0.0']);
    }

    public function test_store_requires_all_name_locales(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = $this->validPayload();
        $payload['name'] = ['en' => 'Only English'];

        $this->actingAs($admin)
            ->postJson($this->storeRoute, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name.uk', 'name.es']);
    }

    public function test_store_validation_empty_body(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson($this->storeRoute, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['polygon', 'name']);
    }

    public function test_admin_update_polygon_fully_replaces_no_merge(): void
    {
        $admin = User::factory()->admin()->create();
        $item = FindTheWrongItem::factory()->create([
            'level_id' => $this->level->id,
            'polygon' => [[0.1, 0.1], [0.2, 0.2], [0.3, 0.3], [0.4, 0.4]],
        ]);

        $newPolygon = [[0.5, 0.5], [0.6, 0.6], [0.7, 0.7]];

        $response = $this->actingAs($admin)
            ->patchJson($this->itemRoute($item->id), ['polygon' => $newPolygon]);

        $response->assertStatus(200)
            ->assertJsonPath('polygon', $newPolygon);

        $item->refresh();
        $this->assertSame($newPolygon, $item->polygon);
        $this->assertCount(3, $item->polygon);
    }

    public function test_admin_update_translates_per_locale_keeps_others(): void
    {
        $admin = User::factory()->admin()->create();
        $item = FindTheWrongItem::factory()->create([
            'level_id' => $this->level->id,
            'name' => ['uk' => 'Старе', 'en' => 'Old', 'es' => 'Viejo'],
        ]);

        $response = $this->actingAs($admin)
            ->patchJson($this->itemRoute($item->id), [
                'name' => ['uk' => 'Нове'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('name.uk', 'Нове')
            ->assertJsonPath('name.en', 'Old')
            ->assertJsonPath('name.es', 'Viejo');
    }

    public function test_admin_destroys_item_removes_db_row(): void
    {
        $admin = User::factory()->admin()->create();
        $item = FindTheWrongItem::factory()->create(['level_id' => $this->level->id]);

        $response = $this->actingAs($admin)
            ->deleteJson($this->itemRoute($item->id));

        $response->assertStatus(204);
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseMissing('find_the_wrong_items', ['id' => $item->id]);
    }

    public function test_admin_destroy_cleans_storage_directory(): void
    {
        $admin = User::factory()->admin()->create();
        $item = FindTheWrongItem::factory()->create(['level_id' => $this->level->id]);

        $audioPath = $item->storageDirectory().'/name_uk.mp3';
        Storage::disk('public')->put($audioPath, 'fake-audio-bytes');
        Storage::disk('public')->assertExists($audioPath);

        $this->actingAs($admin)
            ->deleteJson($this->itemRoute($item->id))
            ->assertStatus(204);

        Storage::disk('public')->assertMissing($audioPath);
    }

    public function test_update_rejects_polygon_with_fewer_than_three_points(): void
    {
        $admin = User::factory()->admin()->create();
        $item = FindTheWrongItem::factory()->create(['level_id' => $this->level->id]);

        $this->actingAs($admin)
            ->patchJson($this->itemRoute($item->id), [
                'polygon' => [[0.1, 0.1], [0.2, 0.2]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['polygon']);
    }

    public function test_update_rejects_coordinate_out_of_range(): void
    {
        $admin = User::factory()->admin()->create();
        $item = FindTheWrongItem::factory()->create(['level_id' => $this->level->id]);

        $this->actingAs($admin)
            ->patchJson($this->itemRoute($item->id), [
                'polygon' => [[1.5, 0.1], [0.4, 0.1], [0.4, 0.4]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['polygon.0.0']);
    }

    public function test_empty_update_body_succeeds_without_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $item = FindTheWrongItem::factory()->create([
            'level_id' => $this->level->id,
            'polygon' => [[0.1, 0.1], [0.2, 0.2], [0.3, 0.3]],
            'name' => ['uk' => 'Тарілка', 'en' => 'Plate', 'es' => 'Plato'],
        ]);
        $originalPolygon = $item->polygon;
        $originalName = $item->getTranslations('name');

        $response = $this->actingAs($admin)
            ->patchJson($this->itemRoute($item->id), []);

        $response->assertStatus(200);

        $item->refresh();
        $this->assertSame($originalPolygon, $item->polygon);
        $this->assertSame($originalName, $item->getTranslations('name'));
    }

    public function test_store_returns_404_for_nonexistent_level(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(
                "/api/admin/games/{$this->game->id}/levels/999999/items",
                $this->validPayload()
            )
            ->assertStatus(404);
    }

    public function test_destroy_returns_404_for_nonexistent_item(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson($this->itemRoute(999999))
            ->assertStatus(404);
    }

    public function test_endpoint_rejects_request_for_game_with_wrong_prefix(): void
    {
        $admin = User::factory()->admin()->create();
        $otherGame = Game::factory()->create(['table_prefix' => 'true_false_image']);

        $this->actingAs($admin)
            ->postJson(
                "/api/admin/games/{$otherGame->id}/levels/{$this->level->id}/items",
                $this->validPayload()
            )
            ->assertStatus(404);
    }

    public function test_store_returns_404_for_nonexistent_game(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/games/999999/levels/{$this->level->id}/items", $this->validPayload())
            ->assertStatus(404);
    }

    public function test_update_returns_404_for_nonexistent_item(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson($this->itemRoute(999999), ['name' => ['uk' => 'X']])
            ->assertStatus(404);
    }
}

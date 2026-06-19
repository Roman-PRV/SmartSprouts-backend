<?php

namespace Tests\Feature\Games\FindTheWrong;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FindTheWrongReadApiTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create([
            'table_prefix' => 'find_the_wrong',
        ]);

        Storage::fake('static', ['url' => config('app.url')]);
        config(['filesystems.default' => 'public']);
    }

    public function test_unauthenticated_user_cannot_access_routes(): void
    {
        $this->getJson("/api/games/{$this->game->id}/levels")->assertStatus(401);
        $this->getJson("/api/games/{$this->game->id}/levels/1")->assertStatus(401);
    }

    public function test_index_returns_levels_with_items_count(): void
    {
        $levelA = FindTheWrongLevel::factory()->create([
            'title' => ['en' => 'Kitchen', 'uk' => 'Кухня', 'es' => 'Cocina'],
            'image_url' => 'levels/kitchen.png',
        ]);
        $levelB = FindTheWrongLevel::factory()->create([
            'title' => ['en' => 'Bathroom', 'uk' => 'Ванна', 'es' => 'Baño'],
            'image_url' => 'levels/bathroom.png',
        ]);
        FindTheWrongItem::factory()->count(3)->create(['level_id' => $levelA->id]);
        FindTheWrongItem::factory()->count(2)->create(['level_id' => $levelB->id]);

        $response = $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/games/{$this->game->id}/levels");

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                ['id', 'title', 'image_url', 'items_count'],
            ]);

        $payload = collect($response->json())->keyBy('id');
        $this->assertSame(3, $payload[$levelA->id]['items_count']);
        $this->assertSame(2, $payload[$levelB->id]['items_count']);
        $this->assertSame('Kitchen', $payload[$levelA->id]['title']);
        $this->assertArrayNotHasKey('items', $payload[$levelA->id]);
    }

    public function test_show_returns_level_with_polygon_items_and_omits_explanation(): void
    {
        $level = FindTheWrongLevel::factory()->create([
            'title' => ['en' => 'Kitchen', 'uk' => 'Кухня'],
            'image_url' => 'levels/kitchen.png',
        ]);

        $polygon = [[0.1, 0.2], [0.3, 0.2], [0.3, 0.4], [0.1, 0.4]];

        $item = FindTheWrongItem::factory()->create([
            'level_id' => $level->id,
            'polygon' => $polygon,
            'name' => ['en' => 'Iron', 'uk' => 'Праска'],
            'name_audio_url' => ['en' => 'audio/iron_en.mp3'],
            'explanation' => ['en' => 'Iron belongs in the laundry room'],
            'explanation_audio_url' => ['en' => 'audio/expl_en.mp3'],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/games/{$this->game->id}/levels/{$level->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'image_url',
                'items' => [
                    ['id', 'polygon', 'name', 'name_audio_url'],
                ],
            ])
            ->assertJsonFragment([
                'id' => $level->id,
                'title' => 'Kitchen',
            ]);

        $items = $response->json('items');
        $this->assertCount(1, $items);
        $this->assertSame($item->id, $items[0]['id']);
        $this->assertSame($polygon, $items[0]['polygon']);
        $this->assertSame('Iron', $items[0]['name']);
        $this->assertSame('http://localhost/storage/audio/iron_en.mp3', $items[0]['name_audio_url']);

        $this->assertArrayNotHasKey('explanation', $items[0]);
        $this->assertArrayNotHasKey('explanation_audio_url', $items[0]);
        $this->assertArrayNotHasKey('items_count', $response->json());
    }

    public function test_show_returns_404_for_non_existent_level(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson("/api/games/{$this->game->id}/levels/99999")
            ->assertStatus(404);
    }

    public function test_show_returns_localized_fields_via_accept_language(): void
    {
        $level = FindTheWrongLevel::factory()->create([
            'title' => ['en' => 'Kitchen', 'uk' => 'Кухня', 'es' => 'Cocina'],
            'image_url' => 'levels/kitchen.png',
        ]);
        FindTheWrongItem::factory()->create([
            'level_id' => $level->id,
            'polygon' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
            'name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'],
            'name_audio_url' => ['uk' => 'audio/iron_uk.mp3'],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept-Language' => 'uk'])
            ->getJson("/api/games/{$this->game->id}/levels/{$level->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Кухня']);

        $items = $response->json('items');
        $this->assertSame('Праска', $items[0]['name']);
        $this->assertSame('http://localhost/storage/audio/iron_uk.mp3', $items[0]['name_audio_url']);
    }
}

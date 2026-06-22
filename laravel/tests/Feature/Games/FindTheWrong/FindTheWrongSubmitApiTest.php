<?php

namespace Tests\Feature\Games\FindTheWrong;

use App\Games\FindTheWrong\Models\FindTheWrongItem;
use App\Games\FindTheWrong\Models\FindTheWrongLevel;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\User;
use App\Services\Tts\TtsOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindTheWrongSubmitApiTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private FindTheWrongLevel $level;

    /** @var FindTheWrongItem[] */
    private array $items;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(TtsOrchestrator::class)
            ->shouldReceive('getOrGenerate')
            ->zeroOrMoreTimes()
            ->andReturnNull();

        $this->game = Game::factory()->create(['table_prefix' => 'find_the_wrong']);
        $this->level = FindTheWrongLevel::factory()->create();
        $this->items = FindTheWrongItem::factory()
            ->count(4)
            ->sequence(
                ['name' => ['en' => 'Iron', 'uk' => 'Праска', 'es' => 'Plancha'], 'explanation' => ['en' => 'Belongs in laundry', 'uk' => 'Не місце на кухні', 'es' => 'Pertenece a lavandería']],
                ['name' => ['en' => 'Toilet', 'uk' => 'Унітаз', 'es' => 'Inodoro'], 'explanation' => ['en' => 'Belongs in bathroom', 'uk' => 'Це для ванної', 'es' => 'Pertenece al baño']],
                ['name' => ['en' => 'Hammer', 'uk' => 'Молоток', 'es' => 'Martillo'], 'explanation' => ['en' => 'Belongs in toolbox', 'uk' => 'Це інструмент', 'es' => 'Pertenece a caja de herramientas']],
                ['name' => ['en' => 'Spoon', 'uk' => 'Ложка', 'es' => 'Cuchara'], 'explanation' => ['en' => 'Belongs in kitchen', 'uk' => 'Це для кухні', 'es' => 'Pertenece a la cocina']],
            )
            ->create(['level_id' => $this->level->id])
            ->all();

        $this->user = User::factory()->create();
    }

    private function route(?int $gameId = null, ?int $levelId = null): string
    {
        $gameId ??= $this->game->id;
        $levelId ??= $this->level->id;

        return "/api/games/{$gameId}/levels/{$levelId}/attempts";
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'duration_seconds' => 42,
            'found' => [
                ['item_id' => $this->items[0]->id, 'stars' => 3],
                ['item_id' => $this->items[1]->id, 'stars' => 2],
                ['item_id' => $this->items[2]->id, 'stars' => 1],
            ],
            'missed_item_ids' => [$this->items[3]->id],
        ];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson($this->route(), $this->validPayload())->assertStatus(401);
    }

    public function test_nonexistent_game_returns_404(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->route(gameId: 999999), $this->validPayload())
            ->assertStatus(404);
    }

    public function test_nonexistent_level_returns_404(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->route(levelId: 999999), $this->validPayload())
            ->assertStatus(404);
    }

    public function test_payload_for_a_different_game_type_returns_422(): void
    {
        // The submit endpoint is shared across games; a find-the-wrong payload
        // sent to a true/false game fails that game's validation (422), not 404.
        $otherGame = Game::factory()->create(['table_prefix' => 'true_false_image']);

        $this->actingAs($this->user)
            ->postJson($this->route(gameId: $otherGame->id), $this->validPayload())
            ->assertStatus(422);
    }

    public function test_happy_path_returns_score_and_reveal_data(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson($this->route(), $this->validPayload());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'score',
                'total_questions',
                'found_items' => [['id', 'stars', 'name', 'explanation']],
                'missed_items' => [['id', 'name', 'explanation']],
            ])
            ->assertJsonPath('score', 3)
            ->assertJsonPath('total_questions', 4);

        $foundItems = $response->json('found_items');
        $this->assertCount(3, $foundItems);
        $this->assertSame($this->items[0]->id, $foundItems[0]['id']);
        $this->assertSame(3, $foundItems[0]['stars']);
        $this->assertSame('Iron', $foundItems[0]['name']);
        $this->assertSame('Belongs in laundry', $foundItems[0]['explanation']);

        $missedItems = $response->json('missed_items');
        $this->assertCount(1, $missedItems);
        $this->assertSame($this->items[3]->id, $missedItems[0]['id']);
        $this->assertSame('Spoon', $missedItems[0]['name']);
        $this->assertArrayNotHasKey('stars', $missedItems[0]);
    }

    public function test_happy_path_persists_game_result(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->route(), $this->validPayload())
            ->assertStatus(200);

        $this->assertDatabaseCount('game_results', 1);

        $result = GameResult::first();
        $this->assertNotNull($result);
        $this->assertSame($this->user->id, $result->user_id);
        $this->assertSame($this->game->id, $result->game_id);
        $this->assertSame($this->level->id, $result->level_id);
        $this->assertSame(3, $result->score);
        $this->assertSame(4, $result->total_questions);

        $details = $result->details;
        $this->assertCount(3, $details['found']);
        $this->assertSame($this->items[0]->id, $details['found'][0]['item_id']);
        $this->assertSame(3, $details['found'][0]['stars']);
        $this->assertSame([$this->items[3]->id], $details['missed_item_ids']);
        $this->assertSame(42, $details['duration_seconds']);
    }

    public function test_interaction_mode_defaults_to_circle_when_absent(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->route(), $this->validPayload())
            ->assertStatus(200);

        $result = GameResult::first();
        $this->assertNotNull($result);
        $this->assertSame('circle', $result->details['interaction_mode']);
    }

    public function test_marker_interaction_mode_is_persisted(): void
    {
        $payload = $this->validPayload();
        $payload['interaction_mode'] = 'marker';

        $this->actingAs($this->user)
            ->postJson($this->route(), $payload)
            ->assertStatus(200);

        $result = GameResult::first();
        $this->assertNotNull($result);
        $this->assertSame('marker', $result->details['interaction_mode']);
    }

    public function test_invalid_interaction_mode_returns_422(): void
    {
        $payload = $this->validPayload();
        $payload['interaction_mode'] = 'swipe';

        $this->actingAs($this->user)
            ->postJson($this->route(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['interaction_mode']);
    }

    public function test_stars_out_of_range_returns_422(): void
    {
        $payload = $this->validPayload();
        $payload['found'][0]['stars'] = 5;

        $this->actingAs($this->user)
            ->postJson($this->route(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['found.0.stars']);
    }

    public function test_item_from_different_level_returns_422(): void
    {
        $otherLevel = FindTheWrongLevel::factory()->create();
        $foreignItem = FindTheWrongItem::factory()->create(['level_id' => $otherLevel->id]);

        $payload = $this->validPayload();
        $payload['found'][0]['item_id'] = $foreignItem->id;

        $this->actingAs($this->user)
            ->postJson($this->route(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['found.0.item_id']);
    }

    public function test_count_mismatch_returns_422(): void
    {
        $payload = $this->validPayload();
        array_pop($payload['missed_item_ids']);

        $this->actingAs($this->user)
            ->postJson($this->route(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['found']);
    }

    public function test_overlap_returns_422(): void
    {
        $payload = [
            'duration_seconds' => 10,
            'found' => [
                ['item_id' => $this->items[0]->id, 'stars' => 3],
                ['item_id' => $this->items[1]->id, 'stars' => 2],
            ],
            'missed_item_ids' => [
                $this->items[0]->id,
                $this->items[2]->id,
                $this->items[3]->id,
            ],
        ];

        $this->actingAs($this->user)
            ->postJson($this->route(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['found']);
    }

    public function test_duration_seconds_out_of_range_returns_422(): void
    {
        $payload = $this->validPayload();
        $payload['duration_seconds'] = 9999;

        $this->actingAs($this->user)
            ->postJson($this->route(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration_seconds']);
    }

    public function test_duplicate_item_id_in_found_returns_422(): void
    {
        $payload = [
            'duration_seconds' => 10,
            'found' => [
                ['item_id' => $this->items[0]->id, 'stars' => 3],
                ['item_id' => $this->items[0]->id, 'stars' => 1],
                ['item_id' => $this->items[1]->id, 'stars' => 2],
            ],
            'missed_item_ids' => [$this->items[2]->id, $this->items[3]->id],
        ];

        $this->actingAs($this->user)
            ->postJson($this->route(), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['found.0.item_id']);
    }

    public function test_double_submit_persists_two_game_results(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->route(), $this->validPayload())
            ->assertStatus(200);

        $this->actingAs($this->user)
            ->postJson($this->route(), $this->validPayload())
            ->assertStatus(200);

        $this->assertDatabaseCount('game_results', 2);
    }

    public function test_happy_path_returns_translated_names_for_ukrainian_locale(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['Accept-Language' => 'uk'])
            ->postJson($this->route(), $this->validPayload());

        $response->assertStatus(200);

        $foundItems = $response->json('found_items');
        $this->assertSame('Праска', $foundItems[0]['name']);
        $this->assertSame('Не місце на кухні', $foundItems[0]['explanation']);

        $missedItems = $response->json('missed_items');
        $this->assertSame('Ложка', $missedItems[0]['name']);
    }
}

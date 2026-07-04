<?php

namespace Tests\Feature\Admin;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Jobs\Tts\GenerateTtsAudioJob;
use App\Models\Game;
use App\Models\User;
use App\Services\Tts\TtsPathHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The shared statement admin dispatcher serves both true/false games at the
 * generic admin/games/{game}/statements URLs, resolving the target model by
 * the game's table_prefix.
 */
class StatementAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private Game $imageGame;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
        config(['games.upload_disk' => 'public']);

        $this->imageGame = Game::factory()->create(['table_prefix' => 'true_false_image']);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create();
        $level = TrueFalseImageLevel::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/admin/games/{$this->imageGame->id}/levels/{$level->id}/statements", [])
            ->assertStatus(403);
    }

    public function test_admin_creates_statement_for_image_game(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseImageLevel::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            "/api/admin/games/{$this->imageGame->id}/levels/{$level->id}/statements",
            [
                'statement' => ['uk' => 'Твердження', 'en' => 'Statement', 'es' => 'Afirmacion'],
                'explanation' => ['uk' => 'Пояснення', 'en' => 'Why', 'es' => 'Porque'],
                'is_true' => true,
            ]
        );

        $response->assertStatus(201)
            ->assertJsonPath('statement.en', 'Statement')
            ->assertJsonPath('is_true', true)
            ->assertJsonStructure(['id', 'level_id', 'statement_audio' => ['en' => ['url', 'is_stale']]]);

        $this->assertDatabaseHas('true_false_image_statements', ['level_id' => $level->id]);
    }

    public function test_create_requires_is_true_boolean(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseImageLevel::factory()->create();

        $this->actingAs($admin)->postJson(
            "/api/admin/games/{$this->imageGame->id}/levels/{$level->id}/statements",
            ['statement' => ['uk' => 'a', 'en' => 'b', 'es' => 'c']]
        )->assertStatus(422)->assertJsonValidationErrors(['is_true']);
    }

    public function test_admin_updates_and_deletes_statement(): void
    {
        $admin = User::factory()->admin()->create();
        $statement = TrueFalseImageStatement::factory()->create();

        $this->actingAs($admin)->patchJson(
            "/api/admin/games/{$this->imageGame->id}/statements/{$statement->id}",
            [
                'statement' => ['uk' => 'Нове', 'en' => 'Updated', 'es' => 'Nuevo'],
                'is_true' => false,
            ]
        )->assertStatus(200)->assertJsonPath('statement.en', 'Updated')->assertJsonPath('is_true', false);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/games/{$this->imageGame->id}/statements/{$statement->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('true_false_image_statements', ['id' => $statement->id]);
    }

    public function test_statement_dispatcher_supports_text_game(): void
    {
        $admin = User::factory()->admin()->create();
        $textGame = Game::factory()->create(['table_prefix' => 'true_false_text']);
        $level = TrueFalseTextLevel::factory()->create();

        $this->actingAs($admin)->postJson(
            "/api/admin/games/{$textGame->id}/levels/{$level->id}/statements",
            [
                'statement' => ['uk' => 'Т', 'en' => 'S', 'es' => 'A'],
                'is_true' => true,
            ]
        )->assertStatus(201);

        $this->assertDatabaseHas('true_false_text_statements', ['level_id' => $level->id]);
    }

    public function test_unconfigured_game_prefix_is_not_found(): void
    {
        $admin = User::factory()->admin()->create();
        $otherGame = Game::factory()->create(['table_prefix' => 'find_the_wrong']);
        $statement = TrueFalseImageStatement::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/admin/games/{$otherGame->id}/statements/{$statement->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Admin operations are not available for this game.');
    }

    public function test_audio_status_reflects_hash_match(): void
    {
        $admin = User::factory()->admin()->create();
        $statement = TrueFalseImageStatement::factory()->create([
            'statement' => ['uk' => 'Т', 'en' => 'Hello', 'es' => 'A'],
        ]);
        $hash = TtsPathHash::forText('Hello', 'en');
        $statement->setTranslation('statement_audio_url', 'en', $statement->storageDirectory()."/en/statement_{$hash}.mp3");
        $statement->save();
        $level = $statement->level;

        $this->actingAs($admin)
            ->getJson("/api/admin/games/{$this->imageGame->id}/levels/{$level->id}")
            ->assertStatus(200)
            ->assertJsonPath('statements.0.statement_audio.en.is_stale', false)
            ->assertJsonPath('statements.0.statement_audio.es.is_stale', true);
    }

    public function test_reading_statements_triggers_no_generation(): void
    {
        $admin = User::factory()->admin()->create();
        $statement = TrueFalseImageStatement::factory()->create();
        $level = $statement->level;

        Queue::fake();

        $this->actingAs($admin)
            ->getJson("/api/admin/games/{$this->imageGame->id}/levels/{$level->id}")
            ->assertStatus(200);

        Queue::assertNothingPushed();
    }

    public function test_regenerate_dispatches_job_for_exact_field_and_locale(): void
    {
        $admin = User::factory()->admin()->create();
        $statement = TrueFalseImageStatement::factory()->create([
            'statement' => ['uk' => 'Т', 'en' => 'Hello', 'es' => 'A'],
        ]);

        Queue::fake();

        $this->actingAs($admin)->postJson(
            "/api/admin/games/{$this->imageGame->id}/statements/{$statement->id}/audio/regenerate",
            ['field' => 'statement_audio_url', 'locale' => 'en']
        )->assertStatus(202);

        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job): bool => $job->attribute === 'statement_audio_url'
                && $job->locale === 'en'
                && $job->model->is($statement)
        );
        Queue::assertPushed(GenerateTtsAudioJob::class, 1);
    }

    public function test_regenerate_rejects_unknown_field(): void
    {
        $admin = User::factory()->admin()->create();
        $statement = TrueFalseImageStatement::factory()->create();

        $this->actingAs($admin)->postJson(
            "/api/admin/games/{$this->imageGame->id}/statements/{$statement->id}/audio/regenerate",
            ['field' => 'nope_audio_url', 'locale' => 'en']
        )->assertStatus(422)->assertJsonValidationErrors(['field']);
    }
}

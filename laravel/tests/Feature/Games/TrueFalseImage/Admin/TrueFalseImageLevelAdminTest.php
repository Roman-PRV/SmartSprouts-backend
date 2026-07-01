<?php

namespace Tests\Feature\Games\TrueFalseImage\Admin;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Jobs\Tts\GenerateTtsAudioJob;
use App\Models\Game;
use App\Models\User;
use App\Services\Tts\TtsPathHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TrueFalseImage levels flow through the generic Admin\LevelController
 * dispatcher (title + required image), with a per-game show resource embedding
 * statements and per-locale audio status, plus level audio regeneration.
 */
class TrueFalseImageLevelAdminTest extends TestCase
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

        $this->game = Game::factory()->create(['table_prefix' => 'true_false_image']);
        $this->route = "/api/admin/games/{$this->game->id}/levels";
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson($this->route)->assertStatus(403);
    }

    public function test_admin_creates_image_level_with_image(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson($this->route, [
            'title' => ['uk' => 'Кухня', 'en' => 'Kitchen', 'es' => 'Cocina'],
            'image' => UploadedFile::fake()->image('kitchen.png', 800, 600),
        ]);

        $response->assertStatus(201)->assertJsonPath('title.en', 'Kitchen');

        $level = TrueFalseImageLevel::query()->findOrFail($response->json('id'));
        Storage::disk('public')->assertExists($level->storageDirectory().'/image.png');
    }

    public function test_store_accepts_image_up_to_10mb(): void
    {
        $admin = User::factory()->admin()->create();

        // Larger than the old 5 MB cap, within the 10 MB limit that matches the
        // frontend, nginx and PHP. Regression guard for the layer mismatch.
        $response = $this->actingAs($admin)->postJson($this->route, [
            'title' => ['uk' => 'Кухня', 'en' => 'Kitchen', 'es' => 'Cocina'],
            'image' => UploadedFile::fake()->create('cover.png', 8000, 'image/png'),
        ]);

        $response->assertStatus(201);
    }

    public function test_store_rejects_image_over_10mb(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson($this->route, [
            'title' => ['uk' => 'a', 'en' => 'b', 'es' => 'c'],
            'image' => UploadedFile::fake()->create('cover.png', 11000, 'image/png'),
        ])->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_store_requires_image_for_image_game(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson($this->route, ['title' => ['uk' => 'a', 'en' => 'b', 'es' => 'c']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_index_returns_statements_count(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseImageLevel::factory()->create();
        TrueFalseImageStatement::factory()->count(2)->create(['level_id' => $level->id]);

        $this->actingAs($admin)->getJson($this->route)
            ->assertStatus(200)
            ->assertJsonStructure([['id', 'title', 'image_url', 'statements_count']])
            ->assertJsonPath('0.statements_count', 2);
    }

    public function test_show_embeds_statements_and_audio_status(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseImageLevel::factory()->create([
            'title' => ['uk' => 'Заголовок', 'en' => 'Title', 'es' => 'Titulo'],
        ]);
        TrueFalseImageStatement::factory()->create(['level_id' => $level->id]);

        $this->actingAs($admin)->getJson("{$this->route}/{$level->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'title_audio' => ['en' => ['url', 'is_stale']],
                'statements' => [['id', 'is_true', 'statement', 'statement_audio', 'explanation_audio']],
            ])
            // Title text exists but no audio yet → stale.
            ->assertJsonPath('title_audio.en.is_stale', true)
            ->assertJsonPath('title_audio.en.url', null);
    }

    public function test_audio_is_fresh_when_hash_matches_stored_path(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseImageLevel::factory()->create([
            'title' => ['uk' => 'Заголовок', 'en' => 'Title', 'es' => 'Titulo'],
        ]);
        $hash = TtsPathHash::forText('Title', 'en');
        $level->setTranslation('title_audio_url', 'en', $level->storageDirectory()."/en/title_{$hash}.mp3");
        $level->save();

        $this->actingAs($admin)->getJson("{$this->route}/{$level->id}")
            ->assertStatus(200)
            ->assertJsonPath('title_audio.en.is_stale', false);
    }

    public function test_destroy_cascades_statements_and_cleans_storage(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseImageLevel::factory()->create();
        $statement = TrueFalseImageStatement::factory()->create(['level_id' => $level->id]);

        $audioPath = $statement->storageDirectory().'/en/statement_abc12345.mp3';
        Storage::disk('public')->put($audioPath, 'bytes');

        $this->actingAs($admin)->deleteJson("{$this->route}/{$level->id}")->assertStatus(204);

        $this->assertDatabaseMissing('true_false_image_levels', ['id' => $level->id]);
        $this->assertDatabaseMissing('true_false_image_statements', ['id' => $statement->id]);
        Storage::disk('public')->assertMissing($audioPath);
    }

    public function test_regenerate_level_audio_dispatches_job_for_exact_field_and_locale(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseImageLevel::factory()->create([
            'title' => ['uk' => 'Заголовок', 'en' => 'Title', 'es' => 'Titulo'],
        ]);

        Queue::fake();

        $this->actingAs($admin)
            ->postJson("{$this->route}/{$level->id}/audio/regenerate", [
                'field' => 'title_audio_url',
                'locale' => 'en',
            ])
            ->assertStatus(202);

        Queue::assertPushed(
            GenerateTtsAudioJob::class,
            fn (GenerateTtsAudioJob $job): bool => $job->attribute === 'title_audio_url'
                && $job->locale === 'en'
                && $job->model->is($level)
        );
        Queue::assertPushed(GenerateTtsAudioJob::class, 1);
    }

    public function test_regenerate_rejects_unknown_field_and_locale(): void
    {
        $admin = User::factory()->admin()->create();
        $level = TrueFalseImageLevel::factory()->create([
            'title' => ['uk' => 'a', 'en' => 'b', 'es' => 'c'],
        ]);
        $base = "{$this->route}/{$level->id}/audio/regenerate";

        $this->actingAs($admin)
            ->postJson($base, ['field' => 'bogus_audio_url', 'locale' => 'en'])
            ->assertStatus(422)->assertJsonValidationErrors(['field']);

        $this->actingAs($admin)
            ->postJson($base, ['field' => 'title_audio_url', 'locale' => 'de'])
            ->assertStatus(422)->assertJsonValidationErrors(['locale']);
    }
}

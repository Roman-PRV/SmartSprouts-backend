<?php

namespace Tests\Feature;

use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function redirect_returns_google_authorization_url(): void
    {
        $redirectResponse = Mockery::mock(RedirectResponse::class);
        $redirectResponse->shouldReceive('getTargetUrl')
            ->andReturn('https://accounts.google.com/o/oauth2/auth?client_id=test');

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn($redirectResponse);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/redirect');

        $response->assertOk()
            ->assertJsonStructure(['url'])
            ->assertJson(['url' => 'https://accounts.google.com/o/oauth2/auth?client_id=test']);
    }

    /** @test */
    public function callback_creates_new_user_and_returns_token(): void
    {
        $googleUser = $this->mockGoogleUser('google-id-123', 'newuser@gmail.com', 'New User', 'https://avatar.url');
        $this->mockSocialiteCallback($googleUser);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@gmail.com',
            'google_id' => 'google-id-123',
            'avatar' => 'https://avatar.url',
        ]);

        $this->assertNotNull(User::query()->where('email', 'newuser@gmail.com')->value('email_verified_at'));
    }

    /** @test */
    public function callback_logs_in_existing_user_found_by_google_id(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-id-456',
            'email' => 'existing@gmail.com',
        ]);

        $googleUser = $this->mockGoogleUser('google-id-456', 'existing@gmail.com', $user->name, null);
        $this->mockSocialiteCallback($googleUser);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseCount('users', 1);
    }

    /** @test */
    public function callback_links_google_id_to_existing_user_found_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@gmail.com',
            'google_id' => null,
        ]);

        $googleUser = $this->mockGoogleUser('google-id-789', 'linked@gmail.com', $user->name, 'https://avatar.url');
        $this->mockSocialiteCallback($googleUser);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-id-789',
            'avatar' => 'https://avatar.url',
        ]);

        $this->assertDatabaseCount('users', 1);
    }

    /** @test */
    public function callback_returns_401_on_invalid_state_exception(): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Invalid OAuth state.']);
    }

    /** @test */
    public function callback_returns_401_on_general_google_exception(): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new Exception('Connection failed'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Google authentication failed.']);
    }

    /**
     * Create a mock of a Socialite Google user.
     */
    private function mockGoogleUser(string $id, string $email, string $name, ?string $avatar): SocialiteUser
    {
        /** @var SocialiteUser $googleUser */
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getAvatar')->andReturn($avatar);

        return $googleUser;
    }

    /**
     * Mock the Socialite driver to return a given user from the callback.
     */
    private function mockSocialiteCallback(SocialiteUser $googleUser): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}

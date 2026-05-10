<?php

namespace Tests\Feature;

use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const STATE_COOKIE = 'google_oauth_state';

    private const VALID_STATE = 'valid-test-state-value-32-chars-1234';

    /** @test */
    public function redirect_returns_google_authorization_url_and_sets_state_cookie(): void
    {
        $redirectResponse = Mockery::mock(RedirectResponse::class);
        $redirectResponse->shouldReceive('getTargetUrl')
            ->andReturn('https://accounts.google.com/o/oauth2/auth?client_id=test&state=generated');

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')
            ->with(Mockery::on(function ($params): bool {
                return is_array($params)
                    && isset($params['state'])
                    && is_string($params['state'])
                    && strlen($params['state']) >= 32;
            }))
            ->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn($redirectResponse);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/redirect');

        $response->assertOk()
            ->assertJsonStructure(['url'])
            ->assertCookie(self::STATE_COOKIE);
    }

    /** @test */
    public function callback_creates_new_user_and_returns_token(): void
    {
        $googleUser = $this->mockGoogleUser('google-id-123', 'newuser@gmail.com', 'New User', 'https://avatar.url');
        $this->mockSocialiteCallback($googleUser);

        $response = $this->callbackWithValidState();

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

        $response = $this->callbackWithValidState();

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

        $response = $this->callbackWithValidState();

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
    public function callback_returns_401_when_state_query_parameter_is_missing(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie(self::STATE_COOKIE, Crypt::encryptString(self::VALID_STATE))
            ->getJson('/api/auth/google/callback');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Invalid OAuth state.']);
    }

    /** @test */
    public function callback_returns_401_when_state_cookie_is_missing(): void
    {
        $response = $this->withCredentials()
            ->getJson('/api/auth/google/callback?state='.self::VALID_STATE);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Invalid OAuth state.']);
    }

    /** @test */
    public function callback_returns_401_when_state_cookie_cannot_be_decrypted(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie(self::STATE_COOKIE, 'not-a-valid-encrypted-payload')
            ->getJson('/api/auth/google/callback?state='.self::VALID_STATE);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Invalid OAuth state.']);
    }

    /** @test */
    public function callback_returns_401_when_state_query_does_not_match_cookie(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie(self::STATE_COOKIE, Crypt::encryptString(self::VALID_STATE))
            ->getJson('/api/auth/google/callback?state=tampered-state-value');

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

        $response = $this->callbackWithValidState();

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Google authentication failed.']);
    }

    /** @test */
    public function callback_returns_422_on_missing_google_id(): void
    {
        $googleUser = $this->mockGoogleUser('', 'user@gmail.com', 'User', null);
        $this->mockSocialiteCallback($googleUser);

        $response = $this->callbackWithValidState();

        $response->assertStatus(422)
            ->assertJson(['message' => 'Google account data is incomplete or invalid.']);
    }

    /** @test */
    public function callback_returns_422_on_invalid_email(): void
    {
        $googleUser = $this->mockGoogleUser('google-id-999', 'not-an-email', 'User', null);
        $this->mockSocialiteCallback($googleUser);

        $response = $this->callbackWithValidState();

        $response->assertStatus(422)
            ->assertJson(['message' => 'Google account data is incomplete or invalid.']);
    }

    /** @test */
    public function callback_uses_email_local_part_when_name_is_missing(): void
    {
        $googleUser = $this->mockGoogleUser('google-id-000', 'johndoe@gmail.com', '', null);
        $this->mockSocialiteCallback($googleUser);

        $response = $this->callbackWithValidState();

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@gmail.com',
            'name' => 'johndoe',
        ]);
    }

    /**
     * Send a callback request with a matching state cookie and query parameter.
     */
    private function callbackWithValidState(): TestResponse
    {
        return $this->withCredentials()
            ->withUnencryptedCookie(self::STATE_COOKIE, Crypt::encryptString(self::VALID_STATE))
            ->getJson('/api/auth/google/callback?state='.self::VALID_STATE);
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

<?php

namespace Tests\Feature;

use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
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

    private const FRONTEND_CALLBACK = 'http://localhost:3001/auth/google/callback';

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
    public function callback_creates_new_user_and_redirects_with_token(): void
    {
        $googleUser = $this->mockGoogleUser('google-id-123', 'newuser@gmail.com', 'New User', 'https://avatar.url');
        $this->mockSocialiteCallback($googleUser);

        $response = $this->callbackWithValidState();

        $this->assertRedirectsToFrontendCallbackWithToken($response);

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

        $this->assertRedirectsToFrontendCallbackWithToken($response);
        $this->assertTokenBelongsToUser($user);
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

        $this->assertRedirectsToFrontendCallbackWithToken($response);
        $this->assertTokenBelongsToUser($user);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-id-789',
            'avatar' => 'https://avatar.url',
        ]);

        $this->assertDatabaseCount('users', 1);
    }

    /** @test */
    public function callback_redirects_with_invalid_state_error_when_state_query_is_missing(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie(self::STATE_COOKIE, Crypt::encryptString(self::VALID_STATE))
            ->get('/api/auth/google/callback');

        $this->assertRedirectsToFrontendCallbackWithError($response, 'invalid_state');
    }

    /** @test */
    public function callback_redirects_with_invalid_state_error_when_state_cookie_is_missing(): void
    {
        $response = $this->withCredentials()
            ->get('/api/auth/google/callback?state='.self::VALID_STATE);

        $this->assertRedirectsToFrontendCallbackWithError($response, 'invalid_state');
    }

    /** @test */
    public function callback_redirects_with_invalid_state_error_when_state_cookie_cannot_be_decrypted(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie(self::STATE_COOKIE, 'not-a-valid-encrypted-payload')
            ->get('/api/auth/google/callback?state='.self::VALID_STATE);

        $this->assertRedirectsToFrontendCallbackWithError($response, 'invalid_state');
    }

    /** @test */
    public function callback_redirects_with_invalid_state_error_when_state_query_does_not_match_cookie(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie(self::STATE_COOKIE, Crypt::encryptString(self::VALID_STATE))
            ->get('/api/auth/google/callback?state=tampered-state-value');

        $this->assertRedirectsToFrontendCallbackWithError($response, 'invalid_state');
    }

    /** @test */
    public function callback_redirects_with_auth_failed_error_on_general_google_exception(): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new Exception('Connection failed'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->callbackWithValidState();

        $this->assertRedirectsToFrontendCallbackWithError($response, 'auth_failed');
    }

    /** @test */
    public function callback_redirects_with_invalid_account_error_on_missing_google_id(): void
    {
        $googleUser = $this->mockGoogleUser('', 'user@gmail.com', 'User', null);
        $this->mockSocialiteCallback($googleUser);

        $response = $this->callbackWithValidState();

        $this->assertRedirectsToFrontendCallbackWithError($response, 'invalid_account');
    }

    /** @test */
    public function callback_redirects_with_invalid_account_error_on_invalid_email(): void
    {
        $googleUser = $this->mockGoogleUser('google-id-999', 'not-an-email', 'User', null);
        $this->mockSocialiteCallback($googleUser);

        $response = $this->callbackWithValidState();

        $this->assertRedirectsToFrontendCallbackWithError($response, 'invalid_account');
    }

    /** @test */
    public function callback_uses_email_local_part_when_name_is_missing(): void
    {
        $googleUser = $this->mockGoogleUser('google-id-000', 'johndoe@gmail.com', '', null);
        $this->mockSocialiteCallback($googleUser);

        $response = $this->callbackWithValidState();

        $this->assertRedirectsToFrontendCallbackWithToken($response);
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
            ->get('/api/auth/google/callback?state='.self::VALID_STATE);
    }

    /**
     * Assert the response is a 302 redirect to the SPA callback URL whose
     * fragment carries an `access_token` and `Bearer` token type, that the
     * state cookie is cleared, and that caching is disabled.
     */
    private function assertRedirectsToFrontendCallbackWithToken(TestResponse $response): void
    {
        $fragment = $this->assertRedirectsToFrontendCallback($response);

        $this->assertArrayHasKey('access_token', $fragment);
        $this->assertNotEmpty($fragment['access_token']);
        $this->assertSame('Bearer', $fragment['token_type'] ?? null);
        $this->assertArrayNotHasKey('error', $fragment);
    }

    /**
     * Assert the response is a 302 redirect to the SPA callback URL whose
     * fragment carries the expected `error` code (and no token).
     */
    private function assertRedirectsToFrontendCallbackWithError(TestResponse $response, string $expectedError): void
    {
        $fragment = $this->assertRedirectsToFrontendCallback($response);

        $this->assertSame($expectedError, $fragment['error'] ?? null);
        $this->assertArrayNotHasKey('access_token', $fragment);
    }

    /**
     * Common assertions for the redirect: status, base URL, security headers,
     * and cleared state cookie. Returns the parsed fragment for further checks.
     *
     * @return array<string, string>
     */
    private function assertRedirectsToFrontendCallback(TestResponse $response): array
    {
        $response->assertStatus(302);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertCookieExpired(self::STATE_COOKIE);

        $location = $response->headers->get('Location');
        $this->assertIsString($location);
        $this->assertStringStartsWith(self::FRONTEND_CALLBACK.'#', $location);

        $fragment = substr($location, strlen(self::FRONTEND_CALLBACK) + 1);
        parse_str($fragment, $params);

        /** @var array<string, string> $params */
        return $params;
    }

    /**
     * Verify the most recently issued personal access token belongs to the
     * given user — replaces the previous `assertJsonPath('user.id', ...)`
     * check now that the response no longer carries a user payload.
     */
    private function assertTokenBelongsToUser(User $user): void
    {
        $token = PersonalAccessToken::query()->latest('id')->first();
        $this->assertNotNull($token);
        $this->assertSame($user->id, $token->tokenable_id);
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

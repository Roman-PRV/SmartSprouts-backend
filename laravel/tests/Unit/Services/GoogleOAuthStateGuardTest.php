<?php

namespace Tests\Unit\Services;

use App\Services\GoogleOAuthStateGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class GoogleOAuthStateGuardTest extends TestCase
{
    private GoogleOAuthStateGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new GoogleOAuthStateGuard;
    }

    public function test_start_returns_url_with_state_and_matching_cookie(): void
    {
        $capturedState = null;

        $redirectResponse = Mockery::mock(RedirectResponse::class);
        $redirectResponse->shouldReceive('getTargetUrl')
            ->andReturn('https://accounts.google.com/o/oauth2/auth?state=injected');

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')
            ->with(Mockery::on(function ($params) use (&$capturedState): bool {
                if (! is_array($params) || ! isset($params['state']) || ! is_string($params['state'])) {
                    return false;
                }
                $capturedState = $params['state'];

                return strlen($capturedState) >= 32;
            }))
            ->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn($redirectResponse);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $result = $this->guard->start();

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('cookie', $result);
        $this->assertSame('https://accounts.google.com/o/oauth2/auth?state=injected', $result['url']);
        $this->assertSame(GoogleOAuthStateGuard::COOKIE_NAME, $result['cookie']->getName());
        $this->assertTrue($result['cookie']->isHttpOnly());
        $this->assertSame('lax', $result['cookie']->getSameSite());

        $this->assertNotNull($capturedState);
        $this->assertSame($capturedState, Crypt::decryptString($result['cookie']->getValue()));
    }

    public function test_validate_returns_true_when_query_state_matches_cookie(): void
    {
        $state = 'a-valid-state-token';
        $request = Request::create('/api/auth/google/callback', 'GET', ['state' => $state]);
        $request->cookies->set(GoogleOAuthStateGuard::COOKIE_NAME, Crypt::encryptString($state));

        $this->assertTrue($this->guard->validate($request));
    }

    public function test_validate_returns_false_when_query_state_is_missing(): void
    {
        $request = Request::create('/api/auth/google/callback');
        $request->cookies->set(GoogleOAuthStateGuard::COOKIE_NAME, Crypt::encryptString('any'));

        $this->assertFalse($this->guard->validate($request));
    }

    public function test_validate_returns_false_when_cookie_is_missing(): void
    {
        $request = Request::create('/api/auth/google/callback', 'GET', ['state' => 'something']);

        $this->assertFalse($this->guard->validate($request));
    }

    public function test_validate_returns_false_when_cookie_cannot_be_decrypted(): void
    {
        $request = Request::create('/api/auth/google/callback', 'GET', ['state' => 'something']);
        $request->cookies->set(GoogleOAuthStateGuard::COOKIE_NAME, 'garbage-payload');

        $this->assertFalse($this->guard->validate($request));
    }

    public function test_validate_returns_false_when_states_do_not_match(): void
    {
        $request = Request::create('/api/auth/google/callback', 'GET', ['state' => 'attacker-state']);
        $request->cookies->set(GoogleOAuthStateGuard::COOKIE_NAME, Crypt::encryptString('legit-state'));

        $this->assertFalse($this->guard->validate($request));
    }

    public function test_forget_cookie_returns_expired_cookie(): void
    {
        $cookie = $this->guard->forgetCookie();

        $this->assertSame(GoogleOAuthStateGuard::COOKIE_NAME, $cookie->getName());
        $this->assertLessThanOrEqual(time(), $cookie->getExpiresTime());
    }

    public function test_retrieve_google_user_delegates_to_socialite_in_stateless_mode(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->assertSame($socialiteUser, $this->guard->retrieveGoogleUser());
    }
}

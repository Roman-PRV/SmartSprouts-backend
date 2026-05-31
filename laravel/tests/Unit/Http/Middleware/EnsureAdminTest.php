<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureAdminTest extends TestCase
{
    private EnsureAdmin $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureAdmin;
    }

    public function test_returns_401_when_request_has_no_user(): void
    {
        $request = Request::create('/test');

        try {
            $this->middleware->handle($request, fn () => null);
            $this->fail('Expected HttpException to be thrown.');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }
    }

    public function test_returns_403_when_user_is_not_admin(): void
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn () => new User(['is_admin' => false]));

        try {
            $this->middleware->handle($request, fn () => null);
            $this->fail('Expected HttpException to be thrown.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_passes_through_admin_user(): void
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn () => new User(['is_admin' => true]));

        $response = $this->middleware->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }
}

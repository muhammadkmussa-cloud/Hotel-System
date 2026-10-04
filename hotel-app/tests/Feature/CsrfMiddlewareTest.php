<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RequireCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CsrfMiddlewareTest extends TestCase
{
    public function testMissingSessionAndQueryOrBodyTokensCannotAuthorizeMutation(): void
    {
        $request = Request::create('/api/v1/probe?_token=fake', 'POST', ['_token' => 'fake']);
        $this->expectException(AccessDeniedHttpException::class);
        (new RequireCsrfToken)->handle($request, fn () => throw new RuntimeException('Handler ran'));
    }

    public function testValidTokenAndRotationAreSessionBound(): void
    {
        $session = new Store('fixture', new ArraySessionHandler(120));
        $session->start();
        $request = Request::create('/api/v1/probe', 'POST', server: ['HTTP_X_CSRF_TOKEN' => $session->token()]);
        $request->setLaravelSession($session);
        self::assertSame(200, (new RequireCsrfToken)->handle($request, fn () => new Response)->getStatusCode());
        $session->regenerateToken();
        $this->expectException(AccessDeniedHttpException::class);
        (new RequireCsrfToken)->handle($request, fn () => throw new RuntimeException('Handler ran'));
    }
}

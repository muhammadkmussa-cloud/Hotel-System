<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RequireCapability;
use App\Http\Middleware\RequirePrincipal;
use App\Security\CapabilityAuthorizer;
use App\Security\DenyAccess;
use App\Security\Principal;
use App\Security\PrincipalResolver;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AccessMiddlewareTest extends TestCase
{
    private function principal(): Principal
    {
        return new class implements Principal { public function identifier(): string { return 'verified-fixture'; } };
    }

    public function testDefaultDenialIgnoresAllSubmittedIdentitiesAndNeverRunsHandler(): void
    {
        $request = Request::create('/api/v1/probe?guestId=other', 'POST', ['principal' => 'owner'], server: ['HTTP_AUTHORIZATION' => 'Bearer fake', 'HTTP_X_ROLE' => 'owner']);
        $request->attributes->set(RequirePrincipal::ATTRIBUTE, $this->principal());
        $middleware = new RequireCapability(new RequirePrincipal(new DenyAccess), new DenyAccess);
        try {
            $middleware->handle($request, fn () => throw new RuntimeException('Handler ran'), 'payments.card');
            self::fail('Missing principal accepted.');
        } catch (AuthenticationException) {
            self::assertFalse($request->attributes->has(RequirePrincipal::ATTRIBUTE));
        }
    }

    public function testVerifiedPrincipalStillNeedsPermissionAndCorrectScope(): void
    {
        $principal = $this->principal();
        $resolver = new class($principal) implements PrincipalResolver {
            public function __construct(private Principal $principal) {}
            public function resolve(Request $request): ?Principal { return $this->principal; }
        };
        $authorizer = new class implements CapabilityAuthorizer {
            public function allows(Principal $principal, string $capability, Request $request): bool {
                return $principal->identifier() === 'verified-fixture' && $capability === 'reports.view' && $request->route('scope') === 'own';
            }
        };
        foreach (['other', 'own'] as $scope) {
            $request = Request::create('/api/v1/probe');
            $request->setRouteResolver(fn () => new class($scope) {
                public function __construct(private string $scope) {}
                public function parameter($key, $default = null) { return $this->scope; }
            });
            $middleware = new RequireCapability(new RequirePrincipal($resolver), $authorizer);
            if ($scope === 'other') {
                try { $middleware->handle($request, fn () => throw new RuntimeException('Handler ran'), 'reports.view'); self::fail('Wrong scope accepted.'); }
                catch (AccessDeniedHttpException) { self::assertTrue(true); }
            } else {
                $response = $middleware->handle($request, fn () => new Response('fixture success'), 'reports.view');
                self::assertSame('fixture success', $response->getContent());
                self::assertSame($principal, $request->attributes->get(RequirePrincipal::ATTRIBUTE));
            }
        }
        $this->expectException(AccessDeniedHttpException::class);
        $middleware->handle($request, fn () => throw new RuntimeException('Handler ran'), 'payments.card');
    }

    public function testResolverIsCalledAgainSoRevocationDoesNotReusePrincipal(): void
    {
        $resolver = new class($this->principal()) implements PrincipalResolver {
            public function __construct(public ?Principal $current) {}
            public function resolve(Request $request): ?Principal { return $this->current; }
        };
        $middleware = new RequirePrincipal($resolver);
        $request = Request::create('/api/v1/probe');
        self::assertSame(200, $middleware->handle($request, fn () => new Response)->getStatusCode());
        $resolver->current = null;
        $this->expectException(AuthenticationException::class);
        $middleware->handle($request, fn () => throw new RuntimeException('Handler ran'));
    }
}

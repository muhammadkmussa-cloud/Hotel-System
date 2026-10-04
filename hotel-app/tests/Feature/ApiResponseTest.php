<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\ApiExceptionResponse;
use App\Http\ApiResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApiResponseTest extends TestCase
{
    public function testSuccessAndErrorsShareOnlyTheServerRequestId(): void
    {
        $request = Request::create('/api/v1/probe', server: ['HTTP_X_REQUEST_ID' => 'attacker']);
        $success = ApiResponse::success($request, ['status' => 'ok'], 201, ['next' => null]);
        $error = ApiResponse::error($request, 422);
        self::assertSame($success->getData(true)['requestId'], $error->getData(true)['requestId']);
        self::assertSame($success->headers->get('X-Request-ID'), $success->getData(true)['requestId']);
        self::assertNotSame('attacker', $success->getData(true)['requestId']);
        self::assertSame(['status' => 'ok'], $success->getData(true)['data']);
        self::assertSame(['next' => null], $success->getData(true)['meta']);
        self::assertTrue($error->headers->hasCacheControlDirective('no-store'));
        self::assertNotSame(ApiResponse::requestId($request), ApiResponse::requestId(Request::create('/api/v1/probe')));
    }

    public function testExceptionDetailsAndUnapprovedHeadersAreNeverReflected(): void
    {
        $request = Request::create('/api/v1/probe');
        foreach ([new RuntimeException('secret SQL/path/token'), new HttpException(500, 'secret SQL/path/token', headers: ['Set-Cookie' => 'secret=token']), new HttpException(418, 'secret SQL/path/token')] as $error) {
            $response = ApiExceptionResponse::render($request, $error);
            self::assertSame(500, $response->getStatusCode());
            self::assertStringNotContainsString('secret', $response->getContent());
            self::assertFalse($response->headers->has('Set-Cookie'));
        }
        self::assertNull(ApiExceptionResponse::render(Request::create('/web'), new RuntimeException));
        self::assertSame('60', ApiResponse::error($request, 429, ['Retry-After' => 'secret'])->headers->get('Retry-After'));
        self::assertSame('15', ApiResponse::error($request, 429, ['Retry-After' => '15'])->headers->get('Retry-After'));
        self::assertSame(403, ApiResponse::error($request, 419)->getStatusCode());
    }
}

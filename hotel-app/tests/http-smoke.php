<?php

declare(strict_types=1);

// A real loopback HTTP server uses only temporary settings and private files.
$source = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/hotel-http-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
$server = null;

function copyHttpFixture(string $from, string $to): void
{
    mkdir($to, 0700, true);
    foreach (new DirectoryIterator($from) as $file) {
        if ($file->isDot() || $file->getFilename() === 'cache') {
            continue;
        }
        $destination = $to . '/' . $file->getFilename();
        $file->isDir() ? copyHttpFixture($file->getPathname(), $destination) : copy($file->getPathname(), $destination);
    }
}

function assertHttp(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function requestHttp(string $origin, string $path, string $method = 'GET', array $requestHeaders = [], ?string $requestBody = null): array
{
    $handle = curl_init($origin . $path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 3, CURLOPT_PROXY => '', CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $requestHeaders]);
    if ($requestBody !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, $requestBody);
    if ($method === 'HEAD') curl_setopt($handle, CURLOPT_NOBODY, true);
    $response = curl_exec($handle);
    if ($response === false) {
        curl_close($handle);
        throw new RuntimeException('Loopback HTTP request failed.');
    }
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    return [$status, substr($response, 0, $headerSize), substr($response, $headerSize)];
}

try {
    foreach (['bootstrap', 'config', 'routes', 'resources', 'public'] as $directory) {
        copyHttpFixture($source . '/' . $directory, $fixture . '/' . $directory);
    }
    // Fixture-only endpoint proves that the API group applies the configured prefix.
    file_put_contents($fixture . '/routes/api.php', "\n\\Illuminate\\Support\\Facades\\Route::get('/route-probe', fn () => response()->json(['fixture' => true]));\n", FILE_APPEND);
    file_put_contents($fixture . '/routes/api.php', <<<'PHP'

\Illuminate\Support\Facades\Route::post('/input-probe', function (\Illuminate\Http\Request $request, \App\Http\Requests\JsonInput $input) {
    return response()->json($input->validate($request, [
        'note' => ['required', 'string', 'max:20'],
        'items' => ['required', 'array', 'list', 'min:1', 'max:5'],
        'items.*.mealId' => ['required', 'string', 'max:36'],
    ]));
});

\Illuminate\Support\Facades\Route::get('/success-probe', function (\Illuminate\Http\Request $request) {
    return \App\Http\ApiResponse::success($request, ['status' => 'ok']);
});
\Illuminate\Support\Facades\Route::get('/exception-probe', function () {
    throw new \RuntimeException('secret-exception-marker SELECT private_token FROM payments');
});
\Illuminate\Support\Facades\Route::get('/renderable-probe', function () {
    throw new class extends \RuntimeException {
        public function render() { return response('secret-exception-marker', 200); }
        public function report() { error_log('secret-exception-marker'); }
    };
});

\Illuminate\Support\Facades\Route::get('/principal-probe', function () {
    file_put_contents(storage_path('protected-handler-ran'), 'unexpected');
    return response('unexpected');
})->middleware('principal');
\Illuminate\Support\Facades\Route::post('/capability-probe', function () {
    file_put_contents(storage_path('protected-handler-ran'), 'unexpected');
    return response('unexpected');
})->middleware('capability:payments.card');

\Illuminate\Support\Facades\Route::get('/csrf-probe', function (\Illuminate\Http\Request $request) {
    return \App\Http\ApiResponse::success($request, ['csrfToken' => $request->session()->token()]);
});
\Illuminate\Support\Facades\Route::match(['POST', 'PUT', 'PATCH', 'DELETE'], '/csrf-write-probe', function (\Illuminate\Http\Request $request) {
    file_put_contents(storage_path('csrf-handler-ran'), 'fixture');
    return \App\Http\ApiResponse::success($request, ['accepted' => true]);
});

\Illuminate\Support\Facades\Route::post('/login-limit-probe', fn (\Illuminate\Http\Request $request) => \App\Http\ApiResponse::success($request, ['fixture' => true]))->middleware('limit:login,email');

\Illuminate\Support\Facades\Route::patch('/version-probe', function (\Illuminate\Http\Request $request) {
    $version = \App\Support\ResourceVersion::fromIfMatch($request->header('If-Match'));
    if ($version !== 1) throw new \Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
    return \App\Http\ApiResponse::success($request, ['fixture' => true])->header('ETag', \App\Support\ResourceVersion::etag(2));
});
PHP, FILE_APPEND);
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/views', 'storage/framework/sessions'] as $directory) {
        mkdir($fixture . '/' . $directory, 0700, true);
    }
    symlink($source . '/vendor', $fixture . '/vendor');
    // Probe an available loopback port; never bind all network interfaces.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    assertHttp($socket !== false, 'Could not reserve loopback port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $origin = 'http://' . $address;
    $key = 'base64:' . base64_encode(random_bytes(32));
    $environment = "APP_ENV=local\nAPP_URL=$origin\nAPP_KEY=$key\nAPP_NAME=\"Hotel <script>alert(1)</script>\"\n";
    file_put_contents($fixture . '/.env', $environment);
    foreach (['composer.json', 'composer.lock', 'artisan'] as $file) {
        copy($source . '/' . $file, $fixture . '/' . $file);
    }
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $fixture . '/public', $source . '/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], [
        0 => ['pipe', 'r'], 1 => ['file', $fixture . '/server.log', 'a'], 2 => ['file', $fixture . '/server.log', 'a'],
    ], $pipes, $fixture . '/public', ['PATH' => getenv('PATH') ?: '/usr/bin:/bin']);
    assertHttp(is_resource($server), 'Could not launch loopback server.');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection !== false) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    assertHttp($ready, 'Loopback server did not become ready.');
    [$csrfStatus, $csrfHeaders, $csrfBody] = requestHttp($origin, '/api/v1/csrf-probe');
    assertHttp($csrfStatus === 200 && preg_match('/^Set-Cookie: (hotel_session=[^;]+)/mi', $csrfHeaders, $cookieMatch) === 1, 'Browser session was not established.');
    $sessionCookie = $cookieMatch[1];
    $csrfToken = json_decode($csrfBody, true)['data']['csrfToken'];
    $browserHeaders = ['Cookie: '.$sessionCookie, 'X-CSRF-TOKEN: '.$csrfToken];
    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        foreach ([[], ['Cookie: '.$sessionCookie], ['Cookie: '.$sessionCookie, 'X-CSRF-TOKEN: wrong'], ['X-CSRF-TOKEN: '.$csrfToken], ['Cookie: '.$sessionCookie, 'Sec-Fetch-Site: same-origin']] as $badHeaders) {
            [$status, , $body] = requestHttp($origin, '/api/v1/csrf-write-probe', $method, $badHeaders);
            assertHttp($status === 403 && json_decode($body, true)['error']['code'] === 'FORBIDDEN', 'CSRF guard accepted missing/invalid/unbound token.');
            assertHttp(! file_exists($fixture.'/storage/csrf-handler-ran'), 'Failed CSRF performed handler work.');
        }
    }
    [$otherStatus, $otherHeaders, $otherBody] = requestHttp($origin, '/api/v1/csrf-probe');
    $otherToken = json_decode($otherBody, true)['data']['csrfToken'];
    [$status] = requestHttp($origin, '/api/v1/csrf-write-probe', 'POST', ['Cookie: '.$sessionCookie, 'X-CSRF-TOKEN: '.$otherToken]);
    assertHttp($status === 403 && ! file_exists($fixture.'/storage/csrf-handler-ran'), 'Other session token was accepted.');
    [$status] = requestHttp($origin, '/api/v1/csrf-write-probe', 'POST', $browserHeaders);
    assertHttp($status === 200 && file_exists($fixture.'/storage/csrf-handler-ran'), 'Valid CSRF did not reach handler.');
    unlink($fixture.'/storage/csrf-handler-ran');
    assertHttp(str_contains(strtolower($csrfHeaders), 'httponly') && str_contains(strtolower($csrfHeaders), 'samesite=lax'), 'Browser cookie safeguards missing.');
    echo "PASS browser CSRF rejects all mutation methods before handler work and binds tokens to sessions\n";
    [$status, $headers, $body] = requestHttp($origin, '/');
    assertHttp($status === 200 && str_contains(strtolower($headers), 'content-type: text/html'), 'Home did not return HTML 200.');
    assertHttp(str_contains($body, '<main>') && str_contains($body, 'Ordering is not available yet.'), 'Home structure/status missing.');
    assertHttp(str_contains($body, '&lt;script&gt;') && ! str_contains($body, '<script>'), 'Installation name was not escaped.');
    assertHttp(! str_contains($body, $key), 'Page disclosed a private value.');
    echo "PASS HTML page and escaped installation name\n";
    foreach (['/assets/css/global.css' => 'text/css', '/assets/js/main.js' => 'javascript'] as $path => $mime) {
        [$status, $headers, $asset] = requestHttp($origin, $path);
        assertHttp($status === 200 && str_contains(strtolower($headers), $mime), 'Asset response has wrong status or MIME type.');
        assertHttp($asset === file_get_contents($source . '/public' . $path), 'Asset was not served as a static file.');
    }
    assertHttp(str_contains($body, 'type="module" src="/assets/js/main.js"'), 'Native module entry missing.');
    echo "PASS same-origin static CSS and JavaScript delivery\n";
    foreach (['/missing', '/.env', '/composer.json', '/composer.lock', '/artisan', '/bootstrap/app.php', '/storage/logs/laravel.log'] as $path) {
        [$status, , $body] = requestHttp($origin, $path);
        assertHttp(in_array($status, $path === '/missing' ? [404] : [403, 404], true) && ! str_contains($body, $key), "Missing/private path $path returned unexpected status $status.");
    }
    echo "PASS missing routes and private-file HTTP boundaries\n";
    $requestIds = [];
    foreach (['/api/v1', '/api/v1/', '/api/v1/missing/deep/path', '/api/v1/missing/%3Cscript%3E', '/api/v1/health/live', '/api/v1/health/ready'] as $path) {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            [$status, $headers, $body] = requestHttp($origin, $path, $method, ['Accept: text/html', 'X-Request-ID: untrusted-id']);
            $json = json_decode($body, true);
            assertHttp($status === 404 && str_contains(strtolower($headers), 'application/json'), 'Unknown API path did not return JSON 404.');
            assertHttp(str_contains(strtolower($headers), 'no-store'), 'API route error was cacheable.');
            assertHttp(is_array($json) && $json['error'] === ['code' => 'NOT_FOUND', 'message' => 'The requested resource was not found.', 'fields' => [], 'retryable' => false], 'Unexpected API error shape.');
            assertHttp(preg_match('/\Areq_[a-f0-9-]{36}\z/', $json['requestId']) === 1, 'Invalid server request ID.');
            assertHttp(! isset($requestIds[$json['requestId']]), 'Request ID was reused.');
            $requestIds[$json['requestId']] = true;
            assertHttp(! str_contains($body, $key) && ! str_contains($body, $fixture) && ! str_contains($body, 'untrusted-id'), 'API route error leaked private/untrusted data.');
        }
    }
    [$status, $headers, $body] = requestHttp($origin, '/api/v1/missing', 'HEAD');
    assertHttp($status === 404 && $body === '' && str_contains(strtolower($headers), 'application/json'), 'API HEAD response invalid.');
    [$status, , $body] = requestHttp($origin, '/api/v1/route-probe');
    assertHttp($status === 200 && json_decode($body, true) === ['fixture' => true], 'API prefix was not applied.');
    [$status, $headers, $body] = requestHttp($origin, '/api/v1/route-probe', 'POST', ['Accept: text/html']);
    assertHttp($status === 405 && str_contains(strtolower($headers), 'application/json') && str_contains(strtolower($headers), 'no-store') && str_contains(strtolower($headers), 'allow: get, head') && json_decode($body, true)['error']['code'] === 'METHOD_NOT_ALLOWED', 'API method error invalid.');
    foreach (['Accept: application/json', 'Accept: */*'] as $accept) {
        [$status, $headers, $body] = requestHttp($origin, '/api/v1/missing', 'GET', [$accept]);
        assertHttp($status === 404 && str_contains(strtolower($headers), 'application/json') && json_decode($body, true)['error']['code'] === 'NOT_FOUND', 'API error depended on content negotiation.');
    }
    foreach (['/api/v10/missing', '/route-probe'] as $path) {
        [$status, $headers] = requestHttp($origin, $path);
        assertHttp($status === 404 && str_contains(strtolower($headers), 'text/html'), 'API prefix captured a web path.');
    }
    echo "PASS API prefix, JSON route errors, method handling and web separation\n";
    foreach ([['GET', '/api/v1/principal-probe'], ['POST', '/api/v1/capability-probe?principal=owner&guestId=other']] as [$method, $path]) {
        [$status, , $body] = requestHttp($origin, $path, $method, [...$browserHeaders, 'Accept: text/html', 'Authorization: Bearer fake', 'X-Role: owner']);
        assertHttp($status === 401 && json_decode($body, true)['error']['code'] === 'UNAUTHENTICATED', 'Protected route did not reject missing principal.');
    }
    assertHttp(! file_exists($fixture.'/storage/protected-handler-ran'), 'Denied protected handler performed work.');
    echo "PASS registered authentication/capability guards deny before handler work\n";
    $validInput = '{"note":"  demo  ","items":[{"mealId":"fixture-meal"}]}';
    [$status, , $body] = requestHttp($origin, '/api/v1/input-probe?note=attacker&guestId=other', 'POST', [...$browserHeaders, 'Content-Type: application/json; charset=UTF-8'], $validInput);
    assertHttp($status === 200 && json_decode($body, true) === json_decode($validInput, true), 'Validated body was altered or merged with query inputs.');
    foreach ([
        [400, '{'], [400, '[]'], [400, '{"note":"a","note":"b"}'],
        [400, '{"note":1e999}'],
        [413, str_repeat('x', 65537)],
        [415, $validInput, 'text/plain'],
        [422, ''], [422, '{}'],
        [422, '{"note":"demo","items":[{"mealId":"fixture-meal","amountMinor":1}]}'],
        [422, '{"note":"demo","items":[{"mealId":"fixture-meal"}],"guestId":"secret-marker"}'],
    ] as $case) {
        [$expected, $payload] = $case;
        [$status, $headers, $body] = requestHttp($origin, '/api/v1/input-probe', 'POST', [...$browserHeaders, 'Accept: text/html', 'Content-Type: '.($case[2] ?? 'application/json')], $payload);
        $json = json_decode($body, true);
        assertHttp($status === $expected && str_contains(strtolower($headers), 'application/json') && str_contains(strtolower($headers), 'no-store'), 'Input error status/headers invalid: '.$expected.' got '.$status);
        assertHttp(isset($json['requestId']) && $json['error']['fields'] === [] && $json['error']['retryable'] === false, 'Input error envelope invalid.');
        assertHttp(! str_contains($body, 'secret-marker') && ! str_contains($body, $fixture) && ! str_contains($body, $key), 'Input response leaked values.');
    }
    foreach (['?_method=DELETE', '?_method[]=DELETE'] as $query) {
        [$status, , $body] = requestHttp($origin, '/api/v1/input-probe'.$query, 'POST', ['Content-Type: application/json'], $validInput);
        assertHttp($status === 400 && json_decode($body, true)['error']['code'] === 'MALFORMED_INPUT', 'Query method override bypassed the parser.');
    }
    echo "PASS bounded JSON, safe input errors, nested allowlists and query separation\n";
    [$status, $headers, $body] = requestHttp($origin, '/api/v1/success-probe', 'GET', ['X-Request-ID: attacker']);
    $json = json_decode($body, true);
    assertHttp($status === 200 && $json['data'] === ['status' => 'ok'] && str_contains($headers, $json['requestId']) && ! str_contains($body, 'attacker'), 'Success envelope/request ID invalid.');
    foreach ([false, true] as $debug) {
        file_put_contents($fixture . '/.env', $environment.'APP_DEBUG='.($debug ? 'true' : 'false')."\n");
        foreach (['/api/v1/exception-probe', '/api/v1/renderable-probe'] as $path) {
            [$status, $headers, $body] = requestHttp($origin, $path, 'GET', ['Accept: text/html']);
            $json = json_decode($body, true);
            assertHttp($status === 500 && $json['error']['code'] === 'INTERNAL_ERROR' && str_contains($headers, $json['requestId']), 'Exception was not safely enveloped.');
            assertHttp(! str_contains($body, 'secret-exception-marker') && ! str_contains($body, $fixture), 'Exception response disclosed details.');
        }
    }
    assertHttp(! str_contains(file_get_contents($fixture.'/server.log'), 'secret-exception-marker'), 'Exception log disclosed details.');
    echo "PASS success envelopes, server request IDs and debug-independent exception redaction\n";
    foreach ([[null, 428], ['W/"v1"', 400], ['"v2"', 412], ['"v1"', 200]] as [$tag, $expectedStatus]) {
        $versionHeaders = $browserHeaders;
        if ($tag !== null) $versionHeaders[] = 'If-Match: '.$tag;
        [$status, $headers, $body] = requestHttp($origin, '/api/v1/version-probe', 'PATCH', $versionHeaders);
        assertHttp($status === $expectedStatus && str_contains(strtolower($headers), 'application/json'), 'Version header HTTP contract failed.');
        if ($status === 200) assertHttp(str_contains(strtolower($headers), 'etag: "v2"'), 'Strong ETag missing.');
    }
    echo "PASS If-Match required/invalid/stale statuses and strong ETag\n";
    file_put_contents($fixture . '/.env', $environment."LOGIN_LIMIT_MAX=2\n");
    foreach ([' Fixture@Example.test ', 'fixture@example.test', 'FIXTURE@example.test'] as $index => $email) {
        [$status, $headers, $body] = requestHttp($origin, '/api/v1/login-limit-probe', 'POST', [...$browserHeaders, 'Content-Type: application/json'], json_encode(['email' => $email]));
        assertHttp($status === ($index < 2 ? 200 : 429), 'Login identity normalization/limit failed.');
        if ($index === 2) assertHttp(preg_match('/retry-after: [0-9]+/i', $headers) === 1 && json_decode($body, true)['error']['code'] === 'RATE_LIMITED', 'Throttle response contract failed.');
    }
    [$status] = requestHttp($origin, '/api/v1/login-limit-probe', 'POST', [...$browserHeaders, 'Content-Type: application/json'], '{"email":"different@example.test"}');
    assertHttp($status === 200, 'Shared IP locked out a different login identity.');
    [$freshStatus, $freshHeaders, $freshBody] = requestHttp($origin, '/api/v1/csrf-probe');
    preg_match('/^Set-Cookie: (hotel_session=[^;]+)/mi', $freshHeaders, $freshCookie);
    $freshBrowser = ['Cookie: '.$freshCookie[1], 'X-CSRF-TOKEN: '.json_decode($freshBody, true)['data']['csrfToken']];
    [$status] = requestHttp($origin, '/api/v1/login-limit-probe', 'POST', [...$freshBrowser, 'Content-Type: application/json'], '{"email":"fixture@example.test"}');
    assertHttp($status === 429, 'New session bypassed login account/IP limit.');
    file_put_contents($fixture . '/.env', $environment."REQUEST_LIMIT_MAX=2\n");
    [$freshStatus, $freshHeaders] = requestHttp($origin, '/api/v1/csrf-probe');
    preg_match('/^Set-Cookie: (hotel_session=[^;]+)/mi', $freshHeaders, $freshCookie);
    [$status] = requestHttp($origin, '/api/v1/route-probe', 'GET', ['Cookie: '.$freshCookie[1]]);
    assertHttp($status === 200, 'Fresh browser was prematurely limited.');
    [$status] = requestHttp($origin, '/api/v1/route-probe', 'GET', ['Cookie: '.$freshCookie[1]]);
    assertHttp($status === 429, 'Browser request limit was not enforced.');
    [$status] = requestHttp($origin, '/api/v1/route-probe');
    assertHttp($status === 200, 'Shared IP locked out a different browser session.');
    file_put_contents($fixture . '/.env', $environment);
    echo "PASS configurable request/login windows, shared-IP isolation and Retry-After\n";
    file_put_contents($fixture . '/.env', "APP_ENV=local\nAPP_DEBUG=true\n");
    [$status, $headers, $body] = requestHttp($origin, '/');
    assertHttp($status === 503 && str_contains(strtolower($headers), 'no-store'), 'Unconfigured HTTP response was not safe.');
    assertHttp(! str_contains($body, 'APP_KEY'), 'Public response disclosed diagnostic settings.');
    [$apiStatus, $apiHeaders, $apiBody] = requestHttp($origin, '/api/v1/health/live');
    assertHttp($apiStatus === 503 && json_decode($apiBody, true)['error']['code'] === 'UNAVAILABLE', 'Unconfigured API did not return JSON.');
    echo "PASS unconfigured HTTP response\n";
    file_put_contents($fixture . '/.env', 'APP_KEY="secret-marker');
    [$status, $headers, $body] = requestHttp($origin, '/');
    assertHttp($status === 503 && str_contains(strtolower($headers), 'no-store'), 'Malformed environment HTTP status/cache incorrect.');
    assertHttp(! str_contains($body, 'secret-marker') && ! str_contains($body, $fixture), 'Parser response leaked input or paths.');
    [$apiStatus, $apiHeaders, $apiBody] = requestHttp($origin, '/api/v1/health/live');
    assertHttp($apiStatus === 503 && json_decode($apiBody, true)['error']['code'] === 'UNAVAILABLE' && ! str_contains($apiBody, 'secret-marker'), 'Malformed API environment did not fail safely.');
    echo "PASS malformed environment HTTP redaction\n";
    file_put_contents($fixture . '/.env', $environment);
    [$status] = requestHttp($origin, '/');
    assertHttp($status === 200, 'Page did not recover after settings correction.');
    echo "PASS HTTP recovery after configuration correction\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($fixture);
}

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

function requestHttp(string $origin, string $path): array
{
    $handle = curl_init($origin . $path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 3, CURLOPT_PROXY => '']);
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
    file_put_contents($fixture . '/.env', "APP_ENV=local\nAPP_DEBUG=true\n");
    [$status, $headers, $body] = requestHttp($origin, '/');
    assertHttp($status === 503 && str_contains(strtolower($headers), 'no-store'), 'Unconfigured HTTP response was not safe.');
    assertHttp(! str_contains($body, 'APP_KEY'), 'Public response disclosed diagnostic settings.');
    echo "PASS unconfigured HTTP response\n";
    file_put_contents($fixture . '/.env', 'APP_KEY="secret-marker');
    [$status, $headers, $body] = requestHttp($origin, '/');
    assertHttp($status === 503 && str_contains(strtolower($headers), 'no-store'), 'Malformed environment HTTP status/cache incorrect.');
    assertHttp(! str_contains($body, 'secret-marker') && ! str_contains($body, $fixture), 'Parser response leaked input or paths.');
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

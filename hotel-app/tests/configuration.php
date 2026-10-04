<?php

declare(strict_types=1);

// Isolated process tests: never load or modify the installation's real .env/cache.
$source = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/hotel-config-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);

function copyTree(string $from, string $to): void
{
    mkdir($to, 0700, true);
    foreach (new DirectoryIterator($from) as $file) {
        if ($file->isDot() || $file->getFilename() === 'cache') {
            continue;
        }
        $destination = $to . '/' . $file->getFilename();
        $file->isDir() ? copyTree($file->getPathname(), $destination) : copy($file->getPathname(), $destination);
    }
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function runProcess(array $command, string $directory): array
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, [
        'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
    ]);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not launch test process.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

try {
    foreach (['bootstrap', 'config', 'routes'] as $directory) {
        copyTree($source . '/' . $directory, $fixture . '/' . $directory);
    }
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/views', 'storage/framework/sessions'] as $directory) {
        mkdir($fixture . '/' . $directory, 0700, true);
    }
    copy($source . '/artisan', $fixture . '/artisan');
    symlink($source . '/vendor', $fixture . '/vendor');
    file_put_contents($fixture . '/http.php', <<<'CHILD'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('http://127.0.0.1:8000/not-a-route');
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'body' => $response->getContent(), 'cache' => $response->headers->get('Cache-Control')], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
CHILD);
    $key = 'base64:' . base64_encode(random_bytes(32));
    $valid = "APP_ENV=local\nAPP_NAME=\"Hotel test\"\nAPP_URL=http://127.0.0.1:8000\nAPP_KEY=$key\n";
    $cases = [
        'private settings' => [$valid, true],
        'another owner domain' => [str_replace(['APP_ENV=local', 'http://127.0.0.1:8000'], ['APP_ENV=production', 'https://second-hotel.example'], $valid), true],
        'missing required values' => ["APP_ENV=local\n", false],
        'malformed key' => [str_replace($key, 'base64:secret-marker', $valid), false],
        'credentials in origin' => [str_replace('http://127.0.0.1:8000', 'https://owner:secret-marker@example.test', $valid), false],
        'production HTTP' => [str_replace(['APP_ENV=local', 'http://127.0.0.1:8000'], ['APP_ENV=production', 'http://hotel.example'], $valid), false],
        'local non-loopback HTTP' => [str_replace('127.0.0.1', 'hotel.example', $valid), false],
        'origin with path' => [str_replace(':8000', ':8000/path', $valid), false],
        'typed false origin' => [str_replace('http://127.0.0.1:8000', 'false', $valid), false],
        'invalid port' => [str_replace(':8000', ':99999', $valid), false],
        'query in origin' => [str_replace(':8000', ':8000?secret-marker', $valid), false],
        'fragment in origin' => [str_replace('http://127.0.0.1:8000', '"http://127.0.0.1:8000#secret-marker"', $valid), false],
        'invalid environment' => [str_replace('APP_ENV=local', 'APP_ENV=secret-marker', $valid), false],
    ];
    foreach ($cases as $name => [$environment, $isValid]) {
        file_put_contents($fixture . '/.env', $environment . "APP_DEBUG=true\n");
        [$exit, $output] = runProcess([PHP_BINARY, 'artisan', 'app:check-config', '--no-ansi'], $fixture);
        check($exit === ($isValid ? 0 : 1), "$name: unexpected CLI result");
        check(! str_contains($output, $key) && ! str_contains($output, 'secret-marker'), "$name: CLI disclosed a value");
        [$httpExit, $json] = runProcess([PHP_BINARY, 'http.php'], $fixture);
        $response = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        check($httpExit === 0 && $response['status'] === ($isValid ? 404 : 503), "$name: unexpected HTTP status");
        check(! str_contains($response['body'], $key) && ! str_contains($response['body'], 'secret-marker'), "$name: HTTP disclosed a value");
        if (! $isValid) {
            check(str_contains($response['cache'], 'no-store'), "$name: error may be cached");
            check(! str_contains($response['body'], 'APP_KEY'), "$name: public error disclosed setting names");
        }
        echo "PASS $name\n";
    }
    file_put_contents($fixture . '/.env', $valid);
    [$exit] = runProcess([PHP_BINARY, 'artisan', 'config:cache', '--no-ansi'], $fixture);
    check($exit === 0, 'Configuration cache creation failed');
    file_put_contents($fixture . '/.env', "APP_KEY=secret-marker\nAPP_URL=invalid\n");
    [$exit] = runProcess([PHP_BINARY, 'artisan', 'app:check-config', '--no-ansi'], $fixture);
    check($exit === 0, 'Cached configuration did not remain authoritative');
    [$exit] = runProcess([PHP_BINARY, 'artisan', 'config:clear', '--no-ansi'], $fixture);
    check($exit === 0, 'Configuration cache could not be cleared');
    [$exit] = runProcess([PHP_BINARY, 'artisan', 'app:check-config', '--no-ansi'], $fixture);
    check($exit === 1, 'Invalid settings accepted after clearing cache');
    echo "PASS configuration cache and recovery\n";

    file_put_contents($fixture . '/.env', 'APP_KEY="secret-marker');
    [$exit, $output] = runProcess([PHP_BINARY, 'artisan', 'app:check-config', '--no-ansi'], $fixture);
    check($exit === 1 && ! str_contains($output, 'secret-marker'), 'Malformed environment leaked parser input');
    echo "PASS malformed environment redaction\n";

    file_put_contents($fixture . '/.env', str_replace($key, '', $valid));
    [$exit] = runProcess([PHP_BINARY, 'artisan', 'key:generate', '--force', '--no-ansi'], $fixture);
    check($exit === 0, 'Private key generation blocked');
    [$exit] = runProcess([PHP_BINARY, 'artisan', 'app:check-config', '--no-ansi'], $fixture);
    check($exit === 0, 'Generated key not accepted');
    echo "PASS private key setup\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($fixture);
}

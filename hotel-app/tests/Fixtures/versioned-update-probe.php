<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

try {
    $app = require __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $db = $app['db']->connection('mysql');
    $schema = $db->getSchemaBuilder();
    $dropAll = static function () use ($db, $schema): void {
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($db->select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $row) {
            $schema->dropIfExists((string) $row->name);
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');
    };
    $id = '0199ac1a-0000-7000-8000-000000000001';
    $action = $argv[1];
    if ($action === 'empty') {
        echo json_encode(['empty' => $schema->getTableListing() === []]);
    } elseif ($action === 'initialize') {
        if ($schema->getTableListing() !== []) throw new RuntimeException;
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        if ($kernel->call('migrate', ['--path' => 'database/migrations/2026_10_04_000001_create_hotel_settings_table.php', '--force' => true], $output) !== 0) throw new RuntimeException;
        $db->table('hotel_settings')->insert(['id' => $id, 'name' => 'Initial fixture', 'timezone' => 'UTC', 'currency' => 'KES']);
        if ($kernel->call('app:migrate', [], $output) !== 0) throw new RuntimeException;
        echo json_encode(['initialized' => true]);
    } elseif ($action === 'inspect') {
        $row = $db->table('hotel_settings')->sole();
        echo json_encode(['name' => $row->name, 'version' => $row->resource_version]);
    } elseif ($action === 'cleanup') {
        $dropAll();
        echo json_encode(['cleaned' => true]);
    } else {
        try {
            $expected = App\Support\ResourceVersion::fromIfMatch($argv[2] === 'missing' ? null : $argv[2]);
            $work = fn () => App\Support\VersionedUpdate::apply($db, 'hotel_settings', $id, $expected, ['name' => $argv[3]]);
            if ($action === 'fail') {
                $db->transaction(function () use ($work): void { $work(); throw new RuntimeException('fixture failure'); });
            }
            $version = $work();
            echo json_encode(['status' => 200, 'version' => $version, 'etag' => App\Support\ResourceVersion::etag($version)]);
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
            echo json_encode(['status' => $error->getStatusCode()]);
        } catch (Throwable) {
            echo json_encode(['failed' => true]);
        }
    }
} catch (Throwable) {
    fwrite(STDERR, "Version fixture failed; private details withheld.\n");
    exit(1);
}

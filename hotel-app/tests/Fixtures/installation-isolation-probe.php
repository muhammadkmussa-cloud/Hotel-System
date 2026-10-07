<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

try {
    if (getenv('APP_ENV') !== 'testing') throw new RuntimeException;
    $app = require __DIR__ . '/bootstrap/app.php';
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
    $action = $argv[1];
    if ($action === 'empty') {
        echo json_encode(['empty' => $schema->getTableListing($db->getDatabaseName(), false) === []]);
    } elseif ($action === 'initialize') {
        if ($schema->getTableListing($db->getDatabaseName(), false) !== []) throw new RuntimeException;
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        if ($kernel->call('app:migrate', [], $output) !== 0) throw new RuntimeException;
        // Same identifier in distinct databases must never merge installation data.
        App\Models\HotelSettings::forceCreate([
            'id' => '0199ac1a-0000-7000-8000-000000000001',
            'name' => getenv('ISOLATION_LABEL'), 'timezone' => 'Africa/Nairobi',
        ]);
        echo json_encode(['initialized' => true]);
    } elseif ($action === 'read') {
        echo json_encode(['name' => App\Models\HotelSettings::sole()->name, 'count' => App\Models\HotelSettings::count()]);
    } elseif ($action === 'update') {
        App\Support\DatabaseTransaction::run($db, function (): void {
            App\Models\HotelSettings::sole()->update(['name' => 'Updated installation A']);
        });
        echo json_encode(['updated' => true]);
    } elseif (in_array($action, ['cross-read', 'cross-write'], true)) {
        $other = getenv('ISOLATION_OTHER_DATABASE');
        if (! is_string($other) || ! preg_match('/\A[A-Za-z0-9_]+\z/', $other)) throw new RuntimeException;
        try {
            if ($action === 'cross-read') {
                $db->select('SELECT name FROM `' . $other . '`.hotel_settings');
            } else {
                $db->update('UPDATE `' . $other . '`.hotel_settings SET name = ?', ['Forbidden cross-installation write']);
            }
            echo json_encode(['denied' => false]);
        } catch (Illuminate\Database\QueryException $error) {
            if (! in_array($error->errorInfo[1] ?? null, [1044, 1142], true)) throw $error;
            echo json_encode(['denied' => true]);
        }
    } elseif ($action === 'cleanup') {
        $dropAll();
        echo json_encode(['cleaned' => true]);
    } else {
        throw new RuntimeException;
    }
} catch (Throwable) {
    fwrite(STDERR, "Isolation fixture failed; private details withheld.\n");
    exit(1);
}

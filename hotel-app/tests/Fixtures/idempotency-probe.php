<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

try {
    $app = require __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $db = $app['db']->connection('mysql');
    $schema = $db->getSchemaBuilder();
    $action = $argv[1];
    if ($action === 'empty') {
        echo json_encode(['empty' => $schema->getTableListing() === []]);
    } elseif ($action === 'initialize') {
        if ($schema->getTableListing() !== []) throw new RuntimeException;
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        if ($kernel->call('app:migrate', [], $output) !== 0) throw new RuntimeException;
        $schema->create('idempotency_probe_effects', fn (Illuminate\Database\Schema\Blueprint $table) => $table->id());
        echo json_encode(['initialized' => true]);
    } elseif ($action === 'inspect') {
        echo json_encode(['effects' => $db->table('idempotency_probe_effects')->count(), 'commands' => $db->table('idempotent_commands')->count()]);
    } elseif ($action === 'damage-result') {
        $db->table('idempotent_commands')->where('identity_hash', hash('sha256', json_encode(['guest:fixture', 'order:fixture', 'fixture-command-0001'])))->update(['result_data' => null]);
        echo json_encode(['damaged' => true]);
    } elseif ($action === 'cleanup') {
        foreach (['idempotency_probe_effects', 'idempotent_commands', 'hotel_settings', 'migrations'] as $table) $schema->dropIfExists($table);
        echo json_encode(['cleaned' => true]);
    } else {
        try {
            $result = App\Support\IdempotentCommand::run($db, $argv[2], $argv[3], $argv[4], $argv[5], function ($connection) use ($action): App\Support\CommandResult {
                $id = $connection->table('idempotency_probe_effects')->insertGetId([]);
                usleep(100000);
                if ($action === 'fail') throw new RuntimeException('private-command-marker');
                if ($action === 'oversize') return new App\Support\CommandResult(['value' => str_repeat('x', 65537)]);
                return new App\Support\CommandResult(['effectId' => $id, 'ratio' => 1.0, 'items' => []], 201);
            });
            echo json_encode(['data' => $result->data, 'status' => $result->status, 'replayed' => $result->replayed], JSON_PRESERVE_ZERO_FRACTION);
        } catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
            echo json_encode(['conflict' => true]);
        } catch (Throwable) {
            echo json_encode(['failed' => true]);
        }
    }
} catch (Throwable) {
    fwrite(STDERR, "Idempotency fixture failed; details withheld.\n");
    exit(1);
}

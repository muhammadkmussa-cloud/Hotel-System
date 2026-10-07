<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

try {
    $app = require __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $app['config']->set('database.connections.demo_fixture', $app['config']->get('demo.connection'));
    $db = $app['db']->connection('demo_fixture');
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
        echo json_encode(['empty' => $schema->getTableListing() === []]);
    } elseif ($action === 'initialize') {
        if ($schema->getTableListing() !== []) throw new RuntimeException;
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        if ($kernel->call('migrate', ['--database' => 'demo_fixture', '--force' => true], $output) !== 0) throw new RuntimeException;
        $schema->create('demo_reset_guard', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('purpose', 32);
            $table->string('token', 64);
        });
        $db->table('demo_reset_guard')->insert(['id' => 1, 'purpose' => 'isolated-demo', 'token' => getenv('DEMO_RESET_TOKEN')]);
        $db->table('hotel_settings')->insert(['id' => (string) Illuminate\Support\Str::uuid7(), 'name' => 'Initial demo fixture', 'timezone' => 'UTC', 'currency' => 'KES']);
        $db->table('idempotent_commands')->insert(['identity_hash' => str_repeat('a', 64), 'body_hash' => str_repeat('b', 64), 'result_status' => 200, 'result_data' => '[]', 'created_at' => gmdate('Y-m-d H:i:s')]);
        echo json_encode(['initialized' => true]);
    } elseif ($action === 'inspect') {
        echo json_encode(['rows' => $db->table('hotel_settings')->get()->all(), 'history' => $db->table('migrations')->get()->all(), 'commands' => $db->table('idempotent_commands')->count()]);
    } elseif ($action === 'insert-blocker') {
        $db->table('hotel_settings')->update(['name' => 'Preserved demo fixture']);
        $db->table('idempotent_commands')->insert(['identity_hash' => str_repeat('a', 64), 'body_hash' => str_repeat('b', 64), 'result_status' => 200, 'result_data' => '[]', 'created_at' => gmdate('Y-m-d H:i:s')]);
        $db->unprepared("ALTER TABLE hotel_settings ADD CONSTRAINT demo_name_guard CHECK (name = 'Preserved demo fixture')");
        echo json_encode(['created' => true]);
    } elseif ($action === 'drop-blocker') {
        $db->unprepared('ALTER TABLE hotel_settings DROP CHECK demo_name_guard');
        echo json_encode(['dropped' => true]);
    } elseif ($action === 'extra-table') {
        $schema->create('unexpected_live_data', fn (Illuminate\Database\Schema\Blueprint $table) => $table->integer('id'));
        echo json_encode(['created' => true]);
    } elseif ($action === 'drop-extra') {
        $schema->drop('unexpected_live_data');
        echo json_encode(['dropped' => true]);
    } elseif ($action === 'missing-marker') {
        $db->table('demo_reset_guard')->delete();
        echo json_encode(['removed' => true]);
    } elseif ($action === 'cleanup') {
        $dropAll();
        echo json_encode(['cleaned' => true]);
    } else {
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $options = ['--no-interaction' => true];
        if ($action !== 'no-confirmation') $options['--confirm-database'] = getenv('DEMO_DB_DATABASE');
        $status = $kernel->call('app:demo-reset', $options, $output);
        echo json_encode(['status' => $status, 'output' => $output->fetch()]);
    }
} catch (Throwable) {
    fwrite(STDERR, "Demo fixture failed; private details withheld.\n");
    exit(1);
}

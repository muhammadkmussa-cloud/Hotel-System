<?php

declare(strict_types=1);

// Copied into a temporary application by HotelSettingsTest; never run on an installation.
require __DIR__ . '/vendor/autoload.php';

try {
    $prefix = getenv('SETTINGS_TEST_PREFIX');
    if (getenv('APP_ENV') !== 'testing' || ! is_string($prefix) || ! preg_match('/\Ahs_[a-f0-9]{24}_\z/', $prefix)) {
        throw new RuntimeException;
    }
    $app = require __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $app['config']->set('database.connections.mysql.prefix', $prefix);
    $schema = $app['db']->connection('mysql')->getSchemaBuilder();
    $action = $argv[1];
    if ($action === 'migrate') {
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $status = $kernel->call('app:migrate', ['--no-interaction' => true], $output);
        echo json_encode(['status' => $status]);
    } elseif ($action === 'compete') {
        $worker = $argv[2];
        if (! in_array($worker, ['a', 'b'], true)) throw new RuntimeException;
        file_put_contents(__DIR__ . '/ready-' . $worker, 'ready');
        $deadline = microtime(true) + 10;
        while (! is_file(__DIR__ . '/release')) {
            if (microtime(true) > $deadline) throw new RuntimeException;
            usleep(10000);
        }
        try {
            App\Models\HotelSettings::create(['name' => 'Demo hotel ' . $worker, 'timezone' => 'Africa/Nairobi']);
            echo json_encode(['result' => 'created']);
        } catch (Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== 1062) throw $error;
            echo json_encode(['result' => 'duplicate']);
        }
    } elseif ($action === 'inspect') {
        $record = App\Models\HotelSettings::sole();
        echo json_encode(['count' => App\Models\HotelSettings::count(), 'row' => $record->toArray()]);
    } elseif ($action === 'update') {
        $record = App\Models\HotelSettings::sole();
        $record->update(['name' => 'Updated demo hotel', 'business_day_cutoff' => '04:00:00', 'fiscal_configuration_version' => 1]);
        echo json_encode(['updated' => true]);
    } elseif ($action === 'bypass') {
        try {
            $app['db']->table('hotel_settings')->update(['installation_slot' => 2]);
            echo json_encode(['blocked' => false]);
        } catch (Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== 3105) throw $error;
            echo json_encode(['blocked' => true]);
        }
    } elseif ($action === 'insert') {
        try {
            $app['db']->table('hotel_settings')->insert(['id' => (string) Illuminate\Support\Str::uuid(), 'name' => 'Another demo hotel', 'timezone' => 'UTC']);
            echo json_encode(['blocked' => false]);
        } catch (Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== 1062) throw $error;
            echo json_encode(['blocked' => true]);
        }
    } elseif ($action === 'storage') {
        $connection = $app['db']->connection('mysql');
        $table = $connection->selectOne(
            'SELECT ENGINE AS engine, TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$prefix . 'hotel_settings'],
        );
        $session = $connection->selectOne('SELECT @@character_set_client AS client, @@character_set_connection AS connection_charset, @@character_set_results AS results, @@collation_connection AS collation');
        echo json_encode(['table' => $table, 'session' => $session]);
    } elseif ($action === 'commit-text') {
        $app['db']->connection('mysql')->transaction(function (): void {
            App\Models\HotelSettings::sole()->update(['name' => 'Karibu café — مرحباً — 欢迎 — 🍲']);
        });
        echo json_encode(['committed' => true]);
    } elseif ($action === 'rollback-exception') {
        $connection = $app['db']->connection('mysql');
        $failure = new RuntimeException('Intentional fixture failure');
        try {
            App\Support\DatabaseTransaction::run($connection, function () use ($failure): void {
                $record = App\Models\HotelSettings::sole();
                $record->update(['name' => 'Must not persist']);
                $record->delete();
                App\Models\HotelSettings::create(['name' => 'Temporary replacement', 'timezone' => 'UTC']);
                throw $failure;
            });
            throw new LogicException('Expected transaction failure');
        } catch (RuntimeException $error) {
            if ($error !== $failure) throw $error;
        }
        echo json_encode(['rolled_back' => true, 'level' => $connection->transactionLevel()]);
    } elseif ($action === 'rollback-constraint') {
        $connection = $app['db']->connection('mysql');
        try {
            App\Support\DatabaseTransaction::run($connection, function (): void {
                App\Models\HotelSettings::sole()->update(['name' => 'Must not persist either']);
                App\Models\HotelSettings::create(['name' => 'Duplicate identity', 'timezone' => 'UTC']);
            });
            throw new LogicException('Expected constraint failure');
        } catch (Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== 1062) throw $error;
        }
        echo json_encode(['rolled_back' => true, 'level' => $connection->transactionLevel()]);
    } elseif ($action === 'clock-create') {
        Carbon\CarbonImmutable::setTestNow(new Carbon\CarbonImmutable('2026-10-04T15:30:00+03:00'));
        $record = $app['db']->connection('mysql')->transaction(function () {
            App\Models\HotelSettings::sole()->delete();
            return App\Models\HotelSettings::create(['name' => 'Clock fixture', 'timezone' => 'Africa/Nairobi']);
        });
        echo json_encode(['id' => $record->getKey()]);
    } elseif ($action === 'clock-update') {
        Carbon\CarbonImmutable::setTestNow(new Carbon\CarbonImmutable('2026-10-04T16:30:00+03:00'));
        App\Models\HotelSettings::sole()->update(['name' => 'Clock fixture updated']);
        echo json_encode(['updated' => true]);
    } elseif ($action === 'raw-clock') {
        $connection = $app['db']->connection('mysql');
        echo json_encode([
            'row' => $connection->table('hotel_settings')->sole(),
            'timezone' => $connection->selectOne('SELECT @@session.time_zone AS zone')->zone,
        ]);
    } elseif ($action === 'retry-setup') {
        $schema->create('retry_probe', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('value');
        });
        $app['db']->table('retry_probe')->insert([['id' => 1, 'value' => 0], ['id' => 2, 'value' => 0]]);
        echo json_encode(['ready' => true]);
    } elseif ($action === 'deadlock-worker') {
        $worker = $argv[2];
        if (! in_array($worker, ['a', 'b'], true)) throw new RuntimeException;
        $connection = $app['db']->connection('mysql');
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 5');
        $attempts = 0;
        $result = App\Support\DatabaseTransaction::run($connection, function () use ($connection, $worker, &$attempts): string {
            $attempts++;
            $first = $worker === 'a' ? 1 : 2;
            $connection->table('retry_probe')->where('id', $first)->increment('value');
            if ($attempts === 1) {
                file_put_contents(__DIR__ . '/locked-' . $worker, 'ready');
                $other = $worker === 'a' ? 'b' : 'a';
                $deadline = microtime(true) + 10;
                while (! is_file(__DIR__ . '/locked-' . $other)) {
                    if (microtime(true) >= $deadline) throw new RuntimeException;
                    usleep(10000);
                }
            }
            $connection->table('retry_probe')->where('id', 3 - $first)->increment('value');
            return 'committed';
        });
        echo json_encode(['result' => $result, 'attempts' => $attempts, 'level' => $connection->transactionLevel()]);
    } elseif ($action === 'retry-inspect') {
        echo json_encode(['values' => $app['db']->table('retry_probe')->orderBy('id')->pluck('value')->all()]);
    } elseif (in_array($action, ['retry-exhaust', 'retry-timeout'], true)) {
        $connection = $app['db']->connection('mysql');
        $attempts = 0;
        try {
            App\Support\DatabaseTransaction::run($connection, function () use ($connection, $action, &$attempts): void {
                $attempts++;
                $connection->table('retry_probe')->where('id', 1)->increment('value');
                // Inject exact server diagnostics to deterministically test the retry limit/classifier.
                $sql = $action === 'retry-exhaust'
                    ? "SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = 'fixture deadlock'"
                    : "SIGNAL SQLSTATE 'HY000' SET MYSQL_ERRNO = 1205, MESSAGE_TEXT = 'fixture timeout'";
                $connection->statement($sql);
            });
            throw new LogicException('Expected failure');
        } catch (Illuminate\Database\QueryException $error) {
            $expected = $action === 'retry-exhaust' ? 1213 : 1205;
            if (($error->errorInfo[1] ?? null) !== $expected) throw $error;
        }
        echo json_encode(['attempts' => $attempts, 'level' => $connection->transactionLevel()]);
    } elseif ($action === 'retry-nested') {
        $connection = $app['db']->connection('mysql');
        $called = false;
        $connection->beginTransaction();
        try {
            try {
                App\Support\DatabaseTransaction::run($connection, function () use (&$called): void { $called = true; });
                throw new RuntimeException('Nested execution accepted');
            } catch (LogicException) {
                echo json_encode(['called' => $called, 'level' => $connection->transactionLevel()]);
            }
        } finally {
            $connection->rollBack();
        }
    } elseif ($action === 'cleanup') {
        $schema->dropIfExists('retry_probe');
        $schema->dropIfExists('hotel_settings');
        $schema->dropIfExists('migrations');
        echo json_encode(['cleaned' => true]);
    } else {
        throw new RuntimeException;
    }
} catch (Throwable) {
    fwrite(STDERR, "Settings fixture failed; private details withheld.\n");
    exit(1);
}

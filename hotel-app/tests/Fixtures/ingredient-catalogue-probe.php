<?php

declare(strict_types=1);

/**
 * P09 fixture probe for the reusable ingredient catalogue.
 *
 * Actions are driven by tests/Database/IngredientCatalogueTest.php against a
 * disposable MySQL schema. Domain conflicts are returned as {error, status}
 * rather than thrown so the test can assert them.
 */

require __DIR__.'/vendor/autoload.php';

try {
    $app = require __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $app['config']->set('database.migrations.table', 'probe_migrations');
    $db = $app['db']->connection('mysql');
    $schema = $db->getSchemaBuilder();
    $action = $argv[1] ?? 'inspect';

    $dropAll = static function () use ($db, $schema): void {
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($db->select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $row) {
            $schema->dropIfExists((string) $row->name);
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');
    };

    if ($action === 'migrate') {
        $dropAll();
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $status = $kernel->call('app:migrate', ['--no-interaction' => true], $output);
        echo json_encode(['status' => $status]);
        exit(0);
    }

    if ($action === 'cleanup') {
        $dropAll();
        echo json_encode(['cleaned' => true]);
        exit(0);
    }

    $catalogue = $app->make(App\Domain\Catalogue\IngredientCatalogue::class);
    $actor = '01990000-0000-7000-8000-0000000000aa';

    $guard = static function (callable $work): array {
        try {
            return ['ok' => $work()];
        } catch (App\Domain\DomainError $error) {
            return ['error' => $error->errorCode, 'status' => $error->status];
        } catch (Throwable $error) {
            return ['error' => 'throwable', 'message' => $error->getMessage()];
        }
    };

    if ($action === 'create') {
        $result = $guard(fn () => $catalogue->create([
            'name' => (string) (getenv('NAME') ?: ''),
            'description' => getenv('DESCRIPTION') ?: null,
            'media_id' => getenv('MEDIA_ID') ?: null,
        ], $actor));
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'update') {
        $result = $guard(fn () => $catalogue->update(
            (string) (getenv('ID') ?: ''),
            (int) (getenv('VERSION') ?: 1),
            ['name' => (string) (getenv('NAME') ?: ''), 'description' => null],
            $actor,
        ));
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'archive' || $action === 'restore') {
        $result = $guard(function () use ($catalogue, $action, $actor) {
            $catalogue->setActive((string) (getenv('ID') ?: ''), $action === 'restore', $actor);

            return true;
        });
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'components') {
        $ids = array_values(array_filter(explode(',', (string) (getenv('CHILD_IDS') ?: '')), static fn ($v) => $v !== ''));
        $result = $guard(fn () => $catalogue->setComponents((string) (getenv('ID') ?: ''), $ids, $actor));
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'state') {
        $row = $db->table('ingredients')->where('id', (string) (getenv('ID') ?: ''))->first(['id', 'name', 'active', 'version', 'media_id']);
        echo json_encode($row === null ? ['found' => false] : [
            'found' => true, 'name' => $row->name, 'active' => (int) $row->active,
            'version' => (int) $row->version, 'media_id' => $row->media_id,
            'versions' => $db->table('ingredient_versions')->where('ingredient_id', $row->id)->count(),
        ]);
        exit(0);
    }

    if ($action === 'components-list') {
        echo json_encode(['components' => $db->table('ingredient_components')
            ->where('parent_ingredient_id', (string) (getenv('ID') ?: ''))
            ->pluck('child_ingredient_id')->all()]);
        exit(0);
    }

    if ($action === 'media-create') {
        $id = (string) Illuminate\Support\Str::uuid7();
        $db->table('media')->insert([
            'id' => $id, 'kind' => 'ingredient', 'publication_state' => 'draft',
            'original_sha256' => str_repeat('a', 64), 'version' => 1,
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        echo json_encode(['id' => $id]);
        exit(0);
    }

    if ($action === 'media-count') {
        echo json_encode(['count' => $db->table('media')->count()]);
        exit(0);
    }

    fwrite(STDERR, "Unknown ingredient fixture action.\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage()."\n");
    exit(1);
}

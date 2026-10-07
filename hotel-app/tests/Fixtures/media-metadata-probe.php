<?php

declare(strict_types=1);

/**
 * P08.01 — MediaMetadata probe. See MediaMetadataTest.
 */

use App\Support\MediaMetadata;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
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
$media = app(MediaMetadata::class);

$action = $argv[1] ?? 'empty';

$out = static function (array $data): void {
    fwrite(STDOUT, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    exit(0);
};

try {
    switch ($action) {
        case 'empty':
            $out(['empty' => $schema->getTableListing() === []]);
            break;

        case 'initialize':
            if ($schema->getTableListing() !== []) {
                throw new RuntimeException('Schema must be empty before initialize.');
            }
            $output = new Symfony\Component\Console\Output\BufferedOutput();
            if ($kernel->call('migrate', ['--path' => 'database/migrations/2026_10_05_000020_create_media_table.php', '--force' => true], $output) !== 0) {
                throw new RuntimeException('Migration failed: '.$output->fetch());
            }
            $out(['initialized' => $schema->hasTable('media')]);
            break;

        case 'create':
            $kind = $argv[2] ?? 'meal';
            $result = $media->create($kind, 'staff-actor-0001');
            $out($result);
            break;

        case 'inspect':
            $id = $argv[2] ?? null;
            if ($id !== null) {
                $row = $db->table('media')->where('id', $id)->first();
                $out($row === null ? ['missing' => true] : [
                    'id' => $row->id,
                    'kind' => $row->kind,
                    'publication_state' => $row->publication_state,
                    'version' => (int) $row->version,
                    'alt_text' => $row->alt_text,
                    'label' => $row->label,
                    'focal_x' => (int) $row->focal_x,
                    'focal_y' => (int) $row->focal_y,
                    'original_sha256' => $row->original_sha256,
                ]);
            }
            $out(['count' => $db->table('media')->count()]);
            break;

        case 'edit':
            $id = $argv[2];
            $version = (int) ($argv[3] ?? 1);
            $result = $media->updateMetadata($id, 'staff-actor-0001', $version, [
                'alt_text' => 'Plated beef stew with ugali and greens.',
                'label' => 'Beef stew',
                'rights_owner' => 'Hotel Kitchen',
                'focal_x' => 4800,
                'focal_y' => 5200,
                'crop_x' => 200,
                'crop_y' => 200,
                'crop_width' => 9600,
                'crop_height' => 9600,
            ]);
            $out($result);
            break;

        case 'edit-stale':
            $id = $argv[2];
            $result = $media->updateMetadata($id, 'staff-actor-0001', 999, [
                'alt_text' => 'Stale edit attempt.',
                'focal_x' => 5000,
                'focal_y' => 5000,
            ]);
            $out($result);
            break;

        case 'edit-protected':
            $id = $argv[2];
            $version = (int) ($argv[3] ?? 1);
            $result = $media->updateMetadata($id, 'staff-actor-0001', $version, [
                'alt_text' => 'Trying to spoof bytes.',
                'focal_x' => 5000,
                'focal_y' => 5000,
                'original_sha256' => str_repeat('0', 64),
            ]);
            $out($result);
            break;

        case 'publish-no-alt':
            $id = $argv[2];
            $version = (int) ($argv[3] ?? 1);
            $db->table('media')->where('id', $id)->update(['alt_text' => null, 'version' => $version]);
            $result = $media->transitionState($id, 'staff-actor-0001', $version, 'published');
            $out($result);
            break;

        case 'publish':
            $id = $argv[2];
            $version = (int) ($argv[3] ?? 1);
            $result = $media->transitionState($id, 'staff-actor-0001', $version, 'published');
            $out($result);
            break;

        case 'archive':
            $id = $argv[2];
            $version = (int) ($argv[3] ?? 1);
            $result = $media->transitionState($id, 'staff-actor-0001', $version, 'archived');
            $out($result);
            break;

        case 'cleanup':
            $dropAll();
            $out(['cleaned' => ! $schema->hasTable('media')]);
            break;

        default:
            $out(['error' => 'unknown-action', 'action' => $action]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage()."\n");
    exit(1);
}

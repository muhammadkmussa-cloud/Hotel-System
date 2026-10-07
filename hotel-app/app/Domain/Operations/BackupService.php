<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Authenticated logical backups for shared hosting. The manifest is signed
 * with the installation APP_KEY (kept outside the archive), every entry is
 * allowlisted and bounded, and restore never trusts archive-provided paths.
 */
final class BackupService
{
    private const FORMAT = 2;
    private const MAX_MANIFEST_BYTES = 1_048_576;
    private const MAX_TABLE_BYTES = 67_108_864;
    private const MAX_MEDIA_BYTES = 25_165_824;
    private const MAX_EXPANDED_BYTES = 1_073_741_824;
    private const MAX_ENTRIES = 10_000;

    public function directory(): string
    {
        $dir = storage_path('app/private/backups');
        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create the private backup directory.');
        }

        return $dir;
    }

    /** @return list<string> */
    public function tables(): array
    {
        $tables = array_map(static fn ($t) => is_array($t) ? $t['name'] : $t->name, Schema::getTables());
        $tables = array_values(array_filter($tables, static fn ($t) => ! in_array($t, ['sqlite_sequence', 'cache', 'cache_locks', 'sessions'], true)));
        sort($tables, SORT_STRING);

        return $tables;
    }

    public function create(?string $label = null): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The zip extension is required for backups.');
        }
        $name = 'hotel-'.gmdate('Ymd-His').($label ? '-'.preg_replace('/[^a-z0-9\-]/', '', strtolower($label)) : '').'.zip';
        $path = $this->directory().'/'.$name;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        $temporary = [];
        try {
            $manifest = [
                'format' => self::FORMAT,
                'created_at' => gmdate('c'),
                'driver' => DB::connection()->getDriverName(),
                'tables' => [],
                'files' => [],
            ];
            $expanded = 0;
            $connection = DB::connection();
            if ($connection->getDriverName() === 'mysql') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $connection->transaction(function () use ($zip, &$manifest, &$expanded, &$temporary): void {
                // Every table cursor shares one repeatable-read transaction, so
                // relational rows come from one logical database snapshot.
                $this->snapshotTables($zip, $manifest, $expanded, $temporary);
            });

            $media = storage_path('app/private/media/originals');
            if (is_dir($media)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($media, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if (! $file->isFile() || $file->isLink()) {
                        throw new RuntimeException('Backup media must contain regular files only.');
                    }
                    $rel = 'media/originals/'.str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($media) + 1));
                    if (! self::isSafeMediaPath($rel)) {
                        throw new RuntimeException('Backup media path is not safe.');
                    }
                    $bytes = $file->getSize();
                    if ($bytes < 0 || $bytes > self::MAX_MEDIA_BYTES) {
                        throw new RuntimeException('A media original exceeds the safe backup entry limit.');
                    }
                    $expanded += $bytes;
                    $this->guardExpandedSize($expanded);
                    if (! $zip->addFile($file->getPathname(), $rel)) {
                        throw new RuntimeException('Could not add a media original to the backup.');
                    }
                    $sha = hash_file('sha256', $file->getPathname());
                    if (! is_string($sha)) {
                        throw new RuntimeException('Could not checksum a media original.');
                    }
                    $manifest['files'][$rel] = ['bytes' => $bytes, 'sha256' => $sha];
                }
            }

            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($manifestJson) > self::MAX_MANIFEST_BYTES) {
                throw new RuntimeException('Backup manifest exceeds the safe size limit.');
            }
            $mac = self::manifestMac($manifestJson, $this->authenticationKey());
            if (! $zip->addFromString('manifest.json', $manifestJson) || ! $zip->addFromString('manifest.hmac', $mac)) {
                throw new RuntimeException('Could not finalise the authenticated backup manifest.');
            }
            if (! $zip->close()) {
                throw new RuntimeException('Could not close the backup archive.');
            }
        } catch (Throwable $error) {
            $zip->close();
            foreach ($temporary as $file) @unlink($file);
            @unlink($path);
            throw $error;
        }
        foreach ($temporary as $file) @unlink($file);

        return $path;
    }

    /** @param array<string,mixed> $manifest @param list<string> $temporary */
    private function snapshotTables(ZipArchive $zip, array &$manifest, int &$expanded, array &$temporary): void
    {
        foreach ($this->tables() as $table) {
            if (! self::isSafeTableName($table)) throw new RuntimeException('Database contains an unsupported table name.');
            $file = tempnam($this->directory(), '.table-');
            $out = is_string($file) ? fopen($file, 'wb') : false;
            if (! is_string($file) || ! is_resource($out)) {
                if (is_string($file)) @unlink($file);
                throw new RuntimeException('Could not create a streamed backup entry.');
            }
            $temporary[] = $file;
            $hash = hash_init('sha256');
            $bytes = 0;
            $count = 0;
            try {
                foreach (DB::table($table)->cursor() as $row) {
                    $line = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
                    $bytes += strlen($line);
                    if ($bytes > self::MAX_TABLE_BYTES || fwrite($out, $line) !== strlen($line)) {
                        throw new RuntimeException('Table '.$table.' exceeds the safe logical-backup entry limit.');
                    }
                    hash_update($hash, $line);
                    $count++;
                }
            } finally {
                fclose($out);
            }
            $expanded += $bytes;
            $this->guardExpandedSize($expanded);
            if (! $zip->addFile($file, 'tables/'.$table.'.jsonl')) throw new RuntimeException('Could not add a database table to the backup.');
            $manifest['tables'][$table] = ['rows' => $count, 'bytes' => $bytes, 'sha256' => hash_final($hash)];
        }
    }

    /** @return array{ok:bool,problems:list<string>,tables:int,rows:int} */
    public function verify(string $path): array
    {
        $problems = [];
        $tables = 0;
        $rows = 0;
        $zip = new ZipArchive();
        $archiveBytes = is_file($path) ? filesize($path) : false;
        if (! is_int($archiveBytes) || $archiveBytes < 1 || $archiveBytes > self::MAX_EXPANDED_BYTES || $zip->open($path) !== true) {
            return self::failed('Archive cannot be opened or exceeds the safe size limit');
        }

        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_ENTRIES) {
                return self::failed('Archive entry count is outside the safe limit');
            }
            $manifestStat = $zip->statName('manifest.json');
            $macStat = $zip->statName('manifest.hmac');
            if (! is_array($manifestStat) || ! is_array($macStat)
                || (int) $manifestStat['size'] > self::MAX_MANIFEST_BYTES || (int) $macStat['size'] !== 64) {
                return self::failed('Authenticated manifest is missing or invalid');
            }
            $manifestJson = $zip->getFromName('manifest.json');
            $givenMac = $zip->getFromName('manifest.hmac');
            if (! is_string($manifestJson) || ! is_string($givenMac)
                || ! hash_equals(self::manifestMac($manifestJson, $this->authenticationKey()), strtolower($givenMac))) {
                return self::failed('Backup authentication failed');
            }
            try {
                $manifest = json_decode($manifestJson, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return self::failed('Manifest JSON is invalid');
            }
            if (! $this->validManifestShape($manifest)) {
                return self::failed('Manifest is missing, unsupported, or unsafe');
            }

            $expectedEntries = ['manifest.json' => true, 'manifest.hmac' => true];
            $expanded = strlen($manifestJson) + 64;
            foreach ($manifest['tables'] as $table => $meta) {
                $entry = 'tables/'.$table.'.jsonl';
                $expectedEntries[$entry] = true;
                $stat = $zip->statName($entry);
                if (! is_array($stat) || (int) $stat['size'] !== $meta['bytes'] || (int) $stat['size'] > self::MAX_TABLE_BYTES) {
                    $problems[] = 'Invalid size for table '.$table;
                    continue;
                }
                $expanded += (int) $stat['size'];
                [$sha, $actualRows, $validJson] = $this->hashTableEntry($zip, $entry);
                if (! $validJson || ! hash_equals($meta['sha256'], $sha) || $actualRows !== $meta['rows']) {
                    $problems[] = 'Content mismatch for table '.$table;
                }
                $rows += $meta['rows'];
                $tables++;
            }
            foreach ($manifest['files'] as $rel => $meta) {
                $expectedEntries[$rel] = true;
                $stat = $zip->statName($rel);
                if (! is_array($stat) || (int) $stat['size'] !== $meta['bytes'] || (int) $stat['size'] > self::MAX_MEDIA_BYTES) {
                    $problems[] = 'Invalid size for media file';
                    continue;
                }
                $expanded += (int) $stat['size'];
                $sha = $this->hashEntry($zip, $rel);
                if ($sha === null || ! hash_equals($meta['sha256'], $sha)) {
                    $problems[] = 'Content mismatch for media file';
                }
            }
            if ($expanded > self::MAX_EXPANDED_BYTES) {
                $problems[] = 'Archive expands beyond the safe limit';
            }

            $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || isset($seen[$name]) || ! isset($expectedEntries[$name])) {
                    $problems[] = 'Archive contains duplicate or unexpected entries';
                    break;
                }
                $seen[$name] = true;
            }
            if (count($seen) !== count($expectedEntries)) {
                $problems[] = 'Archive entry set does not match the manifest';
            }
        } catch (Throwable) {
            $problems[] = 'Backup verification failed safely';
        } finally {
            $zip->close();
        }

        return ['ok' => $problems === [], 'problems' => array_values(array_unique($problems)), 'tables' => $tables, 'rows' => $rows];
    }

    /** Replace all data with authenticated archive contents. Verify first. */
    public function restore(string $path): array
    {
        $check = $this->verify($path);
        if (! $check['ok']) {
            throw new RuntimeException('Backup failed verification: '.implode('; ', $check['problems']));
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Backup cannot be reopened for restore.');
        }
        try {
            // Re-authenticate and re-hash the exact open archive handle used
            // for restore, closing the verify/reopen replacement race.
            $manifest = $this->authenticatedManifestForRestore($zip);
            $current = $this->tables();
            $archived = array_keys($manifest['tables']);
            sort($archived, SORT_STRING);
            if ($manifest['driver'] !== DB::connection()->getDriverName() || $archived !== $current) {
                throw new RuntimeException('Backup schema/driver does not exactly match this migrated installation.');
            }

            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($zip, $manifest): void {
                    foreach (array_keys($manifest['tables']) as $table) {
                        DB::table($table)->delete();
                        $generated = $this->generatedColumns($table);
                        $stream = $zip->getStream('tables/'.$table.'.jsonl');
                        if (! is_resource($stream)) {
                            throw new RuntimeException('A verified table entry cannot be read.');
                        }
                        try {
                            $batch = [];
                            while (($line = fgets($stream)) !== false) {
                                if ($line === "\n" || $line === '') {
                                    continue;
                                }
                                $row = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
                                if (! is_array($row)) {
                                    throw new RuntimeException('A table row is not an object.');
                                }
                                foreach ($generated as $column) {
                                    unset($row[$column]);
                                }
                                $batch[] = $row;
                                if (count($batch) >= 200) {
                                    DB::table($table)->insert($batch);
                                    $batch = [];
                                }
                            }
                            if ($batch !== []) {
                                DB::table($table)->insert($batch);
                            }
                        } finally {
                            fclose($stream);
                        }
                    }
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            $base = storage_path('app/private/media/originals');
            if (! is_dir($base) && ! mkdir($base, 0750, true) && ! is_dir($base)) {
                throw new RuntimeException('Could not create the private media restore directory.');
            }
            $baseReal = realpath($base);
            if (! is_string($baseReal)) {
                throw new RuntimeException('Private media restore directory is unavailable.');
            }
            foreach ($manifest['files'] as $rel => $meta) {
                if (! self::isSafeMediaPath($rel)) {
                    throw new RuntimeException('Unsafe media path refused during restore.');
                }
                $suffix = substr($rel, strlen('media/originals/'));
                $target = $base.'/'.$suffix;
                $parent = dirname($target);
                if (! is_dir($parent) && ! mkdir($parent, 0750, true) && ! is_dir($parent)) {
                    throw new RuntimeException('Could not create a media restore directory.');
                }
                $parentReal = realpath($parent);
                if (! is_string($parentReal) || ($parentReal !== $baseReal && ! str_starts_with($parentReal, $baseReal.DIRECTORY_SEPARATOR))) {
                    throw new RuntimeException('Media restore path escaped private storage.');
                }
                $stream = $zip->getStream($rel);
                $tmp = $target.'.restore-'.bin2hex(random_bytes(6));
                $out = @fopen($tmp, 'xb');
                if (! is_resource($stream) || ! is_resource($out)) {
                    if (is_resource($stream)) fclose($stream);
                    if (is_resource($out)) fclose($out);
                    @unlink($tmp);
                    throw new RuntimeException('Could not open a media restore stream.');
                }
                $hash = hash_init('sha256');
                $bytes = 0;
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 65_536);
                        if ($chunk === false) {
                            throw new RuntimeException('Could not read restored media.');
                        }
                        $bytes += strlen($chunk);
                        if ($bytes > self::MAX_MEDIA_BYTES || fwrite($out, $chunk) !== strlen($chunk)) {
                            throw new RuntimeException('Restored media exceeded its safe boundary.');
                        }
                        hash_update($hash, $chunk);
                    }
                } catch (Throwable $error) {
                    @unlink($tmp);
                    throw $error;
                } finally {
                    fclose($stream);
                    fclose($out);
                }
                if ($bytes !== $meta['bytes'] || ! hash_equals($meta['sha256'], hash_final($hash)) || ! rename($tmp, $target)) {
                    @unlink($tmp);
                    throw new RuntimeException('Restored media failed integrity verification.');
                }
            }
        } finally {
            $zip->close();
        }

        return $check;
    }

    public static function isSafeTableName(string $table): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/D', $table) === 1;
    }

    public static function isSafeMediaPath(string $path): bool
    {
        return strlen($path) <= 240
            && preg_match('#\Amedia/originals/(?:[A-Za-z0-9][A-Za-z0-9._-]{0,127}/)*[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z#D', $path) === 1;
    }

    public static function manifestMac(string $manifestJson, string $key): string
    {
        if (strlen($key) < 32) {
            throw new RuntimeException('A valid private application key is required to authenticate backups.');
        }

        return hash_hmac('sha256', $manifestJson, $key);
    }

    private function authenticationKey(): string
    {
        $key = config('app.key');
        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('A valid private application key is required to create or restore backups.');
        }

        return $key;
    }

    /** @return array{format:int,created_at:string,driver:string,tables:array,files:array} */
    private function authenticatedManifestForRestore(ZipArchive $zip): array
    {
        if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_ENTRIES) {
            throw new RuntimeException('Archive entry count is outside the safe limit.');
        }
        $manifestStat = $zip->statName('manifest.json');
        $macStat = $zip->statName('manifest.hmac');
        if (! is_array($manifestStat) || ! is_array($macStat)
            || (int) $manifestStat['size'] > self::MAX_MANIFEST_BYTES || (int) $macStat['size'] !== 64) {
            throw new RuntimeException('Authenticated manifest is missing or invalid.');
        }
        $json = $zip->getFromName('manifest.json');
        $mac = $zip->getFromName('manifest.hmac');
        if (! is_string($json) || ! is_string($mac)
            || ! hash_equals(self::manifestMac($json, $this->authenticationKey()), strtolower($mac))) {
            throw new RuntimeException('Backup authentication failed.');
        }
        try {
            $manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Manifest JSON is invalid.');
        }
        if (! $this->validManifestShape($manifest)) {
            throw new RuntimeException('Manifest is unsupported or unsafe.');
        }

        $expected = ['manifest.json' => true, 'manifest.hmac' => true];
        $expanded = strlen($json) + 64;
        foreach ($manifest['tables'] as $table => $meta) {
            $entry = 'tables/'.$table.'.jsonl';
            $expected[$entry] = true;
            $stat = $zip->statName($entry);
            if (! is_array($stat) || (int) $stat['size'] !== $meta['bytes'] || (int) $stat['size'] > self::MAX_TABLE_BYTES) {
                throw new RuntimeException('Verified table entry has an invalid size.');
            }
            [$sha, $rows, $validJson] = $this->hashTableEntry($zip, $entry);
            if (! $validJson || $rows !== $meta['rows'] || ! hash_equals($meta['sha256'], $sha)) {
                throw new RuntimeException('Verified table entry failed integrity checks.');
            }
            $expanded += (int) $stat['size'];
        }
        foreach ($manifest['files'] as $rel => $meta) {
            $expected[$rel] = true;
            $stat = $zip->statName($rel);
            $sha = $this->hashEntry($zip, $rel);
            if (! is_array($stat) || (int) $stat['size'] !== $meta['bytes'] || (int) $stat['size'] > self::MAX_MEDIA_BYTES
                || $sha === null || ! hash_equals($meta['sha256'], $sha)) {
                throw new RuntimeException('Verified media entry failed integrity checks.');
            }
            $expanded += (int) $stat['size'];
        }
        if ($expanded > self::MAX_EXPANDED_BYTES) {
            throw new RuntimeException('Archive expands beyond the safe limit.');
        }
        $seen = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || isset($seen[$name]) || ! isset($expected[$name])) {
                throw new RuntimeException('Archive contains duplicate or unexpected entries.');
            }
            $seen[$name] = true;
        }
        if (count($seen) !== count($expected)) {
            throw new RuntimeException('Archive entry set does not match the manifest.');
        }

        return $manifest;
    }

    private function validManifestShape(mixed $manifest): bool
    {
        if (! is_array($manifest) || array_keys($manifest) !== ['format', 'created_at', 'driver', 'tables', 'files']
            || $manifest['format'] !== self::FORMAT || ! is_string($manifest['created_at'])
            || ! is_string($manifest['driver']) || ! is_array($manifest['tables']) || ! is_array($manifest['files'])) {
            return false;
        }
        foreach ($manifest['tables'] as $table => $meta) {
            if (! is_string($table) || ! self::isSafeTableName($table) || ! is_array($meta)
                || array_keys($meta) !== ['rows', 'bytes', 'sha256']
                || ! is_int($meta['rows']) || $meta['rows'] < 0
                || ! is_int($meta['bytes']) || $meta['bytes'] < 0 || $meta['bytes'] > self::MAX_TABLE_BYTES
                || ! self::isSha256($meta['sha256'])) {
                return false;
            }
        }
        foreach ($manifest['files'] as $rel => $meta) {
            if (! is_string($rel) || ! self::isSafeMediaPath($rel) || ! is_array($meta)
                || array_keys($meta) !== ['bytes', 'sha256']
                || ! is_int($meta['bytes']) || $meta['bytes'] < 0 || $meta['bytes'] > self::MAX_MEDIA_BYTES
                || ! self::isSha256($meta['sha256'])) {
                return false;
            }
        }

        return true;
    }

    private static function isSha256(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    /** @return array{0:string,1:int,2:bool} */
    private function hashTableEntry(ZipArchive $zip, string $entry): array
    {
        $stream = $zip->getStream($entry);
        if (! is_resource($stream)) {
            return ['', 0, false];
        }
        $hash = hash_init('sha256');
        $rows = 0;
        $valid = true;
        try {
            while (($line = fgets($stream)) !== false) {
                hash_update($hash, $line);
                if ($line === "\n" || $line === '') {
                    continue;
                }
                try {
                    $row = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
                    if (! is_array($row)) {
                        $valid = false;
                    }
                } catch (JsonException) {
                    $valid = false;
                }
                $rows++;
            }
        } finally {
            fclose($stream);
        }

        return [hash_final($hash), $rows, $valid];
    }

    private function hashEntry(ZipArchive $zip, string $entry): ?string
    {
        $stream = $zip->getStream($entry);
        if (! is_resource($stream)) {
            return null;
        }
        $hash = hash_init('sha256');
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 65_536);
                if ($chunk === false) {
                    return null;
                }
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        return hash_final($hash);
    }

    private function guardExpandedSize(int $bytes): void
    {
        if ($bytes > self::MAX_EXPANDED_BYTES) {
            throw new RuntimeException('Backup exceeds the safe expanded-size limit.');
        }
    }

    /** @return array{ok:false,problems:list<string>,tables:0,rows:0} */
    private static function failed(string $problem): array
    {
        return ['ok' => false, 'problems' => [$problem], 'tables' => 0, 'rows' => 0];
    }

    /** @return list<string> */
    private function generatedColumns(string $table): array
    {
        $out = [];
        foreach (Schema::getColumns($table) as $column) {
            if (! empty($column['generation'])) {
                $out[] = $column['name'];
            }
        }

        return $out;
    }

    /** @return list<array{name:string,bytes:int,created:int}> */
    public function list(): array
    {
        $out = [];
        foreach (glob($this->directory().'/hotel-*.zip') ?: [] as $file) {
            $out[] = ['name' => basename($file), 'bytes' => filesize($file), 'created' => filemtime($file)];
        }
        usort($out, static fn ($a, $b) => $b['created'] <=> $a['created']);

        return $out;
    }

    public function prune(int $keep): int
    {
        $n = 0;
        foreach (array_slice($this->list(), max(1, $keep)) as $backup) {
            @unlink($this->directory().'/'.$backup['name']);
            $n++;
        }

        return $n;
    }
}

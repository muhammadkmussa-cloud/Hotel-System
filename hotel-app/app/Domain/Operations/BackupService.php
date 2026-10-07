<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * P27 — portable logical backups (works on shared hosting without
 * mysqldump): one JSON-lines file per table plus a checksummed manifest,
 * zipped with private media originals. Restore verifies before writing.
 */
final class BackupService
{
    public function directory(): string
    {
        $dir = storage_path('app/private/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /** @return list<string> */
    public function tables(): array
    {
        $tables = array_map(static fn ($t) => is_array($t) ? $t['name'] : $t->name, Schema::getTables());

        return array_values(array_filter($tables, static fn ($t) => ! in_array($t, ['sqlite_sequence', 'cache', 'cache_locks', 'sessions'], true)));
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
        $manifest = ['format' => 1, 'created_at' => gmdate('c'), 'driver' => DB::connection()->getDriverName(), 'tables' => [], 'files' => []];
        foreach ($this->tables() as $table) {
            $lines = '';
            $count = 0;
            foreach (DB::table($table)->cursor() as $row) {
                $lines .= json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
                $count++;
            }
            $zip->addFromString('tables/'.$table.'.jsonl', $lines);
            $manifest['tables'][$table] = ['rows' => $count, 'sha256' => hash('sha256', $lines)];
        }
        $media = storage_path('app/private/media/originals');
        if (is_dir($media)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($media, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $rel = 'media/originals/'.substr($file->getPathname(), strlen($media) + 1);
                $zip->addFile($file->getPathname(), $rel);
                $manifest['files'][$rel] = hash_file('sha256', $file->getPathname());
            }
        }
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $zip->close();

        return $path;
    }

    /** @return array{ok:bool,problems:list<string>,tables:int,rows:int} */
    public function verify(string $path): array
    {
        $problems = [];
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['ok' => false, 'problems' => ['Archive cannot be opened'], 'tables' => 0, 'rows' => 0];
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1) {
            return ['ok' => false, 'problems' => ['Manifest missing or unsupported'], 'tables' => 0, 'rows' => 0];
        }
        $rows = 0;
        foreach ($manifest['tables'] as $table => $meta) {
            $data = $zip->getFromName('tables/'.$table.'.jsonl');
            if ($data === false || hash('sha256', $data) !== $meta['sha256']) {
                $problems[] = 'Checksum mismatch for table '.$table;
            }
            $rows += (int) $meta['rows'];
        }
        foreach ($manifest['files'] as $rel => $sha) {
            $data = $zip->getFromName($rel);
            if ($data === false || hash('sha256', $data) !== $sha) {
                $problems[] = 'Checksum mismatch for '.$rel;
            }
        }
        $zip->close();

        return ['ok' => $problems === [], 'problems' => $problems, 'tables' => count($manifest['tables']), 'rows' => $rows];
    }

    /** Replace all data with the archive contents. Verify first. */
    public function restore(string $path): array
    {
        $check = $this->verify($path);
        if (! $check['ok']) {
            throw new RuntimeException('Backup failed verification: '.implode('; ', $check['problems']));
        }
        $zip = new ZipArchive();
        $zip->open($path);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $current = $this->tables();
        $missing = array_diff(array_keys($manifest['tables']), $current);
        if ($missing !== []) {
            throw new RuntimeException('Run migrations first; missing tables: '.implode(', ', $missing));
        }
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($zip, $manifest): void {
                foreach (array_keys($manifest['tables']) as $table) {
                    DB::table($table)->delete();
                    $generated = $this->generatedColumns($table);
                    $batch = [];
                    foreach (explode("\n", (string) $zip->getFromName('tables/'.$table.'.jsonl')) as $line) {
                        if ($line === '') {
                            continue;
                        }
                        $row = json_decode($line, true);
                        foreach ($generated as $g) {
                            unset($row[$g]);
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
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        foreach ($manifest['files'] as $rel => $sha) {
            $target = storage_path('app/private/'.$rel);
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0750, true);
            }
            file_put_contents($target, $zip->getFromName($rel));
        }
        $zip->close();

        return $check;
    }

    /** @return list<string> generated (stored) columns that must not be inserted */
    private function generatedColumns(string $table): array
    {
        $out = [];
        foreach (Schema::getColumns($table) as $col) {
            if (! empty($col['generation'])) {
                $out[] = $col['name'];
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
        foreach (array_slice($this->list(), max(1, $keep)) as $b) {
            @unlink($this->directory().'/'.$b['name']);
            $n++;
        }

        return $n;
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/** Single-host fixed windows, serialized by OS file locks across PHP workers. */
final class RequestWindow
{
    public function __construct(private readonly string $directory) {}

    /** Returns zero when accepted, otherwise the number of seconds until retry. */
    public function take(string $key, int $maximum, int $seconds): int
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/', $key) || $maximum < 1 || $maximum > 100000 || $seconds < 1 || $seconds > 86400) {
            throw new InvalidArgumentException('Invalid request limit policy.');
        }
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Request limit storage unavailable.');
        }
        $path = $this->directory . '/' . $key;
        $file = @fopen($path, 'x+');
        $created = $file !== false;
        if (! $created) $file = @fopen($path, 'r+');
        if ($file === false) throw new RuntimeException('Request limit storage unavailable.');
        try {
            if (! @chmod($path, 0600) || ! flock($file, LOCK_EX | LOCK_NB)) throw new RuntimeException('Request limit storage unavailable.');
            $raw = stream_get_contents($file, 513);
            if ($raw === false || strlen($raw) > 512 || ($raw === '' && ! $created)) throw new RuntimeException('Request limit storage unavailable.');
            $now = time();
            $state = $raw === '' ? ['count' => 0, 'reset' => $now + $seconds] : json_decode($raw, true);
            if (! is_array($state) || ! isset($state['count'], $state['reset']) || ! is_int($state['count']) || ! is_int($state['reset']) || $state['count'] < 0) {
                throw new RuntimeException('Request limit storage unavailable.');
            }
            if ($state['reset'] <= $now) $state = ['count' => 0, 'reset' => $now + $seconds];
            if ($state['count'] >= $maximum) return max(1, $state['reset'] - $now);
            $state['count']++;
            $encoded = json_encode($state, JSON_THROW_ON_ERROR);
            if (! rewind($file) || ! ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || ! fflush($file)) {
                throw new RuntimeException('Request limit storage unavailable.');
            }
            return 0;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}

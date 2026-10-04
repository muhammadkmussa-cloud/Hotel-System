<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\RequestWindow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RequestWindowTest extends TestCase
{
    private string $directory;
    protected function setUp(): void { $this->directory = sys_get_temp_dir().'/hotel-limit-'.bin2hex(random_bytes(8)); mkdir($this->directory, 0700); }
    protected function tearDown(): void { foreach (glob($this->directory.'/*') as $file) unlink($file); rmdir($this->directory); }

    public function testPersistentWindowsRespectThresholdAndExpiry(): void
    {
        $key = hash('sha256', 'fixture');
        self::assertSame(0, (new RequestWindow($this->directory))->take($key, 2, 60));
        self::assertSame(0, (new RequestWindow($this->directory))->take($key, 2, 60));
        self::assertGreaterThan(0, (new RequestWindow($this->directory))->take($key, 2, 60));
        file_put_contents($this->directory.'/'.$key, json_encode(['count' => 2, 'reset' => time()-1]));
        self::assertSame(0, (new RequestWindow($this->directory))->take($key, 2, 60));
        self::assertSame(0600, fileperms($this->directory.'/'.$key) & 0777);
    }

    public function testConcurrentProcessesCannotExceedTheWindow(): void
    {
        $script = $this->directory.'/probe.php';
        file_put_contents($script, <<<'PHP'
<?php
require $argv[1];
try { echo (new \App\Support\RequestWindow($argv[2]))->take(hash('sha256', 'parallel'), 2, 60); }
catch (\RuntimeException) { echo 'busy'; }
PHP);
        $processes = [];
        try {
            for ($index = 0; $index < 8; $index++) {
                $process = new \Symfony\Component\Process\Process([PHP_BINARY, $script, dirname(__DIR__, 2).'/vendor/autoload.php', $this->directory]);
                $process->start();
                $processes[] = $process;
            }
            $accepted = 0;
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait());
                $output = $process->getOutput();
                self::assertTrue($output === 'busy' || ctype_digit($output));
                if ($output === '0') $accepted++;
            }
            self::assertGreaterThanOrEqual(1, $accepted);
            self::assertLessThanOrEqual(2, $accepted);
        } finally {
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
    }

    public function testContentionAndCorruptStorageFailClosed(): void
    {
        $key = hash('sha256', 'fixture');
        $path = $this->directory.'/'.$key;
        $file = fopen($path, 'c+');
        flock($file, LOCK_EX);
        try {
            try { (new RequestWindow($this->directory))->take($key, 2, 60); self::fail('Contended limit bypassed.'); }
            catch (RuntimeException $error) { self::assertSame('Request limit storage unavailable.', $error->getMessage()); }
        } finally { flock($file, LOCK_UN); fclose($file); }
        try { (new RequestWindow($this->directory))->take($key, 2, 60); self::fail('Empty existing counter reset the window.'); }
        catch (RuntimeException $error) { self::assertSame('Request limit storage unavailable.', $error->getMessage()); }
        file_put_contents($path, 'broken-private-state');
        $this->expectException(RuntimeException::class);
        (new RequestWindow($this->directory))->take($key, 2, 60);
    }
}

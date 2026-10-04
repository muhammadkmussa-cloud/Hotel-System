<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class FoundationSmokeTest extends TestCase
{
    public function testPrivateConfigurationAndRecovery(): void
    {
        $this->runRegression('configuration.php');
    }

    #[Group('http')]
    public function testPageAssetsAndHttpBoundaries(): void
    {
        $this->runRegression('http-smoke.php');
    }

    private function runRegression(string $script): void
    {
        $project = dirname(__DIR__, 2);
        $process = new Process([PHP_BINARY, $project . '/tests/' . $script], $project);
        $process->setTimeout(60);
        $process->run();

        self::assertSame(
            0,
            $process->getExitCode(),
            $script . " failed:\n" . $process->getOutput() . $process->getErrorOutput(),
        );
    }
}

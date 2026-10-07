<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * P08.09 — the writable public media path must never execute code.
 *
 * Apache/DirectAdmin honours .htaccess; the PHP built-in test server does not,
 * so this asserts the shipped hardening file and its key directives rather than
 * executing a planted script.
 */
final class MediaPathHardeningTest extends TestCase
{
    public function testWritableMediaPathShipsAScriptExecutionGuard(): void
    {
        $path = dirname(__DIR__, 2).'/public/media/.htaccess';
        self::assertFileExists($path, 'The writable public media path must ship a hardening file.');

        $contents = (string) file_get_contents($path);
        self::assertStringContainsString('php_flag engine off', $contents);
        self::assertStringContainsString('RemoveHandler .php', $contents);
        self::assertStringContainsString('Require all denied', $contents);
        self::assertStringContainsString('ExecCGI', $contents);
        // SVG/XML/HTML must be denied so an uploaded "image" cannot be served as markup.
        self::assertMatchesRegularExpression('/svg/i', $contents);
    }
}

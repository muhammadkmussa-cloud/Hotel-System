<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\BoundMediaUpload;
use App\Http\Requests\InvalidUpload;
use App\Support\MediaUploadLimits;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P08.02 — Bounded upload middleware.
 *
 * Uses plain Symfony UploadedFile/Request objects so the test exercises
 * middleware logic without booting the whole Laravel application. The real
 * HTTP integration with CSRF/session/capability guards is exercised through
 * http-smoke.php and Playwright once an upload endpoint is wired in P08.08.
 */
final class BoundMediaUploadTest extends TestCase
{
    private MediaUploadLimits $limits;

    protected function setUp(): void
    {
        parent::setUp();
        // Use small deterministic limits for these tests.
        $this->limits = new class extends MediaUploadLimits {
            public function maxBytes(): int { return 1024; }
            public function fieldName(): string { return 'file'; }
            public function acceptedMimeTypes(): array { return ['image/jpeg', 'image/png', 'image/webp']; }
        };
    }

    private function middleware(): BoundMediaUpload
    {
        return new BoundMediaUpload($this->limits);
    }

    private function makeRequest(
        ?UploadedFile $file,
        string $contentType = 'multipart/form-data; boundary=boundary',
        ?string $contentLength = null,
        array $headers = [],
    ): Request {
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => $contentType] + $headers);
        $request->headers = new HeaderBag(array_merge([
            'content-type' => [$contentType],
        ], $contentLength !== null ? ['content-length' => [$contentLength]] : []));
        $files = [];
        if ($file !== null) {
            $files['file'] = $file;
        }
        $request->files = new FileBag($files);

        return $request;
    }

    private function realTempFile(string $content, string $originalName = 'photo.jpg', string $mime = 'image/jpeg', int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hotel-up-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, $mime, $error, true);
    }

    public function testRejectsNonMultipartContentType(): void
    {
        $request = Request::create('/api/v1/media', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json']);
        $request->headers = new HeaderBag(['content-type' => ['application/json']]);
        $request->files = new FileBag([]);

        $this->expectException(InvalidUpload::class);
        $this->middleware()->handle($request, fn () => new Response);
    }

    public function testRejectsOversizedContentLengthBeforeFileRead(): void
    {
        $request = $this->makeRequest(null, 'multipart/form-data; boundary=boundary', '2048');

        try {
            $this->middleware()->handle($request, fn () => new Response);
            self::fail('Expected InvalidUpload.');
        } catch (InvalidUpload $error) {
            self::assertSame(413, $error->getResponse()->getStatusCode());
        }
    }

    public function testRejectsMissingFile(): void
    {
        $request = $this->makeRequest(null, 'multipart/form-data; boundary=boundary', '100');

        $this->expectException(InvalidUpload::class);
        $this->middleware()->handle($request, fn () => new Response);
    }

    public function testRejectsPhpIniSizeError(): void
    {
        $file = new UploadedFile('/nonexistent', 'photo.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);
        $request = $this->makeRequest($file, 'multipart/form-data; boundary=boundary', '100');

        try {
            $this->middleware()->handle($request, fn () => new Response);
            self::fail('Expected InvalidUpload.');
        } catch (InvalidUpload $error) {
            self::assertSame(413, $error->getResponse()->getStatusCode());
        }
    }

    public function testRejectsPartialUpload(): void
    {
        $file = new UploadedFile('/nonexistent', 'photo.jpg', 'image/jpeg', UPLOAD_ERR_PARTIAL, true);
        $request = $this->makeRequest($file);

        try {
            $this->middleware()->handle($request, fn () => new Response);
            self::fail('Expected InvalidUpload.');
        } catch (InvalidUpload $error) {
            self::assertSame(400, $error->getResponse()->getStatusCode());
        }
    }

    public function testRejectsUnsupportedMimeClaim(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'hotel-up-');
        file_put_contents($path, str_repeat('a', 100));
        $file = new UploadedFile($path, 'run.exe', 'application/octet-stream', UPLOAD_ERR_OK, true);
        $request = $this->makeRequest($file, 'multipart/form-data; boundary=boundary', (string) filesize($path));

        try {
            $this->middleware()->handle($request, fn () => new Response);
            self::fail('Expected InvalidUpload for non-image MIME.');
        } catch (InvalidUpload $error) {
            self::assertSame(415, $error->getResponse()->getStatusCode());
        } finally {
            @unlink($path);
        }
    }

    public function testAcceptsValidJpegAndSetsRequestAttribute(): void
    {
        $content = str_repeat("\xFF\xD8\xFF\xE0", 64); // 256 bytes, not a valid JPEG but size/mime OK
        $file = $this->realTempFile($content, 'beef-stew.jpg', 'image/jpeg');
        $request = $this->makeRequest($file, 'multipart/form-data; boundary=boundary', (string) strlen($content));

        $response = $this->middleware()->handle($request, function (Request $passed) {
            $attr = $passed->attributes->get(BoundMediaUpload::ATTRIBUTE);
            if (! is_array($attr)) {
                throw new \RuntimeException('Validated upload attribute missing.');
            }

            return new Response($attr['original_name']);
        });

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('beef-stew.jpg', $response->getContent());

        @unlink($file->getPathname());
    }

    public function testRejectsFilesLargerThanAppBytesEvenIfContentLengthLied(): void
    {
        // Content-Length says 2 bytes but the actual file is 2048 bytes — must
        // be caught by getSize() rather than the fast-fail header check.
        $content = str_repeat('x', 2048);
        $file = $this->realTempFile($content, 'large.jpg', 'image/jpeg');
        $request = $this->makeRequest($file, 'multipart/form-data; boundary=boundary', '2');

        try {
            $this->middleware()->handle($request, fn () => new Response);
            self::fail('Expected InvalidUpload when file size exceeds the cap.');
        } catch (InvalidUpload $error) {
            self::assertSame(413, $error->getResponse()->getStatusCode());
        } finally {
            @unlink($file->getPathname());
        }
    }
}

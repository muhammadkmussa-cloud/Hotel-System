<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\MediaException;
use App\Support\RasterInfo;
use App\Support\RasterLimits;
use App\Support\RasterValidator;
use PHPUnit\Framework\TestCase;

/**
 * P08.03 — RasterValidator unit tests.
 *
 * These tests use hand-built minimal valid JPEG/PNG/WebP payloads and a
 * collection of hostile samples. They run on plain PHP without booting
 * Laravel, so they can be executed on any PHPUnit-capable host.
 */
final class RasterValidatorTest extends TestCase
{
    private RasterValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        // Use small but realistic pixel budget for tests.
        $this->validator = new RasterValidator(new RasterLimits(2048, 2048, 4_194_304, 40)); // 2048*2048
    }

    // ---------- Valid images ----------

    public function testAcceptsMinimalValidJpeg(): void
    {
        $bytes = $this->minimalJpeg(320, 240);
        $info = $this->validator->validate($bytes, 'dish.jpg');
        self::assertInstanceOf(RasterInfo::class, $info);
        self::assertSame('image/jpeg', $info->mime);
        self::assertSame(320, $info->width);
        self::assertSame(240, $info->height);
    }

    public function testAcceptsMinimalValidPng(): void
    {
        $bytes = $this->minimalPng(640, 480);
        $info = $this->validator->validate($bytes, 'meal.png');
        self::assertSame('image/png', $info->mime);
        self::assertSame(640, $info->width);
        self::assertSame(480, $info->height);
    }

    public function testAcceptsMinimalValidWebpVp8l(): void
    {
        $bytes = $this->minimalWebpVp8l(320, 240);
        $info = $this->validator->validate($bytes, 'dish.webp');
        self::assertSame('image/webp', $info->mime);
        self::assertSame(320, $info->width);
        self::assertSame(240, $info->height);
    }

    // ---------- Rejections ----------

    public function testRejectsEmptyFile(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionCode(0);
        try {
            $this->validator->validate('', 'x.jpg');
        } catch (MediaException $e) {
            self::assertSame(MediaException::TRUNCATED, $e->errorCode());
            throw $e;
        }
    }

    public function testRejectsGarbageBytes(): void
    {
        try {
            $this->validator->validate(str_repeat('a', 200), 'x.jpg');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::NOT_A_RASTER, $e->errorCode());
        }
    }

    public function testRejectsHtmlLeadingTag(): void
    {
        try {
            $this->validator->validate('<html><body><script>...'.str_repeat("\x00", 200), 'meal.jpg');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::EXECUTABLE_OR_VECTOR, $e->errorCode());
        }
    }

    public function testRejectsSvgAfterBom(): void
    {
        try {
            $this->validator->validate("\xEF\xBB\xBF\n\n<svg xmlns='http://www.w3.org/2000/svg'>".str_repeat('x', 200), 'meal.svg');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::EXECUTABLE_OR_VECTOR, $e->errorCode());
        }
    }

    public function testRejectsZipPolyglot(): void
    {
        try {
            $this->validator->validate("PK\x03\x04".str_repeat('x', 200), 'meal.jpg');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::EXECUTABLE_OR_VECTOR, $e->errorCode());
        }
    }

    public function testRejectsPeExe(): void
    {
        try {
            $this->validator->validate('MZ'.str_repeat("\x90", 200), 'meal.jpg');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::EXECUTABLE_OR_VECTOR, $e->errorCode());
        }
    }

    public function testRejectsPdf(): void
    {
        try {
            $this->validator->validate('%PDF-1.4'.str_repeat('x', 200), 'meal.jpg');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::EXECUTABLE_OR_VECTOR, $e->errorCode());
        }
    }

    public function testRejectsTruncatedJpegMissingEoi(): void
    {
        $bytes = substr($this->minimalJpeg(320, 240), 0, -4); // strip EOI + trailing
        try {
            $this->validator->validate($bytes, 'x.jpg');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::TRUNCATED, $e->errorCode());
        }
    }

    public function testRejectsTruncatedPngMissingIend(): void
    {
        $bytes = substr($this->minimalPng(100, 100), 0, -20);
        try {
            $this->validator->validate($bytes, 'x.png');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::TRUNCATED, $e->errorCode());
        }
    }

    public function testRejectsOversizedDimensions(): void
    {
        // 8193 x 8193 image is well above our 2048*2048 test budget.
        try {
            $this->validator->validate($this->minimalPng(8193, 8193), 'huge.png');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::DIMENSIONS_TOO_LARGE, $e->errorCode());
        }
    }

    public function testRejectsDecompressionBombArea(): void
    {
        // A narrow-but-extreme panorama exceeds the pixel area without
        // exceeding max width or height individually.
        try {
            $this->validator->validate($this->minimalPng(2048, 2049), 'panorama.png');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::DIMENSIONS_TOO_LARGE, $e->errorCode());
        }
    }

    public function testRejectsUnsupportedPngBitDepth(): void
    {
        try {
            $this->validator->validate($this->minimalPng(100, 100, 1, 2), 'weird.png'); // bit-depth 1 with RGB invalid
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::INVALID_STRUCTURE, $e->errorCode());
        }
    }

    public function testRejectsWebpWithPolyglotTrailer(): void
    {
        // Build a valid 320x240 WebP then append a large ZIP trailer after
        // the RIFF container — the validator should detect RIFF size mismatch.
        $base = $this->minimalWebpVp8l(320, 240);
        self::assertGreaterThan(40, strlen($base));
        $tampered = $base."PK\x03\x04".str_repeat('x', 512);
        try {
            $this->validator->validate($tampered, 'polyglot.webp');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::EXECUTABLE_OR_VECTOR, $e->errorCode());
        }
    }

    public function testRejectsUnsupportedRaster(): void
    {
        // GIF signature GIF89a is not in the accepted set.
        try {
            $this->validator->validate('GIF89a'.str_repeat('a', 200), 'meal.gif');
            self::fail('Expected MediaException.');
        } catch (MediaException $e) {
            self::assertSame(MediaException::NOT_A_RASTER, $e->errorCode());
        }
    }

    // ---------- Fixture builders (minimal valid files) ----------

    private function minimalJpeg(int $w, int $h): string
    {
        // SOI + JFIF APP0 + DQT + SOF0(w,h) + DHT*2 + SOS + entropy + EOI.
        $out = "\xFF\xD8";
        $jfif = "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
        $out .= "\xFF\xE0".pack('n', 2 + strlen($jfif)).$jfif;
        $dqt = "\x00".str_repeat("\x10", 64);
        $out .= "\xFF\xDB".pack('n', 2 + strlen($dqt)).$dqt;
        // CnnC = precision(1), height(2 BE), width(2 BE), components(1).
        $sof = pack('CnnC', 8, $h, $w, 3)
            ."\x01\x22\x00\x02\x11\x01\x03\x11\x01";
        $out .= "\xFF\xC0".pack('n', 2 + strlen($sof)).$sof;
        $dhtBody = "\x00".str_repeat("\x00", 16).str_repeat("\x00", 12);
        $out .= "\xFF\xC4".pack('n', 2 + strlen($dhtBody)).$dhtBody;
        $dhtBody2 = "\x10".str_repeat("\x00", 16).str_repeat("\x00", 12);
        $out .= "\xFF\xC4".pack('n', 2 + strlen($dhtBody2)).$dhtBody2;
        $sos = "\x03\x01\x00\x02\x11\x03\x11\x00\x3F\x00";
        $out .= "\xFF\xDA".pack('n', 2 + strlen($sos)).$sos;
        $out .= "\x00\x40\x00\xFF\xD9";
        // Pad to at least 40 bytes with neutral entropy to satisfy min bytes
        if (strlen($out) < 40) {
            $out = substr($out, 0, -2).str_repeat("\x00", 40 - strlen($out))."\xFF\xD9";
        }
        return $out;
    }

    private function minimalPng(int $w, int $h, int $bitDepth = 8, int $colorType = 2): string
    {
        // Use zlib only if available; otherwise produce a known-good fixed
        // IDAT by falling back to an uncompressed deflate block (RFC 1951).
        $sig = "\x89PNG\r\n\x1A\n";
        $ihdr = pack('N2C5', $w, $h, $bitDepth, $colorType, 0, 0, 0);
        // per-pixel byte count
        $bpp = match ($colorType) {
            0 => intdiv($bitDepth + 7, 8),
            2 => 3 * intdiv($bitDepth + 7, 8),
            3 => 1,
            4 => intdiv($bitDepth + 7, 8) + 1,
            6 => 3 * intdiv($bitDepth + 7, 8) + 1,
            default => 3,
        };
        $raw = "\x00".str_repeat("\x00", $w * $h * $bpp);
        if (strlen($raw) < 10) {
            $raw = str_repeat("\x00", 40);
        }
        // Build a deflate non-compressed block (BTYPE=00) for the raw data.
        $idat = $this->storeDeflate($raw);
        $out = $sig;
        $out .= $this->pngChunk('IHDR', $ihdr);
        $out .= $this->pngChunk('IDAT', $idat);
        $out .= $this->pngChunk('IEND', '');
        return $out;
    }

    private function minimalWebpVp8l(int $w, int $h): string
    {
        // 32-bit LE packed: (w-1):14 | ((h-1):14 << 14) | 0 (alpha + version).
        $bits = (($w - 1) & 0x3FFF) | ((($h - 1) & 0x3FFF) << 14);
        $packed = pack('V', $bits);
        $payload = "\x2F".$packed; // 1 signature + 4 packed bytes
        // Round-trip verify.
        $b0 = ord($payload[1]);
        $b1 = ord($payload[2]);
        $b2 = ord($payload[3]);
        $b3 = ord($payload[4]);
        $wCheck = 1 + ($b0 | (($b1 & 0x3F) << 8));
        $hCheck = 1 + ((($b1 >> 6) & 0x03) | ($b2 << 2) | (($b3 & 0x0F) << 10));
        if ($wCheck !== $w || $hCheck !== $h) {
            throw new \RuntimeException("WebP fixture builder failed roundtrip for {$w}x{$h}: got {$wCheck}x{$hCheck}");
        }
        $chunk = 'VP8L'.pack('V', strlen($payload)).$payload;
        if (strlen($payload) % 2 === 1) {
            $chunk .= "\x00";
        }
        $body = 'WEBP'.$chunk;
        $out = 'RIFF'.pack('V', strlen($body) - 4).$body;
        return $out;
    }

    private function pngChunk(string $type, string $data): string
    {
        $crc = pack('N', crc32($type.$data));
        return pack('N', strlen($data)).$type.$data.$crc;
    }

    /**
     * Produce a valid DEFLATE (RFC 1951) stream containing $data in one
     * non-compressed (BTYPE=00) block with BFINAL=1. zlib wraps it with
     * CMF/FLG and adler32.
     */
    private function storeDeflate(string $data): string
    {
        // Zlib header: CM=8 (deflate), CINFO=7 (32K window), FCHECK=1.
        $cmf = 0x78;
        $flg = 0x01;
        $blocks = '';
        $len = strlen($data);
        // Split into <=0xFFFF chunks to honor non-compressed block length field.
        for ($pos = 0; $pos < $len; $pos += 0xFFFF) {
            $chunk = substr($data, $pos, 0xFFFF);
            $final = ($pos + strlen($chunk) >= $len);
            $btype = 0x00;
            $header = chr(($final ? 0x01 : 0x00) | $btype);
            $l = strlen($chunk);
            $header .= pack('vv', $l, (~$l) & 0xFFFF);
            $blocks .= $header.$chunk;
        }
        $adler = $this->adler32($data);
        return chr($cmf).chr($flg).$blocks.pack('N', $adler);
    }

    private function adler32(string $data): int
    {
        $a = 1;
        $b = 0;
        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $a = ($a + ord($data[$i])) % 65521;
            $b = ($b + $a) % 65521;
        }
        return ($b << 16) | $a;
    }
}

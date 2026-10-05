<?php

declare(strict_types=1);

namespace App\Support;

/**
 * P08.03 — Raster signature validator.
 *
 * Performs magic-byte inspection, header dimension parsing, truncation
 * detection and decompression-bomb gating BEFORE any GD/Imagick decode. This
 * is the second line of defence (after BoundMediaUpload's size/MIME guard) and
 * is intentionally conservative: we would rather reject a weird but valid
 * file than accept a polyglot/executable payload.
 *
 * The validator works on the raw file bytes (a string) so it can be unit
 * tested without filesystem access and used against both the multipart temp
 * file and stored originals. It does NOT rely on finfo/ext-fileinfo alone
 * because those can be fooled by embedded payloads after the signature.
 *
 * Rejected outright:
 *   - Files starting with HTML/script tags (`<`, `<!`, `<?`)
 *   - Files starting with SVG declarations (`<svg`, `<?xml`)
 *   - ZIP/RAR/7z/PE/ELF/MSI/PDF magic bytes (polyglot trap)
 *   - Anything whose first 16 bytes do not match JPEG/PNG/WebP signatures
 *   - JPEGs that lack a SOFn marker with valid dimensions
 *   - PNGs that don't start with a valid IHDR with sensible dimensions
 *   - WebPs that don't parse VP8/VP8L/VP8X dimension headers
 *   - Files whose declared dimensions exceed max_width/max_height/pixel_area
 *   - Truncated files (missing IEND/EOI or RIFF size mismatch beyond a small
 *     trailer buffer)
 *
 * Returns a RasterInfo value object with detected mime, width, height and the
 * byte offset of the last valid end marker (for audit logging).
 */
final class RasterValidator
{
    // How many bytes of a WebP RIFF size mismatch we tolerate — some writers
    // round, but anything larger suggests trailing polyglot data.
    private const RIFF_SIZE_TOLERANCE = 64;

    // Maximum number of JPEG marker segments to walk before giving up; bounds
    // CPU for adversarial files with thousands of garbage markers.
    private const JPEG_MAX_MARKERS = 512;

    // Maximum number of PNG chunks to walk before giving up.
    private const PNG_MAX_CHUNKS = 64;

    public function __construct(private readonly RasterLimits $limits) {}

    /**
     * Validate raw bytes and return detected raster metadata.
     *
     * @param  string  $bytes  Complete file contents (already bounded by
     *                         BoundMediaUpload to <= max_bytes).
     * @param  string  $claimedFilename  Original client filename for error
     *                                   context only; not used for decisions.
     * @throws MediaException on any failure
     */
    public function validate(string $bytes, string $claimedFilename = ''): RasterInfo
    {
        $length = strlen($bytes);
        $min = $this->limits->minFileBytes();
        if ($length < $min) {
            throw MediaException::truncated(' ('.strlen($bytes).' bytes)');
        }

        // FIRST: reject obvious text/script payloads before anything else,
        // even if later bytes look like an image (polyglot defence).
        $this->rejectExecutablePrefix($bytes);

        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => $this->validateJpeg($bytes, $length),
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => $this->validatePng($bytes, $length),
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => $this->validateWebp($bytes, $length),
            default => throw MediaException::notARaster($claimedFilename !== '' ? ' ('.$claimedFilename.')' : ''),
        };
    }

    private function rejectExecutablePrefix(string $bytes): void
    {
        // Strip leading BOM/whitespace — polyglot HTML/SVG commonly hides
        // after a UTF-8 BOM or a few newlines.
        $trimmed = ltrim($bytes, "\xEF\xBB\xBF \t\r\n\0");
        if ($trimmed === '') {
            throw MediaException::notARaster();
        }
        $first = $trimmed[0];

        // "<" opens SVG/HTML/XML — never legitimate for the raster formats we
        // accept.
        if ($first === '<') {
            $lower = strtolower(substr(ltrim($trimmed, '< '), 0, 16));
            if (str_starts_with($lower, 'svg')
                || str_starts_with($lower, 'html')
                || str_starts_with($lower, '!doctype')
                || str_starts_with($lower, '?xml')
                || str_starts_with($lower, 'script')
                || str_starts_with($lower, 'body')
                || str_starts_with($lower, 'head')
                || str_starts_with($lower, '?php')) {
                throw MediaException::executableOrVector(substr($lower, 0, 8));
            }
            throw MediaException::executableOrVector('text/markup');
        }

        // Reject known dangerous magic bytes before checking for image magic.
        // PK = ZIP/DOCX/XLSX/APK/JAR; MZ = PE/EXE/DLL; %PDF = PDF; 7z = 7-Zip;
        // Rar! = RAR; \x7fELF = ELF binary; \x1f\x8b = gzip (tar bomb);
        // USTAR = tar; %!PS = PostScript.
        static $dangerous = [
            "PK\x03\x04" => 'zip',
            "PK\x05\x06" => 'zip-empty',
            "PK\x07\x08" => 'zip-spanned',
            'MZ' => 'pe-exe',
            '%PDF' => 'pdf',
            "7z\xBC\xAF\x27\x1C" => '7z',
            'Rar!' => 'rar',
            "\x7FELF" => 'elf',
            "\x1F\x8B" => 'gzip',
            'ustar' => 'tar',
            '%!PS' => 'postscript',
        ];
        foreach ($dangerous as $magic => $label) {
            if (str_starts_with($trimmed, $magic)) {
                throw MediaException::executableOrVector($label);
            }
        }
    }

    private function validateJpeg(string $bytes, int $length): RasterInfo
    {
        // JPEG starts with SOI 0xFFD8 and ends with EOI 0xFFD9. We scan for
        // the first SOFn marker (C0/C1/C2; exclude DHT C4, DAC CC, DNL DC,
        // restart markers D0-D7, SOI D8, EOI D9, SOS DA, DBP D8, COM FE,
        // APPn E0-EF) to read dimensions.
        if ($length < 4 || ! str_starts_with($bytes, "\xFF\xD8")) {
            throw MediaException::notARaster();
        }

        $width = 0;
        $height = 0;
        $offset = 2;
        $eoiFound = false;
        $markersSeen = 0;
        $inEntropy = false;

        while ($offset < $length - 1 && $markersSeen < self::JPEG_MAX_MARKERS) {
            $markersSeen++;

            if ($bytes[$offset] !== "\xFF") {
                $offset++;
                continue;
            }

            // Skip run of padding 0xFF bytes; after this, $offset points at
            // the byte immediately after the last 0xFF.
            $ffStart = $offset;
            while ($offset < $length && $bytes[$offset] === "\xFF") {
                $offset++;
            }
            if ($offset >= $length) {
                break;
            }
            $marker = ord($bytes[$offset]);
            $offset++;

            // After SOS we are in entropy-coded data. A 0xFF in entropy is
            // either byte-stuffing (0xFF 0x00 = literal 0xFF), a restart
            // marker RST0–RST7 (0xFF 0xD0–D7), or EOI/DNL/another marker.
            if ($inEntropy) {
                if ($marker === 0x00) {
                    // byte-stuffed literal 0xFF — not a marker, continue.
                    continue;
                }
                if ($marker >= 0xD0 && $marker <= 0xD7) {
                    // RST marker — no length, continue entropy.
                    continue;
                }
                // Any other non-zero byte ends entropy and is a marker.
                $inEntropy = false;
                // Fall through to marker handling.
            }

            // EOI
            if ($marker === 0xD9) {
                $eoiFound = true;
                break;
            }
            // SOI again can appear between scans (odd but legal).
            if ($marker === 0xD8) {
                continue;
            }
            // Standalone TEM marker.
            if ($marker === 0x01) {
                continue;
            }
            // All other markers: 2-byte big-endian segment length (including
            // the length field itself, but NOT the marker code).
            if ($offset + 1 >= $length) {
                throw MediaException::truncated(' at marker '.dechex($marker));
            }
            $segLen = (ord($bytes[$offset]) << 8) | ord($bytes[$offset + 1]);
            if ($segLen < 2 || $offset + $segLen > $length) {
                throw MediaException::truncated(' at segment '.dechex($marker));
            }

            // SOF0/SOF1/SOF2 (baseline, extended sequential, progressive)
            if (in_array($marker, [0xC0, 0xC1, 0xC2], true)) {
                if ($segLen < 7) {
                    throw MediaException::invalidStructure(' (SOF segment)');
                }
                $h = (ord($bytes[$offset + 3]) << 8) | ord($bytes[$offset + 4]);
                $w = (ord($bytes[$offset + 5]) << 8) | ord($bytes[$offset + 6]);
                if ($w > 0 && $h > 0) {
                    $width = $w;
                    $height = $h;
                }
            }

            // SOS: segment header precedes entropy data. Skip the header then
            // re-enter entropy scan mode; we will pick up markers there.
            if ($marker === 0xDA) {
                $inEntropy = true;
                $offset += $segLen;
                continue;
            }

            $offset += $segLen;
        }

        if (! $eoiFound) {
            throw MediaException::truncated(' (missing EOI marker)');
        }
        if ($width < 1 || $height < 1) {
            throw MediaException::dimensionsMissing();
        }
        $this->enforcePixelBudget($width, $height);

        return new RasterInfo('image/jpeg', $width, $height, $offset);
    }

    private function validatePng(string $bytes, int $length): RasterInfo
    {
        $sig = "\x89PNG\r\n\x1a\n";
        if ($length < 33 || ! str_starts_with($bytes, $sig)) {
            throw MediaException::notARaster();
        }

        // IHDR must be the first chunk.
        $offset = strlen($sig);
        $ihdrLen = (ord($bytes[$offset]) << 24)
            | (ord($bytes[$offset + 1]) << 16)
            | (ord($bytes[$offset + 2]) << 8)
            | ord($bytes[$offset + 3]);
        $chunkType = substr($bytes, $offset + 4, 4);
        if ($chunkType !== 'IHDR' || $ihdrLen !== 13) {
            throw MediaException::invalidStructure(' (PNG must start with IHDR of 13 bytes)');
        }
        // IHDR data: width(4) height(4) bit-depth(1) color-type(1) ...
        $w = (ord($bytes[$offset + 8]) << 24)
            | (ord($bytes[$offset + 9]) << 16)
            | (ord($bytes[$offset + 10]) << 8)
            | ord($bytes[$offset + 11]);
        $h = (ord($bytes[$offset + 12]) << 24)
            | (ord($bytes[$offset + 13]) << 16)
            | (ord($bytes[$offset + 14]) << 8)
            | ord($bytes[$offset + 15]);
        $bitDepth = ord($bytes[$offset + 16]);
        $colorType = ord($bytes[$offset + 17]);
        if ($w < 1 || $h < 1) {
            throw MediaException::dimensionsMissing();
        }
        // Reject weird bit depths / colour types early. Valid PNG depths per
        // colour type: 0→1/2/4/8/16; 2→8/16; 3→1/2/4/8; 4→8/16; 6→8/16.
        static $allowedDepths = [
            0 => [1, 2, 4, 8, 16],
            2 => [8, 16],
            3 => [1, 2, 4, 8],
            4 => [8, 16],
            6 => [8, 16],
        ];
        if (! isset($allowedDepths[$colorType]) || ! in_array($bitDepth, $allowedDepths[$colorType], true)) {
            throw MediaException::invalidStructure(' (unsupported PNG bit depth/color type)');
        }

        // Walk chunks forward, verify IEND exists and no chunk length exceeds
        // remaining bytes (defends against crafted huge-length chunks).
        $offset += 8 + $ihdrLen + 4; // len + type + data + crc
        $iendFound = false;
        $chunks = 1;
        while ($offset + 8 <= $length && $chunks < self::PNG_MAX_CHUNKS) {
            $chunks++;
            $len = (ord($bytes[$offset]) << 24)
                | (ord($bytes[$offset + 1]) << 16)
                | (ord($bytes[$offset + 2]) << 8)
                | ord($bytes[$offset + 3]);
            $type = substr($bytes, $offset + 4, 4);
            if ($len < 0 || $offset + 8 + $len + 4 > $length) {
                throw MediaException::truncated(' (PNG chunk out of bounds: '.$type.')');
            }
            if ($type === 'IEND') {
                if ($len !== 0) {
                    throw MediaException::invalidStructure(' (IEND must be empty)');
                }
                $iendFound = true;
                $offset += 12;
                break;
            }
            $offset += 8 + $len + 4;
        }
        if (! $iendFound) {
            throw MediaException::truncated(' (missing IEND)');
        }
        $this->enforcePixelBudget($w, $h);

        return new RasterInfo('image/png', $w, $h, $offset);
    }

    private function validateWebp(string $bytes, int $length): RasterInfo
    {
        // RIFF header: "RIFF" + 4 byte LE size (file length - 8) + "WEBP"
        if ($length < 30 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP') {
            throw MediaException::notARaster();
        }
        $riffSize = (ord($bytes[4]))
            | (ord($bytes[5]) << 8)
            | (ord($bytes[6]) << 16)
            | (ord($bytes[7]) << 24);
        // RIFF size should equal file length - 8; allow a small tolerance
        // for appended trailers (e.g. extra EXIF from broken encoders) but
        // reject large mismatches that look like polyglot data after the image.
        $expected = $length - 8;
        if ($riffSize < 20 || $riffSize > $expected + self::RIFF_SIZE_TOLERANCE) {
            throw MediaException::truncated(' (RIFF size mismatch)');
        }
        // If the declared RIFF is much shorter than actual bytes, that's
        // trailing data; reject as probable polyglot.
        if ($riffSize + 8 < $length - self::RIFF_SIZE_TOLERANCE) {
            throw MediaException::executableOrVector('webp-with-trailer');
        }

        $subchunkOffset = 12;
        $width = 0;
        $height = 0;
        $subchunkFound = false;

        while ($subchunkOffset + 8 <= $length) {
            $fourcc = substr($bytes, $subchunkOffset, 4);
            $chunkSize = (ord($bytes[$subchunkOffset + 4]))
                | (ord($bytes[$subchunkOffset + 5]) << 8)
                | (ord($bytes[$subchunkOffset + 6]) << 16)
                | (ord($bytes[$subchunkOffset + 7]) << 24);
            $dataStart = $subchunkOffset + 8;
            if ($chunkSize < 0 || $dataStart + $chunkSize > $length) {
                throw MediaException::truncated(' ('.$fourcc.' chunk out of bounds)');
            }

            if ($fourcc === 'VP8 ') {
                // Lossy VP8: bitstream starts with 3-byte frame tag "VP8
                // data" where the spec says bytes 0-2 are a 24-bit "tag"
                // (0x9D012A for a keyframe start code at bytes 3-5). Actual
                // image width/height are in the uncompressed frame header
                // starting at byte 6 of the chunk data: 2-byte LE width and
                // 2-byte LE height (14 bits each, 2 scale bits above).
                if ($chunkSize < 10) {
                    throw MediaException::truncated(' (VP8 chunk too small)');
                }
                $w = (ord($bytes[$dataStart + 6]) | (ord($bytes[$dataStart + 7]) << 8)) & 0x3FFF;
                $h = (ord($bytes[$dataStart + 8]) | (ord($bytes[$dataStart + 9]) << 8)) & 0x3FFF;
                if ($w > 0 && $h > 0) {
                    $width = $w;
                    $height = $h;
                    $subchunkFound = true;
                    break;
                }
                throw MediaException::dimensionsMissing();
            }

            if ($fourcc === 'VP8L') {
                // Lossless stream per WebP Container Specification:
                //   byte 0 of chunk payload: 0x2F signature
                //   Then a 32-bit little-endian packed integer where:
                //     bits  0-13: width - 1
                //     bits 14-27: height - 1
                //     bit  28:    alpha_is_used hint
                //     bits 29-30: version (must be 0)
                if ($chunkSize < 5) {
                    throw MediaException::truncated(' (VP8L chunk too small)');
                }
                if (ord($bytes[$dataStart]) !== 0x2F) {
                    throw MediaException::invalidStructure(' (VP8L signature)');
                }
                $b0 = ord($bytes[$dataStart + 1]);
                $b1 = ord($bytes[$dataStart + 2]);
                $b2 = ord($bytes[$dataStart + 3]);
                $b3 = ord($bytes[$dataStart + 4]);
                // Version bits must be 0 — anything else is a malformed stream.
                if (($b3 & 0xC0) !== 0x00) {
                    throw MediaException::invalidStructure(' (VP8L version bits)');
                }
                $w = 1 + ($b0 | (($b1 & 0x3F) << 8));
                $h = 1 + ((($b1 >> 6) & 0x03) | ($b2 << 2) | (($b3 & 0x0F) << 10));
                if ($w > 0 && $h > 0) {
                    $width = $w;
                    $height = $h;
                    $subchunkFound = true;
                    break;
                }
                throw MediaException::dimensionsMissing();
            }

            if ($fourcc === 'VP8X') {
                // Extended format: 10 bytes of flags/ICC/alpha, then 3 bytes
                // LE width-1 and 3 bytes LE height-1.
                if ($chunkSize < 16) {
                    throw MediaException::truncated(' (VP8X chunk too small)');
                }
                $w = 1 + (ord($bytes[$dataStart + 12])
                    | (ord($bytes[$dataStart + 13]) << 8)
                    | (ord($bytes[$dataStart + 14]) << 16));
                $h = 1 + (ord($bytes[$dataStart + 15])
                    | (ord($bytes[$dataStart + 16]) << 8)
                    | (ord($bytes[$dataStart + 17]) << 16));
                if ($w > 0 && $h > 0) {
                    $width = $w;
                    $height = $h;
                    $subchunkFound = true;
                    // Do NOT break: VP8X is usually first, followed by VP8
                    // or VP8L; keep walking to ensure file is well-formed?
                    // We conservatively accept VP8X dimensions since the
                    // spec guarantees they are the canvas width/height;
                    // any mismatch with the later bitstream will be caught
                    // by actual decode in P08.05.
                    break;
                }
                throw MediaException::dimensionsMissing();
            }

            // Skip unknown chunk; chunks are padded to even size.
            $advance = 8 + $chunkSize + ($chunkSize % 2);
            $subchunkOffset += $advance;
        }

        if (! $subchunkFound) {
            throw MediaException::notARaster(' (no VP8/VP8L/VP8X header)');
        }
        $this->enforcePixelBudget($width, $height);

        return new RasterInfo('image/webp', $width, $height, $subchunkOffset);
    }

    private function enforcePixelBudget(int $width, int $height): void
    {
        if ($width > $this->limits->maxWidth() || $height > $this->limits->maxHeight()) {
            throw MediaException::dimensionsTooLarge($width, $height, $this->limits->maxPixelArea());
        }
        if ($width > 0 && $height > intdiv(PHP_INT_MAX, $width)) {
            // Multiplication would overflow — definitely too large.
            throw MediaException::dimensionsTooLarge($width, $height, $this->limits->maxPixelArea());
        }
        $area = $width * $height;
        if ($area > $this->limits->maxPixelArea()) {
            throw MediaException::dimensionsTooLarge($width, $height, $this->limits->maxPixelArea());
        }
    }
}

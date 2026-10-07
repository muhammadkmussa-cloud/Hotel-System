<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Requests\InvalidUpload;
use App\Support\MediaUploadLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * P08.02 — Bounded authorized media upload parsing.
 *
 * This middleware enforces the application-level upload cap BEFORE any
 * controller code touches an UploadedFile. It does not decode raster bytes
 * (that is P08.03) and does not write anything to disk itself. What it does:
 *
 *   1. Require multipart/form-data Content-Type (no base64-in-JSON blobs, no
 *      raw bodies, no other encodings) so we have one predictable code path.
 *   2. Require exactly one file under the configured field name and refuse
 *      additional unexpected fields so a client cannot smuggle work in.
 *   3. Reject early using a sane capped Content-Length when the client was
 *      honest, because Laravel's normal file handling reads the whole body.
 *   4. Verify PHP's upload error code — partial uploads, no file, extension
 *      stops, and "INI size" rejections all become safe 4xx responses.
 *   5. Verify the claimed byte count against the configured application cap
 *      (which is itself below PHP/post_max_size in production).
 *   6. Verify the claimed client MIME type is in the configured allow list.
 *      Actual signature verification arrives in P08.03.
 *
 * After this middleware runs, the controller can call `$request->validatedFile()`
 * (added here as a request attribute) to obtain the UploadedFile, confident
 * that oversized or malformed input has been rejected before processing.
 */
final class BoundMediaUpload
{
    public const ATTRIBUTE = 'hotel.validatedUpload';

    public function __construct(private readonly MediaUploadLimits $limits) {}

    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = $this->limits->maxBytes();
        $field = $this->limits->fieldName();

        // Only applies to upload routes. Controllers opt in via middleware.
        $contentType = strtolower((string) $request->headers->get('Content-Type', ''));
        if (! str_starts_with($contentType, 'multipart/form-data')) {
            throw new InvalidUpload(415, 'UNSUPPORTED_MEDIA_TYPE', 'Send multipart/form-data with one file field.');
        }

        // Fast-fail on Content-Length when available before PHP parses the
        // body. If a client omits the header we fall through to PHP's parsed
        // size check below; post_max_size breaches will already have been
        // converted to an empty POST by PHP and caught there.
        $claimedLength = $request->headers->get('Content-Length');
        if ($claimedLength !== null && preg_match('/\A[0-9]{1,20}\z/', $claimedLength) === 1) {
            if ((int) $claimedLength > $maxBytes) {
                throw new InvalidUpload(413, 'PAYLOAD_TOO_LARGE', 'Upload exceeds the configured size limit.');
            }
        }

        // Require exactly one file and no additional POST fields. The full
        // metadata-aware upload endpoint arrives in P08.08; until then extra
        // multipart parts are rejected so a client cannot smuggle work past
        // the size cap or seed metadata before validation exists.
        if ($request->request->count() !== 0) {
            throw new InvalidUpload(400, 'MALFORMED_INPUT', 'Send only the file field; metadata endpoints arrive later.');
        }
        if ($request->files->count() !== 1 || ! $request->files->has($field)) {
            throw new InvalidUpload(400, 'MALFORMED_INPUT', 'Attach exactly one file under the "'.$field.'" field.');
        }

        // Read the raw file bag: Laravel's file() helper hides entries that
        // carry a PHP upload error, which would mask a 413 as a generic 400.
        $file = $request->files->get($field);
        if (! $file instanceof UploadedFile) {
            throw new InvalidUpload(400, 'MALFORMED_INPUT', 'Uploaded file could not be read.');
        }

        switch ($file->getError()) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new InvalidUpload(413, 'PAYLOAD_TOO_LARGE', 'Upload exceeds the configured size limit.');
            case UPLOAD_ERR_PARTIAL:
                throw new InvalidUpload(400, 'MALFORMED_INPUT', 'Upload was interrupted. Try again.');
            case UPLOAD_ERR_NO_FILE:
                throw new InvalidUpload(400, 'MALFORMED_INPUT', 'No file was attached.');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                // Server-side problem — do not leak the reason.
                throw new InvalidUpload(503, 'UNAVAILABLE', 'Upload cannot be accepted right now.');
            default:
                throw new InvalidUpload(400, 'MALFORMED_INPUT', 'Upload could not be accepted.');
        }

        // Guard against an attacker who lies about Content-Length. getSize()
        // returns the actual byte count PHP wrote to the temp file.
        $size = $file->getSize();
        if (! is_int($size) || $size < 1 || $size > $maxBytes) {
            throw new InvalidUpload(413, 'PAYLOAD_TOO_LARGE', 'Upload exceeds the configured size limit.');
        }

        // MIME allow-list check against the client-supplied type and the
        // guess from the file's extension. Signature verification is P08.03,
        // so this is a structural gate only — it does not prove image/* content.
        $claimed = strtolower((string) $file->getClientMimeType());
        $guessed = strtolower((string) $file->getMimeType());
        $accepted = $this->limits->acceptedMimeTypes();
        if (! in_array($claimed, $accepted, true) || ! in_array($guessed, $accepted, true)) {
            throw new InvalidUpload(415, 'UNSUPPORTED_MEDIA_TYPE', 'Accepted formats are JPEG, PNG and WebP only.');
        }

        // Original filename is informational only; we never trust it for storage.
        $originalName = $file->getClientOriginalName();
        if (! is_string($originalName) || $originalName === '' || mb_strlen($originalName) > MediaUploadLimits::MAX_ORIGINAL_STEM_LENGTH) {
            throw new InvalidUpload(400, 'MALFORMED_INPUT', 'File name is missing or too long.');
        }

        $request->attributes->set(self::ATTRIBUTE, [
            'file' => $file,
            'size_bytes' => $size,
            'claimed_mime' => $claimed,
            'original_name' => $originalName,
        ]);

        return $next($request);
    }
}

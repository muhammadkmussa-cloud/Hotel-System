<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Media upload limits (P08.02)
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_bytes' => (int) env('MEDIA_UPLOAD_MAX_BYTES', 20 * 1024 * 1024), // 20 MiB
        'accepted_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'field_name' => 'file',
    ],

    /*
    |--------------------------------------------------------------------------
    | Raster decode limits (P08.03)
    |--------------------------------------------------------------------------
    |
    | After the upload byte cap passes, RasterValidator inspects the file's
    | actual magic bytes and parses dimension headers without fully decoding
    | pixel data. These caps bound what the server will even *attempt* to
    | decode with GD in P08.05 — any image claiming larger dimensions is
    | rejected as a probable decompression bomb. Width and height are
    | independent so an extreme panorama or a tall ingredient portrait is
    | rejected before it consumes memory. Pixel area is the product and is
    | the primary memory guard.
    |
    | Minimum file size excludes trivially empty/truncated uploads that claim
    | to be images. JPEG/PNG headers alone are ~10-20 bytes; legitimate photos
    | almost never fall below a few hundred bytes after EXIF/quantisation.
    */
    'raster' => [
        'max_width' => (int) env('MEDIA_MAX_WIDTH', 8192),
        'max_height' => (int) env('MEDIA_MAX_HEIGHT', 8192),
        // 67 megapixels — well above the 2000 px master target but a firm
        // wall against billion-pixel decompression bombs.
        'max_pixel_area' => (int) env('MEDIA_MAX_PIXEL_AREA', 67_108_864), // 8192 * 8192
        'min_file_bytes' => (int) env('MEDIA_MIN_FILE_BYTES', 120),
    ],
];

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * P08.02 — upload-specific HTTP error. Thrown when an upload is missing,
 * oversized, partial, or uses an unsupported media type, before any decoding
 * or storage work starts. Mirrors InvalidJsonInput so upload routes return the
 * same safe envelope shape used elsewhere.
 */
final class InvalidUpload extends HttpResponseException
{
    public function __construct(int $status, ?string $code = null, ?string $message = null)
    {
        $response = ApiResponse::error(new Request, $status);
        if ($code !== null || $message !== null) {
            $data = $response->getData(true);
            if ($code !== null) {
                $data['error']['code'] = $code;
            }
            if ($message !== null) {
                $data['error']['message'] = $message;
            }
            $response->setData($data);
        }
        parent::__construct($response);
    }
}

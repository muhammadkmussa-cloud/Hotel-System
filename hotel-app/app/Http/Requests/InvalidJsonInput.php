<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

final class InvalidJsonInput extends HttpResponseException
{
    public function __construct(int $status)
    {
        parent::__construct(ApiResponse::error(new Request, $status));
    }
}

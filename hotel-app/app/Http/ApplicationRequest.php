<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class ApplicationRequest extends Request
{
    public static function createFromBase(SymfonyRequest $request)
    {
        $incoming = new static(
            $request->query->all(), $request->request->all(), $request->attributes->all(),
            $request->cookies->all(), $request->files->all(), $request->server->all(),
        );
        if (! $incoming->is('api/v1', 'api/v1/*')) return parent::createFromBase($request);

        $incoming->headers->replace($request->headers->all());
        $incoming->content = $request->getContent(true);
        // API JSON is decoded only by the bounded parser, never during capture.
        return $incoming;
    }
}

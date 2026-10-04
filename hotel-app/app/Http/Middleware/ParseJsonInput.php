<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Requests\InvalidJsonInput;
use Closure;
use Illuminate\Http\Request;
use JsonException;
use stdClass;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;

final class ParseJsonInput
{
    public const MAX_BYTES = 65536;
    public const MAX_DEPTH = 32;
    public const ATTRIBUTE = 'hotel.jsonObject';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1', 'api/v1/*')) return $next($request);

        if ($request->headers->has('X-HTTP-Method-Override') || $request->request->has('_method') || $request->query->has('_method')) throw new InvalidJsonInput(400);

        // Run before Laravel's input transforms: never eagerly decode an unbounded body.
        $stream = $request->getContent(true);
        $body = stream_get_contents($stream, self::MAX_BYTES + 1);
        if ($body === false) throw new InvalidJsonInput(400);
        if (strlen($body) > self::MAX_BYTES) throw new InvalidJsonInput(413);
        $request->setJson(new InputBag());
        if ($body === '') return $next($request);

        if (! preg_match('/\Aapplication\/json(?:\s*;\s*charset\s*=\s*(?:utf-8|"utf-8"))?\s*\z/i', $request->headers->get('Content-Type', ''))
            || ! in_array(strtolower($request->headers->get('Content-Encoding', 'identity')), ['', 'identity'], true)) {
            throw new InvalidJsonInput(415);
        }
        try {
            $object = json_decode($body, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            json_encode($object, JSON_THROW_ON_ERROR); // Reject overflowing JSON exponents (INF).
        } catch (JsonException) {
            throw new InvalidJsonInput(400);
        }
        if (! $object instanceof stdClass) throw new InvalidJsonInput(400);
        $this->rejectDuplicateKeys($body);
        $request->attributes->set(self::ATTRIBUTE, $object);
        $request->setJson(new InputBag(json_decode($body, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING)));

        return $next($request);
    }

    private function rejectDuplicateKeys(string $body): void
    {
        // Syntax is already checked. Track decoded member names separately in each object.
        $stack = [];
        $length = strlen($body);
        for ($offset = 0; $offset < $length; $offset++) {
            $token = $body[$offset];
            if ($token === '"') {
                $start = $offset++;
                while ($offset < $length && $body[$offset] !== '"') {
                    if ($body[$offset] === '\\') $offset++;
                    $offset++;
                }
                $token = substr($body, $start, $offset - $start + 1);
            } elseif (! str_contains('{}[],', $token)) {
                continue;
            }
            if ($token === '{' || $token === '[') {
                $stack[] = ['object' => $token === '{', 'key' => true, 'seen' => []];
            } elseif ($token === '}' || $token === ']') {
                array_pop($stack);
            } elseif ($token === ',') {
                $stack[array_key_last($stack)]['key'] = true;
            } else {
                $index = array_key_last($stack);
                if ($stack[$index]['object'] && $stack[$index]['key']) {
                    $key = json_decode($token, true, flags: JSON_THROW_ON_ERROR);
                    if (isset($stack[$index]['seen'][$key])) throw new InvalidJsonInput(400);
                    $stack[$index]['seen'][$key] = true;
                    $stack[$index]['key'] = false;
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Middleware\ParseJsonInput;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\Request;
use stdClass;

/** Endpoint rules must declare every object member, including containers and list-item members. */
final class JsonInput
{
    public function __construct(private readonly Factory $validator) {}

    /** @param array<string, mixed> $rules @return array<string, mixed> */
    public function validate(Request $request, array $rules): array
    {
        $object = $request->attributes->get(ParseJsonInput::ATTRIBUTE);
        if (! $object instanceof stdClass) throw new InvalidJsonInput(422);
        $paths = array_map(static fn (string $key): array => explode('.', $key), array_keys($rules));
        $this->checkMembers($object, [], $paths);
        // Decode the checked object, never merged query, route, file or session inputs.
        $data = json_decode(json_encode($object, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, ParseJsonInput::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        $validator = $this->validator->make($data, $rules)->stopOnFirstFailure();
        if ($validator->fails()) throw new InvalidJsonInput(422);

        return $validator->validated();
    }

    private function checkMembers(mixed $value, array $path, array $allowed): void
    {
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $child) {
                $member = [...$path, (string) $key];
                $matched = false;
                foreach ($allowed as $pattern) {
                    if (count($pattern) !== count($member)) continue;
                    $matched = true;
                    foreach ($pattern as $index => $segment) {
                        if ($segment === '*' ? ! is_int($member[$index]) : $segment !== $member[$index]) {
                            $matched = false;
                            break;
                        }
                    }
                    if ($matched) break;
                }
                if (! $matched) throw new InvalidJsonInput(422);
                $this->checkMembers($child, $member, $allowed);
            }
        } elseif (is_array($value)) {
            foreach ($value as $index => $child) $this->checkMembers($child, [...$path, $index], $allowed);
        }
    }
}

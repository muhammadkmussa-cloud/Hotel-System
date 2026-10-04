<?php

declare(strict_types=1);

namespace App\Security;

final class SessionPrincipal implements Principal
{
    public function __construct(private readonly string $staffUserId) {}

    public function identifier(): string
    {
        return $this->staffUserId;
    }
}

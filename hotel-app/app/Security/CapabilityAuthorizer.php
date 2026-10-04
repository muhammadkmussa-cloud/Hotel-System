<?php

declare(strict_types=1);

namespace App\Security;

use Illuminate\Http\Request;

interface CapabilityAuthorizer
{
    /** Check capability AND installation/resource ownership/state; request inputs never grant scope. */
    public function allows(Principal $principal, string $capability, Request $request): bool;
}

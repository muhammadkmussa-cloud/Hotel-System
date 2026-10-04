<?php

declare(strict_types=1);

namespace App\Security;

use Illuminate\Http\Request;

/** Safe default until the verified staff/device adapters are implemented. */
final class DenyAccess implements PrincipalResolver, CapabilityAuthorizer
{
    public function resolve(Request $request): ?Principal { return null; }
    public function allows(Principal $principal, string $capability, Request $request): bool { return false; }
}

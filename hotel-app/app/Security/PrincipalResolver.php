<?php

declare(strict_types=1);

namespace App\Security;

use Illuminate\Http\Request;

interface PrincipalResolver
{
    /** Resolve an active identity from verified credentials, including expiry/revocation checks. */
    public function resolve(Request $request): ?Principal;
}

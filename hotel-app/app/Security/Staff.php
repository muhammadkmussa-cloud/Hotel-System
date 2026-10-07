<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Middleware\RequirePrincipal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Convenience reads of the verified staff principal for controllers and views. */
final class Staff
{
    public static function id(Request $request): ?string
    {
        $principal = $request->attributes->get(RequirePrincipal::ATTRIBUTE);
        if ($principal instanceof SessionPrincipal) {
            return $principal->identifier();
        }

        return null;
    }

    /** Resolve the staff principal without requiring one (for optional staff views). */
    public static function optional(Request $request): ?string
    {
        $id = self::id($request);
        if ($id !== null) {
            return $id;
        }
        $principal = app(PrincipalResolver::class)->resolve($request);
        if ($principal instanceof SessionPrincipal) {
            $request->attributes->set(RequirePrincipal::ATTRIBUTE, $principal);

            return $principal->identifier();
        }

        return null;
    }

    public static function can(Request $request, string $capability): bool
    {
        $principal = $request->attributes->get(RequirePrincipal::ATTRIBUTE);

        return $principal instanceof Principal && app(CapabilityAuthorizer::class)->allows($principal, $capability, $request);
    }

    /** @return list<string> */
    public static function roles(?string $staffId): array
    {
        if ($staffId === null) {
            return [];
        }

        return DB::table('staff_role_grants')->join('roles', 'roles.id', '=', 'staff_role_grants.role_id')
            ->where('staff_role_grants.staff_user_id', $staffId)->pluck('roles.key')->all();
    }

    public static function name(?string $staffId): string
    {
        if ($staffId === null) {
            return '';
        }

        return (string) (DB::table('staff_users')->where('id', $staffId)->value('name') ?? '');
    }
}

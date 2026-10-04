<?php

declare(strict_types=1);

namespace App\Security;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Throwable;

/**
 * Role-based capability checks for staff principals. The resolver already
 * verified the session and active state; this maps capabilities to role grants.
 */
final class StaffCapabilityAuthorizer implements CapabilityAuthorizer
{
    private const CAPABILITY_ROLES = [
        'staff.manage' => ['owner', 'manager'],
        'settings.manage' => ['owner'],
        'devices.manage' => ['owner', 'manager'],
        'visits.manage' => ['waiter', 'manager', 'cashier'],
        'visits.transfer' => ['manager'],
        'visits.close' => ['waiter', 'manager'],
        'catalogue.edit' => ['menu_editor', 'manager', 'owner'],
        'catalogue.publish' => ['manager', 'owner'],
        'availability.manage' => ['kitchen_lead', 'manager'],
        'orders.review' => ['kitchen_lead', 'manager'],
        'orders.adjust' => ['manager'],
        'bills.allocate' => ['waiter', 'cashier', 'manager'],
        'payments.cash' => ['waiter', 'cashier', 'manager'],
        'payments.card' => ['cashier', 'manager'],
        'refunds.request' => ['waiter', 'cashier', 'manager'],
        'refunds.approve' => ['manager', 'owner'],
        'refunds.complete' => ['cashier', 'manager'],
        'cash.reconcile' => ['cashier', 'manager'],
        'kitchen.view' => ['kitchen_staff', 'kitchen_lead', 'manager'],
        'kitchen.update' => ['kitchen_staff', 'kitchen_lead', 'manager'],
        'printing.request' => ['waiter', 'cashier', 'manager', 'kitchen_staff'],
        'reports.view' => ['owner', 'manager', 'auditor', 'cashier'],
        'audit.view' => ['owner', 'auditor', 'manager'],
        'fiscal.manage' => ['owner'],
    ];

    public function __construct(private readonly DatabaseManager $database) {}

    public function allows(Principal $principal, string $capability, Request $request): bool
    {
        if (! $principal instanceof SessionPrincipal) {
            return false;
        }
        $allowed = self::CAPABILITY_ROLES[$capability] ?? [];
        if ($allowed === []) {
            return false;
        }

        try {
            $roles = $this->database->connection('mysql')->table('staff_role_grants')
                ->join('roles', 'roles.id', '=', 'staff_role_grants.role_id')
                ->where('staff_role_grants.staff_user_id', $principal->identifier())
                ->pluck('roles.key')->all();
        } catch (Throwable) {
            return false;
        }

        return array_intersect($roles, $allowed) !== [];
    }
}

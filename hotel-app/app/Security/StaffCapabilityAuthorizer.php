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
        'visits.manage' => ['waiter', 'manager', 'cashier', 'owner'],
        'visits.transfer' => ['manager', 'owner'],
        'visits.close' => ['waiter', 'manager', 'cashier', 'owner'],
        'catalogue.edit' => ['menu_editor', 'manager', 'owner'],
        'catalogue.publish' => ['manager', 'owner'],
        'availability.manage' => ['kitchen_lead', 'manager', 'owner'],
        'orders.review' => ['kitchen_lead', 'manager', 'owner'],
        'orders.adjust' => ['manager', 'owner'],
        'orders.view' => ['waiter', 'cashier', 'manager', 'owner', 'kitchen_lead', 'auditor'],
        'bills.allocate' => ['waiter', 'cashier', 'manager', 'owner'],
        'payments.cash' => ['waiter', 'cashier', 'manager', 'owner'],
        'payments.mpesa' => ['waiter', 'cashier', 'manager', 'owner'],
        'payments.card' => ['cashier', 'manager', 'owner'],
        'refunds.request' => ['waiter', 'cashier', 'manager', 'owner'],
        'refunds.approve' => ['manager', 'owner'],
        'refunds.complete' => ['cashier', 'manager', 'owner'],
        'cash.reconcile' => ['cashier', 'manager', 'owner'],
        'kitchen.view' => ['kitchen_staff', 'kitchen_lead', 'manager', 'owner'],
        'kitchen.update' => ['kitchen_staff', 'kitchen_lead', 'manager', 'owner'],
        'printing.request' => ['waiter', 'cashier', 'manager', 'kitchen_staff', 'kitchen_lead', 'owner'],
        'printing.manage' => ['manager', 'owner'],
        'integrations.manage' => ['owner', 'manager'],
        'backups.manage' => ['owner'],
        'reports.view' => ['owner', 'manager', 'auditor', 'cashier'],
        'audit.view' => ['owner', 'auditor', 'manager'],
        'fiscal.manage' => ['owner', 'manager'],
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

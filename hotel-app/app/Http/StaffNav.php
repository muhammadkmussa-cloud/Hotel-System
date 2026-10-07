<?php

declare(strict_types=1);

namespace App\Http;

use App\Security\Staff;
use Illuminate\Http\Request;

/** Role-aware staff navigation (only links the signed-in role may open). */
final class StaffNav
{
    public const ITEMS = [
        ['Dashboard', '/staff', null],
        ['Tables', '/staff/tables', 'visits.manage'],
        ['Review', '/staff/review', 'orders.review'],
        ['Kitchen', '/kitchen', 'kitchen.view'],
        ['Cashier', '/staff/cashier', 'payments.cash'],
        ['Cash', '/staff/cash', 'payments.cash'],
        ['Refunds', '/staff/refunds', 'refunds.request'],
        ['Menu', '/admin/meals', 'catalogue.edit'],
        ['Availability', '/staff/availability', 'availability.manage'],
        ['Reports', '/admin/reports', 'reports.view'],
        ['Audit', '/admin/audit', 'audit.view'],
        ['Operations', '/admin/operations', 'printing.manage'],
        ['Devices', '/admin/devices', 'devices.manage'],
        ['Staff', '/admin/staff', 'staff.manage'],
        ['Settings', '/admin/settings', 'settings.manage'],
    ];

    /** @return list<array{label:string,href:string,active:bool}> */
    public static function items(Request $request): array
    {
        $out = [];
        $path = '/'.ltrim($request->path(), '/');
        foreach (self::ITEMS as [$label, $href, $cap]) {
            if ($cap !== null && ! Staff::can($request, $cap)) {
                continue;
            }
            $active = $href === '/staff' ? $path === '/staff' : ($path === $href || str_starts_with($path, $href.'/'));
            $out[] = ['label' => $label, 'href' => $href, 'active' => $active];
        }

        return $out;
    }
}

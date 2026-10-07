<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\RequirePrincipal;
use App\Security\Principal;
use App\Support\StaffAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StaffAdminController
{
    public function show(StaffAdmin $admin): View
    {
        return view('admin.staff', [
            'staff' => $admin->list(),
            'roles' => ['owner', 'manager', 'cashier', 'waiter', 'kitchen_lead', 'kitchen_staff', 'menu_editor', 'auditor'],
        ]);
    }

    public function roles(Request $request, StaffAdmin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:grant,revoke'],
            'staff_user_id' => ['required', 'string', 'uuid'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
        ]);

        $actor = $request->attributes->get(RequirePrincipal::ATTRIBUTE);
        $actorId = $actor instanceof Principal ? $actor->identifier() : '';
        $result = $validated['action'] === 'grant'
            ? $admin->grantRoles($validated['staff_user_id'], array_values($validated['roles']), $actorId)
            : $admin->revokeRoles($validated['staff_user_id'], array_values($validated['roles']), $actorId);

        return match ($result) {
            'granted', 'revoked' => redirect('/admin/staff')->with('status', 'Roles updated.'),
            'refused_self' => back()->withInput()->withErrors(['roles' => 'You cannot change your own roles.']),
            'refused_owner' => back()->withInput()->withErrors(['roles' => 'Only an owner can grant the owner role.']),
            'refused_last_owner' => back()->withInput()->withErrors(['roles' => 'The last active owner cannot be removed.']),
            'invalid_input' => back()->withInput()->withErrors(['roles' => 'Check the role selection and try again.']),
            default => back()->withInput()->withErrors(['roles' => 'Role update failed. Private details withheld.']),
        };
    }

    public function deactivate(Request $request, StaffAdmin $admin): RedirectResponse
    {
        $validated = $request->validate(['staff_user_id' => ['required', 'string', 'uuid']]);
        $actor = $request->attributes->get(RequirePrincipal::ATTRIBUTE);
        $actorId = $actor instanceof Principal ? $actor->identifier() : '';

        $result = $admin->deactivate($validated['staff_user_id'], $actorId);

        return match ($result) {
            'deactivated' => redirect('/admin/staff')->with('status', 'Staff member deactivated; their sessions were revoked.'),
            'refused_self' => back()->withErrors(['staff_user_id' => 'You cannot deactivate yourself.']),
            'refused_last_owner' => back()->withErrors(['staff_user_id' => 'The last active owner cannot be deactivated.']),
            'already_inactive' => back()->withErrors(['staff_user_id' => 'That staff member is already inactive.']),
            'invalid_input' => back()->withErrors(['staff_user_id' => 'Unknown staff member.']),
            default => back()->withErrors(['staff_user_id' => 'Deactivation failed. Private details withheld.']),
        };
    }

    public function update(Request $request, StaffAdmin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'staff_user_id' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190'],
        ]);
        $actor = $request->attributes->get(RequirePrincipal::ATTRIBUTE);
        $actorId = $actor instanceof Principal ? $actor->identifier() : '';

        $result = $admin->update($validated['staff_user_id'], $validated['name'], $validated['email'], $actorId);

        return match ($result) {
            'updated' => redirect('/admin/staff')->with('status', 'Staff member updated.'),
            'refused_owner' => back()->withInput()->withErrors(['staff_user_id' => 'Only an owner can edit an owner account.']),
            'duplicate_email' => back()->withInput()->withErrors(['email' => 'That email is already in use.']),
            'invalid_input' => back()->withInput()->withErrors(['email' => 'Check the staff details and try again.']),
            default => back()->withInput()->withErrors(['email' => 'Update failed. Private details withheld.']),
        };
    }

    public function activate(Request $request, StaffAdmin $admin): RedirectResponse
    {
        $validated = $request->validate(['staff_user_id' => ['required', 'string', 'uuid']]);
        $actor = $request->attributes->get(RequirePrincipal::ATTRIBUTE);
        $actorId = $actor instanceof Principal ? $actor->identifier() : '';

        $result = $admin->activate($validated['staff_user_id'], $actorId);

        return match ($result) {
            'activated' => redirect('/admin/staff')->with('status', 'Staff member activated.'),
            'already_active' => back()->withErrors(['staff_user_id' => 'That staff member is already active.']),
            'invalid_input' => back()->withErrors(['staff_user_id' => 'Unknown staff member.']),
            default => back()->withErrors(['staff_user_id' => 'Activation failed. Private details withheld.']),
        };
    }

    public function store(Request $request, StaffAdmin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'name' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
        ]);

        // No self-escalation: only an owner may grant the owner role.
        $roles = array_values($validated['roles']);
        $principal = $request->attributes->get(RequirePrincipal::ATTRIBUTE);
        $actorId = $principal instanceof Principal ? $principal->identifier() : '';
        if (in_array('owner', $roles, true)
            && ! $admin->staffHasRole($actorId, 'owner')
        ) {
            return back()->withInput($request->only('email', 'name'))
                ->withErrors(['roles' => 'Only an owner can grant the owner role.']);
        }

        $result = $admin->create(
            $validated['email'],
            $validated['name'],
            $validated['password'],
            array_values($validated['roles']),
            $actorId,
        );

        return match ($result) {
            'created' => redirect('/admin/staff')->with('status', 'Staff member created.'),
            'refused_owner' => back()->withInput($request->only('email', 'name'))->withErrors(['roles' => 'Only an owner can grant the owner role.']),
            'duplicate_email' => back()->withInput()->withErrors(['email' => 'A staff member with that email already exists.']),
            'invalid_input' => back()->withInput()->withErrors(['email' => 'Check the staff details and try again.']),
            default => back()->withInput()->withErrors(['email' => 'Staff creation failed. Private details withheld.']),
        };
    }
}

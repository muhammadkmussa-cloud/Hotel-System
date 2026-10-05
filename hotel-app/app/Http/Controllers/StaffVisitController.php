<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\RequirePrincipal;
use App\Security\CapabilityAuthorizer;
use App\Security\Principal;
use App\Support\DeviceRegistry;
use App\Support\GuestBindingService;
use App\Support\GuestService;
use App\Support\SecurityAudit;
use App\Support\StaffAdmin;
use App\Support\VisitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class StaffVisitController
{
    public function __construct(
        private readonly VisitService $visits,
        private readonly GuestService $guests,
        private readonly GuestBindingService $bindings,
        private readonly DeviceRegistry $devices,
        private readonly StaffAdmin $staff,
        private readonly SecurityAudit $audit,
    ) {}

    /**
     * S03 — waiter table overview: table status, guest counts and clearly
     * labelled placeholders for balances that arrive with billing (P18/P19).
     */
    public function index(): View
    {
        $overview = $this->visits->overview();

        return view('staff-tables', [
            'tables' => $overview,
            'availableTables' => array_values(array_filter(
                $overview,
                static fn (array $table): bool => $table['visitId'] === null,
            )),
            'occupiedCount' => count(array_filter(
                $overview,
                static fn (array $table): bool => $table['visitId'] !== null,
            )),
        ]);
    }

    /**
     * S04 — visit detail: guests, tablet assignment, transfer and closure.
     */
    public function show(Request $request, string $visitId, CapabilityAuthorizer $authorizer): View
    {
        $visit = $this->visits->visit($visitId);
        abort_if($visit === null, 404);

        $principal = $request->attributes->get(RequirePrincipal::ATTRIBUTE);
        $canTransfer = $principal instanceof Principal && $authorizer->allows($principal, 'visits.transfer', $request);

        $guests = $this->guests->list($visitId);
        $bindings = $this->bindings->forGuests(array_column($guests, 'id'));

        return view('staff-visit', [
            'visit' => $visit,
            'guests' => array_map(
                static fn (array $guest): array => $guest + ['devices' => $bindings[$guest['id']] ?? []],
                $guests,
            ),
            // Only live tablet sessions may take a guest identity.
            'bindableSessions' => $this->devices->activeSessions('tablet'),
            'waiters' => $this->assignableWaiters(),
            'canTransfer' => $canTransfer,
            'transferTables' => array_values(array_filter(
                $this->visits->overview(),
                static fn (array $table): bool => $table['visitId'] === null || $table['tableId'] === $visit['tableId'],
            )),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'string', 'uuid'],
        ]);

        $result = $this->visits->open($validated['table_id'], $this->actorId($request));

        return redirect('/staff/tables')->with('status', $result['created'] ? 'Visit opened.' : 'Table already has an active visit.');
    }

    /**
     * Add the next guest to an open visit. The guest number is chosen by the
     * server; a browser can never pick its own table or guest identity.
     */
    public function storeGuest(Request $request, string $visitId): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $name = trim((string) ($validated['name'] ?? ''));
        $result = $this->guests->add(
            $visitId,
            $this->actorId($request),
            $name === '' ? null : $name,
        );

        $message = match ($result['result']) {
            'created' => $result['label'].' added.',
            'visit_closed' => 'That visit is closed. Reload the table list.',
            'not_found' => 'Visit not found.',
            'limit_reached' => 'This visit already has the maximum number of guests.',
            'duplicate_label' => 'Another guest was added at the same time. Reload and try again.',
            default => 'Guest could not be added. Check the name and try again.',
        };

        return redirect('/staff/visits/'.$visitId)->with('status', $message);
    }

    /**
     * Bind a tablet session to a guest. Both identifiers are server-validated
     * rows; a browser never dictates which guest or table it belongs to.
     */
    public function storeBinding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guest_id' => ['required', 'string', 'uuid'],
            'device_session_id' => ['required', 'string', 'uuid'],
            'ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $result = $this->bindings->bind(
            $validated['guest_id'],
            $validated['device_session_id'],
            $this->actorId($request),
            isset($validated['ttl_minutes']) ? (int) $validated['ttl_minutes'] : null,
        );

        $message = match ($result['result']) {
            'created' => 'Tablet bound to guest.',
            'visit_closed' => 'That visit is closed. Reload the table list.',
            'guest_not_found' => 'Guest not found.',
            'device_session_not_found' => 'Device session not found. Pair the tablet again.',
            'device_session_inactive' => 'That tablet session is no longer active. Pair it again.',
            'wrong_mode' => 'Only tablets can be bound to a guest.',
            'conflict' => 'Another binding was created at the same time. Reload and try again.',
            default => 'Binding failed. Check the tablet and guest, then try again.',
        };

        return redirect($this->visitPathForGuest($validated['guest_id']))->with('status', $message);
    }

    /**
     * Revoke a binding. Orders and bills are untouched; only the device loses
     * the guest identity.
     */
    public function destroyBinding(Request $request, string $bindingId): RedirectResponse
    {
        $validated = $request->validate([
            'visit_id' => ['nullable', 'string', 'uuid'],
        ]);

        $result = $this->bindings->revoke($bindingId, $this->actorId($request));

        $message = match ($result) {
            'revoked' => 'Tablet unbound from the guest.',
            'already_revoked' => 'That tablet is already unbound.',
            'not_found' => 'Binding not found.',
            default => 'Unbinding failed. Reload and try again.',
        };

        return redirect(isset($validated['visit_id']) ? '/staff/visits/'.$validated['visit_id'] : '/staff/tables')
            ->with('status', $message);
    }

    /**
     * P07.10 — replace a tablet: the binding and the old device session both
     * end, so a handed-over device cannot keep reading the guest's identity
     * and any local draft is stale by design.
     */
    public function replaceBinding(Request $request, string $bindingId): RedirectResponse
    {
        $validated = $request->validate([
            'visit_id' => ['nullable', 'string', 'uuid'],
        ]);

        $result = $this->bindings->revokeWithDeviceSession($bindingId, $this->actorId($request));
        if ($result === 'revoked') {
            $this->audit->record('device_revoked', $this->actorId($request), $request->ip(), [
                'reason' => 'tablet_replaced',
            ]);
        }

        $message = match ($result) {
            'revoked' => 'Tablet replaced. The old device lost guest access; pair the new tablet and bind it.',
            'already_revoked' => 'That tablet is already unbound.',
            'not_found' => 'Binding not found.',
            default => 'Replacement failed. Reload and try again.',
        };

        return redirect(isset($validated['visit_id']) ? '/staff/visits/'.$validated['visit_id'] : '/staff/tables')
            ->with('status', $message);
    }

    /**
     * P07.09 — locked table/waiter transfer. Guests, bindings and orders keep
     * their identity; only the table or the owning waiter changes.
     */
    public function transfer(Request $request, string $visitId): RedirectResponse
    {
        $validated = $request->validate([
            'table_id' => ['nullable', 'string', 'uuid'],
            'waiter_id' => ['nullable', 'string', 'uuid'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $result = $this->visits->transfer(
            $visitId,
            $validated['table_id'] ?? null,
            $validated['waiter_id'] ?? null,
            $this->actorId($request),
            isset($validated['expected_version']) ? (int) $validated['expected_version'] : null,
        );

        if ($result['result'] === 'transferred') {
            $this->audit->record('visit_transferred', $this->actorId($request), $request->ip(), [
                'visit_id' => $visitId,
                'table_id' => $validated['table_id'] ?? null,
                'waiter_id' => $validated['waiter_id'] ?? null,
                'reason' => $validated['reason'] ?? null,
            ]);
        }

        $message = match ($result['result']) {
            'transferred' => 'Visit transferred.',
            'version_conflict' => 'Visit changed. Reload and try again.',
            'destination_occupied' => 'That table already has an active visit.',
            'destination_not_found' => 'Unknown table.',
            'waiter_not_found' => 'Unknown or inactive waiter.',
            'visit_closed' => 'That visit is closed.',
            'unchanged' => 'Nothing to transfer: choose a different table or waiter.',
            'not_found' => 'Visit not found.',
            default => 'Transfer failed. Check the destination and try again.',
        };

        return redirect('/staff/visits/'.$visitId)->with('status', $message);
    }

    public function destroy(Request $request, string $visitId): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->visits->close(
            $visitId,
            $this->actorId($request),
            $validated['expected_version'] ?? null,
        );

        if (!$result['success']) {
            return redirect('/staff/visits/'.$visitId)->with('status', 'Visit changed. Reload and try again.');
        }

        return redirect('/staff/tables')->with('status', 'Visit closed.');
    }

    /**
     * The signed-in staff id. `RequirePrincipal` populates only
     * `hotel.principal`; reading any other key yields an empty actor and
     * every service call would then refuse with `invalid_input`.
     */
    private function actorId(Request $request): string
    {
        $principal = $request->attributes->get(RequirePrincipal::ATTRIBUTE);

        return $principal instanceof Principal ? $principal->identifier() : '';
    }

    private function visitPathForGuest(string $guestId): string
    {
        $guest = $this->guests->find($guestId);

        return $guest === null ? '/staff/tables' : '/staff/visits/'.$guest['visitId'];
    }

    /** @return list<array{id:string,name:string}> */
    private function assignableWaiters(): array
    {
        return array_values(array_map(
            static fn (array $user): array => ['id' => (string) $user['id'], 'name' => (string) $user['name']],
            array_filter(
                $this->staff->list(),
                static fn (array $user): bool => $user['active']
                    && array_intersect($user['roles'], ['waiter', 'manager']) !== [],
            ),
        ));
    }
}

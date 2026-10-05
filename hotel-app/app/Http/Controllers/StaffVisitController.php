<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DeviceRegistry;
use App\Support\GuestBindingService;
use App\Support\GuestService;
use App\Support\TableConfig;
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
    ) {}

    public function index(TableConfig $tables): View
    {
        $activeVisits = $this->visits->active();
        $guestsByVisit = $this->guests->forVisits(array_column($activeVisits, 'id'));
        $allGuests = [];
        foreach ($guestsByVisit as $guests) {
            foreach ($guests as $guest) {
                $allGuests[] = $guest;
            }
        }
        $bindingsByGuest = $this->bindings->forGuests(array_column($allGuests, 'id'));

        return view('staff-tables', [
            'tables' => array_values(array_filter(
                $tables->list(),
                static fn (object $table): bool => (bool) $table->active,
            )),
            'activeVisits' => array_map(
                static function (array $visit) use ($guestsByVisit, $bindingsByGuest): array {
                    $guests = $guestsByVisit[$visit['id']] ?? [];

                    return $visit + ['guests' => array_map(
                        static fn (array $guest): array => $guest + ['devices' => $bindingsByGuest[$guest['id']] ?? []],
                        $guests,
                    )];
                },
                $activeVisits,
            ),
            // Only live tablet sessions may take a guest identity.
            'bindableSessions' => $this->devices->activeSessions('tablet'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'string', 'uuid'],
        ]);

        $result = $this->visits->open($validated['table_id'], (string) $request->attributes->get('principal.id', ''));

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

        $result = $this->guests->add(
            $visitId,
            (string) $request->attributes->get('principal.id', ''),
            isset($validated['name']) ? trim((string) $validated['name']) : null,
        );

        $message = match ($result['result']) {
            'created' => $result['label'].' added.',
            'visit_closed' => 'That visit is closed. Reload the table list.',
            'not_found' => 'Visit not found.',
            'limit_reached' => 'This visit already has the maximum number of guests.',
            'duplicate_label' => 'Another guest was added at the same time. Reload and try again.',
            default => 'Guest could not be added. Check the name and try again.',
        };

        return redirect('/staff/tables')->with('status', $message);
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
            (string) $request->attributes->get('principal.id', ''),
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

        return redirect('/staff/tables')->with('status', $message);
    }

    /**
     * Revoke a binding. Orders and bills are untouched; only the device loses
     * the guest identity.
     */
    public function destroyBinding(Request $request, string $bindingId): RedirectResponse
    {
        $result = $this->bindings->revoke($bindingId, (string) $request->attributes->get('principal.id', ''));

        $message = match ($result) {
            'revoked' => 'Tablet unbound from the guest.',
            'already_revoked' => 'That tablet is already unbound.',
            'not_found' => 'Binding not found.',
            default => 'Unbinding failed. Reload and try again.',
        };

        return redirect('/staff/tables')->with('status', $message);
    }

    public function destroy(Request $request, string $visitId): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->visits->close(
            $visitId,
            (string) $request->attributes->get('principal.id', ''),
            $validated['expected_version'] ?? null,
        );

        if (!$result['success']) {
            return redirect('/staff/tables')->with('status', 'Visit changed. Reload and try again.');
        }

        return redirect('/staff/tables')->with('status', 'Visit closed.');
    }
}

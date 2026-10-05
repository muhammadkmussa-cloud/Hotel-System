<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
    ) {}

    public function index(TableConfig $tables): View
    {
        $activeVisits = $this->visits->active();
        $guests = $this->guests->forVisits(array_column($activeVisits, 'id'));

        return view('staff-tables', [
            'tables' => array_values(array_filter(
                $tables->list(),
                static fn (object $table): bool => (bool) $table->active,
            )),
            'activeVisits' => array_map(
                static fn (array $visit): array => $visit + ['guests' => $guests[$visit['id']] ?? []],
                $activeVisits,
            ),
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

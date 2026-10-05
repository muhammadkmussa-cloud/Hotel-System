<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\VisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StaffVisitController
{
    public function index(): View
    {
        $tables = \Illuminate\Support\Facades\DB::table('tables')->orderBy('label')->get(['id', 'label']);
        $activeVisits = \Illuminate\Support\Facades\DB::table('visits')
            ->where('state', 'open')->orderBy('opened_at')->get(['id', 'table_id', 'opened_at']);

        return view('staff-tables', ['tables' => $tables, 'activeVisits' => $activeVisits]);
    }

    public function open(Request $request, VisitService $visits): RedirectResponse
    {
        $validated = $request->validate(['table_id' => ['required', 'string', 'uuid']]);
        $actor = $request->attributes->get('hotel.principal');
        $actorId = $actor instanceof \App\Security\Principal ? $actor->identifier() : '';

        $result = $visits->open($validated['table_id'], $actorId);

        return $result['conflict']
            ? back()->withErrors(['table_id' => 'That table already has an active visit.'])
            : redirect('/staff/tables')->with('status', 'Visit opened.');
    }

    public function close(Request $request, VisitService $visits): RedirectResponse
    {
        $validated = $request->validate(['visit_id' => ['required', 'string', 'uuid']]);
        $result = $visits->close($validated['visit_id']);

        return match ($result) {
            'closed' => redirect('/staff/tables')->with('status', 'Visit closed.'),
            'not_found' => back()->withErrors(['visit_id' => 'Unknown visit.']),
            default => back()->withErrors(['visit_id' => 'Close failed. Private details withheld.']),
        };
    }
}

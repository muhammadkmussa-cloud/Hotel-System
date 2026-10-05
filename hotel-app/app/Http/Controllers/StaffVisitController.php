<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\VisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class StaffVisitController
{
    public function __construct(private readonly VisitService $visits) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'table_id' => ['required', 'string', 'uuid'],
        ])->validate();

        $result = $this->visits->open($validated['table_id'], (string) $request->attributes->get('principal.id', ''));

        return redirect('/staff/tables')->with('status', $result['created'] ? 'Visit opened.' : 'Table already has an active visit.');
    }

    public function destroy(Request $request, string $visitId): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ])->validate();

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

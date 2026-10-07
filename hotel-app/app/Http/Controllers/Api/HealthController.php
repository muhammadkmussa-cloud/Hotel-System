<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController
{
    public function live(Request $request): JsonResponse
    {
        return ApiResponse::success($request, ['status' => 'live']);
    }

    public function ready(Request $request): JsonResponse
    {
        $checks = ['database' => false, 'migrations' => false, 'storage' => is_writable(storage_path('app'))];
        try {
            DB::select('select 1');
            $checks['database'] = true;
            $checks['migrations'] = DB::table('migrations')->count() >= 26;
        } catch (Throwable) {
        }
        $jobs = DB::table('system_heartbeats')->where('name', 'job_runner')->value('last_seen_at');
        $checks['jobRunnerSeenSecondsAgo'] = $jobs ? time() - strtotime($jobs.' UTC') : null;
        $ok = $checks['database'] && $checks['migrations'] && $checks['storage'];
        if (! $ok) {
            return ApiResponse::problem($request, 503, 'NOT_READY', 'The application is not ready.', [], ['checks' => $checks]);
        }

        return ApiResponse::success($request, ['status' => 'ready', 'checks' => $checks]);
    }
}

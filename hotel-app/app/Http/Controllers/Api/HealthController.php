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
        return ApiResponse::success($request, ['status' => 'ok']);
    }

    public function ready(Request $request): JsonResponse
    {
        $database = false;
        $migrations = false;
        $storage = is_writable(storage_path('app'));
        try {
            DB::select('select 1');
            $database = true;
            $required = array_map(
                static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
                glob(database_path('migrations/*.php')) ?: [],
            );
            $applied = DB::table('migrations')->pluck('migration')->all();
            $migrations = $required !== [] && array_diff($required, $applied) === [];
        } catch (Throwable) {
            // Readiness is intentionally redacted; detailed dependency state
            // remains on the separately authorized operations screen.
        }
        if (! ($database && $migrations && $storage)) {
            return ApiResponse::problem($request, 503, 'NOT_READY', 'The application is not ready.');
        }

        return ApiResponse::success($request, ['status' => 'ready']);
    }
}

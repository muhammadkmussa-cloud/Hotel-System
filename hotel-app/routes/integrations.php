<?php

declare(strict_types=1);

use App\Http\Controllers\IntegrationController;
use Illuminate\Support\Facades\Route;

// No session/CSRF: these are authenticated by secret path token or bearer token.
Route::post('/callbacks/mpesa/{token}', [IntegrationController::class, 'mpesaCallback'])->where('token', '[A-Za-z0-9_\-]{16,128}');
Route::get('/bridge/v1/print-jobs/lease', [IntegrationController::class, 'lease']);
Route::post('/bridge/v1/print-jobs/{jobId}/report', [IntegrationController::class, 'report']);

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\KioskController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\TableController;
use Illuminate\Support\Facades\Route;

// Mounted under /api/v1 with session, CSRF (X-CSRF-TOKEN), rate limits and strict JSON.

Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/events', EventsController::class);

// Customer tablet: the guest is derived from the device's live binding.
Route::middleware(['device:tablet', 'guest'])->prefix('table')->group(function (): void {
    Route::get('/context', [TableController::class, 'context']);
    Route::get('/menu', [TableController::class, 'menu']);
    Route::get('/meals/{mealId}', [TableController::class, 'meal']);
    Route::get('/cart', [TableController::class, 'cart']);
    Route::post('/cart/lines', [TableController::class, 'addLine']);
    Route::patch('/cart/lines/{lineId}', [TableController::class, 'updateLine']);
    Route::delete('/cart/lines/{lineId}', [TableController::class, 'removeLine']);
    Route::post('/cart/accept-changes', [TableController::class, 'acceptChanges']);
    Route::post('/orders', [TableController::class, 'submit']);
    Route::get('/orders', [TableController::class, 'orders']);
    Route::get('/bill', [TableController::class, 'bill']);
    Route::get('/receipts/{checkoutId}', [TableController::class, 'receipt']);
    Route::post('/service-requests', [TableController::class, 'serviceRequest']);
    Route::post('/share-proposals', [TableController::class, 'shareProposal']);
    Route::post('/checkouts', [TableController::class, 'startCheckout']);
    Route::post('/checkouts/{checkoutId}/cancel', [TableController::class, 'cancelCheckout']);
    Route::post('/checkouts/{checkoutId}/mpesa-attempts', [TableController::class, 'mpesa']);
    Route::get('/payment-attempts/{attemptId}', [TableController::class, 'attempt']);
});

// Kiosk: anonymous ordering bound to the kiosk device session.
Route::middleware(['device:kiosk'])->prefix('kiosk')->group(function (): void {
    Route::post('/sessions', [KioskController::class, 'start']);
    Route::get('/menu', [KioskController::class, 'menu']);
    Route::get('/meals/{mealId}', [KioskController::class, 'meal']);
    Route::get('/cart', [KioskController::class, 'cart']);
    Route::post('/cart/lines', [KioskController::class, 'addLine']);
    Route::patch('/cart/lines/{lineId}', [KioskController::class, 'updateLine']);
    Route::delete('/cart/lines/{lineId}', [KioskController::class, 'removeLine']);
    Route::post('/cart/accept-changes', [KioskController::class, 'acceptChanges']);
    Route::post('/orders', [KioskController::class, 'submit']);
    Route::get('/status', [KioskController::class, 'status']);
    Route::post('/mpesa-attempts', [KioskController::class, 'mpesa']);
    Route::post('/pay-at-cashier', [KioskController::class, 'payAtCashier']);
    Route::post('/cancel', [KioskController::class, 'cancel']);
});

Route::middleware(['staff_or_device:kitchen.view,kitchen'])->group(function (): void {
    Route::get('/kitchen/tasks', [KitchenController::class, 'tasks']);
    Route::post('/kitchen/tickets/{ticketId}/transitions', [KitchenController::class, 'transition']);
});
Route::get('/collection', [KitchenController::class, 'collection'])->middleware('staff_or_device:kitchen.view,collection,kitchen');

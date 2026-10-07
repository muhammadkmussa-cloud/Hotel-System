<?php

declare(strict_types=1);

use App\Http\Controllers\SetupController;
use App\Http\Controllers\StaffSignInController;
use App\Http\Controllers\StaffSignOutController;
use App\Http\Controllers\DeviceAdminController;
use App\Http\Controllers\StaffVisitController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\HotelSettingsController;
use App\Http\Controllers\StaffAdminController;
use App\Http\Controllers\StaffUnlockController;
use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Staff\CashierController;
use App\Http\Controllers\Staff\MoneyController;
use App\Http\Controllers\Staff\ServiceController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::get('/staff/sign-in', [StaffSignInController::class, 'show'])->name('staff.sign-in');
Route::post('/staff/sign-in', [StaffSignInController::class, 'store'])->middleware('limit:login,email')->name('staff.sign-in.store');
Route::post('/staff/sign-out', [StaffSignOutController::class, 'store'])->name('staff.sign-out');
Route::get('/staff/lock', [StaffUnlockController::class, 'show'])->name('staff.lock');
Route::post('/staff/unlock', [StaffUnlockController::class, 'store'])->middleware('limit:login,staff_user_id')->name('staff.unlock');

Route::get('/admin/staff', [StaffAdminController::class, 'show'])->middleware('capability:staff.manage')->name('admin.staff');
Route::post('/admin/staff', [StaffAdminController::class, 'store'])->middleware('capability:staff.manage')->name('admin.staff.store');
Route::post('/admin/staff/roles', [StaffAdminController::class, 'roles'])->middleware('capability:staff.manage')->name('admin.staff.roles');
Route::post('/admin/staff/deactivate', [StaffAdminController::class, 'deactivate'])->middleware('capability:staff.manage')->name('admin.staff.deactivate');
Route::post('/admin/staff/update', [StaffAdminController::class, 'update'])->middleware('capability:staff.manage')->name('admin.staff.update');
Route::post('/admin/staff/activate', [StaffAdminController::class, 'activate'])->middleware('capability:staff.manage')->name('admin.staff.activate');

Route::get('/admin/settings', [HotelSettingsController::class, 'show'])->middleware('capability:settings.manage')->name('admin.settings');
Route::post('/admin/settings', [HotelSettingsController::class, 'store'])->middleware('capability:settings.manage')->name('admin.settings.store');
Route::post('/admin/settings/receipt', [HotelSettingsController::class, 'storeReceipt'])->middleware('capability:settings.manage')->name('admin.settings.receipt');
Route::post('/admin/settings/tables', [HotelSettingsController::class, 'storeTables'])->middleware('capability:settings.manage')->name('admin.settings.tables');
Route::post('/admin/settings/stations', [HotelSettingsController::class, 'storeStations'])->middleware('capability:settings.manage')->name('admin.settings.stations');
Route::get('/device/pair', [DevicePairingController::class, 'show'])->name('device.pair');
Route::post('/device/pair', [DevicePairingController::class, 'store'])->middleware('limit:pairing')->name('device.pair.store');
Route::get('/admin/devices', [DeviceAdminController::class, 'show'])->middleware('capability:devices.manage')->name('admin.devices');
Route::post('/admin/devices/revoke', [DeviceAdminController::class, 'revoke'])->middleware('capability:devices.manage')->name('admin.devices.revoke');
Route::post('/admin/devices/enroll', [DeviceAdminController::class, 'enroll'])->middleware('capability:devices.manage');
Route::post('/admin/devices/{deviceId}/code', [DeviceAdminController::class, 'code'])->middleware('capability:devices.manage')->whereUuid('deviceId');
Route::post('/admin/device-sessions/{sessionId}/revoke', [DeviceAdminController::class, 'revokeSession'])->middleware('capability:devices.manage')->whereUuid('sessionId');
Route::post('/device/forget', [DevicePairingController::class, 'forget']);

Route::get('/staff/tables', [StaffVisitController::class, 'index'])->middleware('capability:visits.manage')->name('staff.tables');
Route::get('/staff/visits/{visitId}', [StaffVisitController::class, 'show'])->middleware('capability:visits.manage')->name('staff.visits.show');
Route::post('/staff/visits/{visitId}/transfers', [StaffVisitController::class, 'transfer'])->middleware('capability:visits.transfer')->name('staff.visits.transfers');
Route::post('/staff/guest-bindings/{bindingId}/replace', [StaffVisitController::class, 'replaceBinding'])->middleware('capability:visits.manage')->name('staff.guest-bindings.replace');
Route::post('/staff/visits/open', [StaffVisitController::class, 'store'])->middleware('capability:visits.manage')->name('staff.visits.open');
Route::post('/staff/visits/{visitId}/close', [StaffVisitController::class, 'destroy'])->middleware('capability:visits.manage')->name('staff.visits.close');
Route::post('/staff/visits/{visitId}/guests', [StaffVisitController::class, 'storeGuest'])->middleware('capability:visits.manage')->name('staff.visits.guests.store');
Route::post('/staff/guest-bindings', [StaffVisitController::class, 'storeBinding'])->middleware('capability:visits.manage')->name('staff.guest-bindings.store');
Route::post('/staff/guest-bindings/{bindingId}/revoke', [StaffVisitController::class, 'destroyBinding'])->middleware('capability:visits.manage')->name('staff.guest-bindings.revoke');
Route::post('/admin/settings/printers', [HotelSettingsController::class, 'storePrinters'])->middleware('capability:settings.manage')->name('admin.settings.printers');

Route::view('/preview/components', 'preview.components');

foreach (['customer', 'kiosk', 'staff', 'kitchen', 'collection', 'cashier', 'bill'] as $mode) {
    Route::view('/preview/'.$mode, 'preview.'.$mode);
}

// ----- Device apps -----
Route::get('/table', [AppController::class, 'table'])->middleware(['device:tablet', 'guest']);
Route::get('/table/waiting', [AppController::class, 'waiting']);
Route::get('/kiosk', [AppController::class, 'kiosk'])->middleware('device:kiosk');
Route::get('/kitchen', [AppController::class, 'kitchen'])->middleware('staff_or_device:kitchen.view,kitchen');
Route::get('/collection', [AppController::class, 'collection'])->middleware('staff_or_device:kitchen.view,collection,kitchen');

// ----- Staff operations -----
Route::get('/staff', [ServiceController::class, 'dashboard'])->middleware('principal');
Route::middleware('capability:orders.review')->group(function (): void {
    Route::get('/staff/review', [ServiceController::class, 'review']);
    Route::post('/staff/review/{submissionId}/approve', [ServiceController::class, 'approve'])->whereUuid('submissionId');
    Route::post('/staff/review/{submissionId}/decline', [ServiceController::class, 'decline'])->whereUuid('submissionId');
});
Route::post('/staff/service-requests/{requestId}/{state}', [ServiceController::class, 'progressRequest'])->middleware('capability:visits.manage')->whereUuid('requestId')->whereIn('state', ['acknowledged', 'resolved']);
Route::post('/staff/share-proposals/{proposalId}/decide', [ServiceController::class, 'decideShare'])->middleware('capability:bills.allocate')->whereUuid('proposalId');
Route::post('/staff/charges/{chargeId}/split', [ServiceController::class, 'split'])->middleware('capability:bills.allocate')->whereUuid('chargeId');
Route::post('/staff/allocations/{allocationId}/discount', [ServiceController::class, 'discount'])->middleware('capability:orders.adjust')->whereUuid('allocationId');
Route::post('/staff/order-items/{itemId}/cancel', [ServiceController::class, 'cancelItem'])->middleware('capability:orders.adjust')->whereUuid('itemId');
Route::get('/staff/guests/{guestId}/bill', [ServiceController::class, 'printBill'])->middleware('capability:visits.manage')->whereUuid('guestId');

Route::middleware('capability:payments.cash')->group(function (): void {
    Route::get('/staff/cashier', [CashierController::class, 'index']);
    Route::post('/staff/guests/{guestId}/checkout', [CashierController::class, 'startGuest'])->whereUuid('guestId');
    Route::post('/staff/kiosk-orders/{kioskOrderId}/checkout', [CashierController::class, 'startKiosk'])->whereUuid('kioskOrderId');
    Route::get('/staff/checkouts/{checkoutId}', [CashierController::class, 'show'])->whereUuid('checkoutId');
    Route::get('/staff/checkouts/{checkoutId}/poll', [CashierController::class, 'poll'])->whereUuid('checkoutId');
    Route::post('/staff/checkouts/{checkoutId}/cash', [CashierController::class, 'cash'])->whereUuid('checkoutId');
    Route::post('/staff/checkouts/{checkoutId}/cancel', [CashierController::class, 'cancel'])->whereUuid('checkoutId');
    Route::get('/staff/receipts/{checkoutId}', [CashierController::class, 'receipt'])->whereUuid('checkoutId');
    Route::post('/staff/receipts/{checkoutId}/reprint', [CashierController::class, 'reprint'])->whereUuid('checkoutId');
    Route::post('/staff/payment-attempts/{attemptId}/resolve', [CashierController::class, 'resolveAttempt'])->whereUuid('attemptId');
});
Route::post('/staff/checkouts/{checkoutId}/card', [CashierController::class, 'card'])->middleware('capability:payments.card')->whereUuid('checkoutId');
Route::post('/staff/checkouts/{checkoutId}/mpesa', [CashierController::class, 'mpesa'])->middleware('capability:payments.mpesa')->whereUuid('checkoutId');

Route::get('/staff/cash', [MoneyController::class, 'cash'])->middleware('capability:payments.cash');
Route::post('/staff/cash/handovers', [MoneyController::class, 'propose'])->middleware('capability:payments.cash');
Route::middleware('capability:cash.reconcile')->group(function (): void {
    Route::post('/staff/cash/drawers', [MoneyController::class, 'openDrawer']);
    Route::post('/staff/cash/drawers/{drawerId}/close', [MoneyController::class, 'closeDrawer'])->whereUuid('drawerId');
    Route::post('/staff/cash/handovers/{handoverId}/decide', [MoneyController::class, 'decide'])->whereUuid('handoverId');
});
Route::get('/staff/refunds', [MoneyController::class, 'refunds'])->middleware('capability:refunds.request');
Route::post('/staff/payments/{paymentId}/refunds', [MoneyController::class, 'requestRefund'])->middleware('capability:refunds.request')->whereUuid('paymentId');
Route::post('/staff/refunds/{refundId}/decide', [MoneyController::class, 'decideRefund'])->middleware('capability:refunds.approve')->whereUuid('refundId');
Route::post('/staff/refunds/{refundId}/complete', [MoneyController::class, 'completeRefund'])->middleware('capability:refunds.complete')->whereUuid('refundId');

Route::get('/staff/availability', [CatalogueController::class, 'availability'])->middleware('capability:availability.manage');
Route::post('/admin/meals/{mealId}/availability', [CatalogueController::class, 'setAvailability'])->middleware('capability:availability.manage')->whereUuid('mealId');

// ----- Catalogue -----
Route::get('/admin/meals', [CatalogueController::class, 'meals'])->middleware('capability:catalogue.edit');
Route::post('/admin/meals', [CatalogueController::class, 'storeMeal'])->middleware('capability:catalogue.edit');
Route::post('/admin/categories', [CatalogueController::class, 'saveCategory'])->middleware('capability:catalogue.edit');
Route::get('/admin/meals/{mealId}', [CatalogueController::class, 'meal'])->middleware('principal')->whereUuid('mealId');
Route::post('/admin/meals/{mealId}', [CatalogueController::class, 'updateMeal'])->middleware('capability:catalogue.edit')->whereUuid('mealId');
Route::post('/admin/meals/{mealId}/ingredients', [CatalogueController::class, 'mealIngredients'])->middleware('capability:catalogue.edit')->whereUuid('mealId');
Route::post('/admin/meals/{mealId}/approve', [CatalogueController::class, 'approveRecipe'])->middleware('capability:orders.review')->whereUuid('mealId');
Route::post('/admin/meals/{mealId}/publish', [CatalogueController::class, 'publishMeal'])->middleware('capability:catalogue.publish')->whereUuid('mealId');
Route::post('/admin/meals/{mealId}/unpublish', [CatalogueController::class, 'unpublishMeal'])->middleware('capability:catalogue.publish')->whereUuid('mealId');
Route::post('/admin/meals/{mealId}/archive', [CatalogueController::class, 'archiveMeal'])->middleware('capability:catalogue.publish')->whereUuid('mealId');
Route::middleware('capability:catalogue.edit')->group(function (): void {
    Route::get('/admin/ingredients', [CatalogueController::class, 'ingredientsIndex']);
    Route::post('/admin/ingredients', [CatalogueController::class, 'storeIngredient']);
    Route::get('/admin/ingredients/{ingredientId}', [CatalogueController::class, 'ingredient'])->whereUuid('ingredientId');
    Route::post('/admin/ingredients/{ingredientId}', [CatalogueController::class, 'updateIngredient'])->whereUuid('ingredientId');
    Route::post('/admin/ingredients/{ingredientId}/components', [CatalogueController::class, 'ingredientComponents'])->whereUuid('ingredientId');
    Route::post('/admin/ingredients/{ingredientId}/archive', [CatalogueController::class, 'archiveIngredient'])->whereUuid('ingredientId');
    Route::get('/admin/media', [CatalogueController::class, 'mediaIndex']);
    Route::post('/admin/media', [CatalogueController::class, 'uploadMedia'])->middleware('media.upload');
    Route::get('/admin/media/{mediaId}', [CatalogueController::class, 'mediaItem'])->whereUuid('mediaId');
    Route::post('/admin/media/{mediaId}', [CatalogueController::class, 'updateMedia'])->whereUuid('mediaId');
    Route::post('/admin/media/{mediaId}/approve', [CatalogueController::class, 'approveMedia'])->whereUuid('mediaId');
    Route::post('/admin/media/{mediaId}/state', [CatalogueController::class, 'mediaState'])->whereUuid('mediaId');
});

// ----- Reports, audit, operations -----
Route::get('/admin/reports', [OperationsController::class, 'reports'])->middleware('capability:reports.view');
Route::get('/admin/reports/export/{kind}', [OperationsController::class, 'export'])->middleware('capability:reports.view')->whereIn('kind', ['payments', 'sales']);
Route::get('/admin/audit', [OperationsController::class, 'audit'])->middleware('capability:audit.view');
Route::get('/admin/operations', [OperationsController::class, 'operations'])->middleware('capability:integrations.manage');
Route::post('/admin/operations/jobs/run', [OperationsController::class, 'runJobs'])->middleware('capability:integrations.manage');
Route::post('/admin/print-jobs/{jobId}/reprint', [OperationsController::class, 'reprint'])->middleware('capability:printing.manage')->whereUuid('jobId');
Route::post('/admin/print-jobs/{jobId}/mark', [OperationsController::class, 'markPrintJob'])->middleware('capability:printing.manage')->whereUuid('jobId');
Route::post('/admin/fiscal/{documentId}/retry', [OperationsController::class, 'retryFiscal'])->middleware('capability:fiscal.manage')->whereUuid('documentId');
Route::post('/admin/backups', [OperationsController::class, 'createBackup'])->middleware('capability:backups.manage');
Route::get('/admin/backups/{name}', [OperationsController::class, 'downloadBackup'])->middleware('capability:backups.manage');
Route::post('/admin/settings/commerce', [OperationsController::class, 'commerce'])->middleware('capability:settings.manage');

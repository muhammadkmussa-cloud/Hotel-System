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
Route::post('/device/pair', [DevicePairingController::class, 'store'])->name('device.pair.store');
Route::get('/admin/devices', [DeviceAdminController::class, 'show'])->middleware('capability:devices.manage')->name('admin.devices');
Route::post('/admin/devices/revoke', [DeviceAdminController::class, 'revoke'])->middleware('capability:devices.manage')->name('admin.devices.revoke');

Route::get('/staff/tables', [StaffVisitController::class, 'index'])->middleware('capability:visits.manage')->name('staff.tables');
Route::post('/staff/visits/open', [StaffVisitController::class, 'store'])->middleware('capability:visits.manage')->name('staff.visits.open');
Route::post('/staff/visits/{visitId}/close', [StaffVisitController::class, 'destroy'])->middleware('capability:visits.manage')->name('staff.visits.close');
Route::post('/staff/visits/{visitId}/guests', [StaffVisitController::class, 'storeGuest'])->middleware('capability:visits.manage')->name('staff.visits.guests.store');
Route::post('/admin/settings/printers', [HotelSettingsController::class, 'storePrinters'])->middleware('capability:settings.manage')->name('admin.settings.printers');

Route::view('/preview/components', 'preview.components');

foreach (['customer', 'kiosk', 'staff', 'kitchen', 'collection', 'cashier', 'bill'] as $mode) {
    Route::view('/preview/'.$mode, 'preview.'.$mode);
}

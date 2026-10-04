<?php

declare(strict_types=1);

use App\Http\Controllers\SetupController;
use App\Http\Controllers\StaffSignInController;
use App\Http\Controllers\StaffSignOutController;
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

Route::view('/preview/components', 'preview.components');

foreach (['customer', 'kiosk', 'staff', 'kitchen', 'collection', 'cashier', 'bill'] as $mode) {
    Route::view('/preview/'.$mode, 'preview.'.$mode);
}

<?php

use App\Enums\ShiftStatus;
use App\Http\Controllers\Api\RentalSessionController;
use App\Http\Controllers\Api\UnitController as ApiUnitController;
use App\Http\Controllers\Owner\OwnerDashboardController;
use App\Http\Controllers\Owner\ProductMasterController;
use App\Http\Controllers\Owner\RatePackageController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\UnitMasterController;
use App\Http\Controllers\Owner\UserController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UnitGridController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
| Halaman awal harus(role) aware supaya tidak memantulkan user yang sudah
| login ke halaman `login`: Owner -> dashboard, kasir dengan shift aktif ->
| grid unit, kasir tanpa shift -> input modal awal.
*/
Route::get('/', function (Request $request) {
    if (! $request->user()) {
        return redirect()->route('login');
    }

    if ($request->user()->isOwner()) {
        return redirect()->route('owner.dashboard');
    }

    $hasOpenShift = $request->user()
        ->shifts()
        ->where('status', ShiftStatus::OPEN)
        ->exists();

    return redirect()->route($hasOpenShift ? 'units.index' : 'shift.start');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated — KASIR (dan's Owner untuk halaman grid)
| Middleware `shift.active` mengunci Grid Unit / POS / Rekonsiliasi sampai
| kasir menginput Modal Awal Kas.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Gatekeeper shift
    Route::get('/shift/start', [ShiftController::class, 'start'])->name('shift.start');
    Route::post('/shift/start', [ShiftController::class, 'store'])->name('shift.start.store');

    // Halaman yang WAJIB punya shift aktif
    Route::middleware('shift.active')->group(function () {
        // Grid Monitoring Unit real-time
        Route::get('/dashboard/units', [UnitGridController::class, 'index'])->name('units.index');

        // Kasir / POS Billing
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/{session}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');

        // Rekonsiliasi akhir shift
        Route::get('/shift/end', [ShiftController::class, 'end'])->name('shift.end');
        Route::post('/shift/end', [ShiftController::class, 'close'])->name('shift.end.store');
    });

    Route::get('/shift/{shift}/summary', [ShiftController::class, 'summary'])->name('shift.summary');

    // Fallback berdasar role
    Route::get('/dashboard', function () {
        return auth()->user()->isOwner()
            ? redirect()->route('owner.dashboard')
            : redirect()->route('units.index');
    })->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| JSON API — consumed by Alpine.js di halaman grid unit & POS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'shift.active'])->prefix('api')->name('api.')->group(function () {
    Route::get('/units', [ApiUnitController::class, 'index'])->name('units.index');
    Route::get('/units/{unit}', [ApiUnitController::class, 'show'])->name('units.show');
    Route::get('/units/{unit}/meta', [ApiUnitController::class, 'meta'])->name('units.meta');

    Route::post('/sessions', [RentalSessionController::class, 'store'])->name('sessions.store');
    Route::get('/sessions/{rentalSession}', [RentalSessionController::class, 'show'])->name('sessions.show');
    Route::post('/sessions/{rentalSession}/extend', [RentalSessionController::class, 'extend'])->name('sessions.extend');
    Route::post('/sessions/{rentalSession}/items', [RentalSessionController::class, 'storeItem'])->name('sessions.items.store');
    Route::post('/sessions/{rentalSession}/complete', [RentalSessionController::class, 'complete'])->name('sessions.complete');
    Route::post('/sessions/{rentalSession}/cancel', [RentalSessionController::class, 'cancel'])->name('sessions.cancel');
});

/*
|--------------------------------------------------------------------------
| OWNER — dashboard analitik, laporan, audit log, CRUD master
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');

    // Laporan keuangan
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/audit-log', [ReportController::class, 'auditLog'])->name('audit-log');

    // Master Unit
    Route::resource('units', UnitMasterController::class)->except('show');
    Route::patch('units/{unit}/status', [UnitMasterController::class, 'toggleStatus'])->name('units.status');

    // Master Tarif Paket
    Route::resource('rate-packages', RatePackageController::class)->except('show');

    // Master Produk F&B
    Route::resource('products', ProductMasterController::class)->except('show');
    Route::patch('products/{product}/stock', [ProductMasterController::class, 'adjustStock'])->name('products.stock');

    // Manajemen user kasir
    Route::resource('users', UserController::class)->except('show');
});

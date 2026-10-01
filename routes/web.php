<?php

use App\Http\Controllers\Api\RentalSessionController;
use App\Http\Controllers\Api\UnitController as ApiUnitController;
use App\Http\Controllers\CustomerBookingController;
use App\Http\Controllers\CustomerDisplayController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\Owner\OwnerDashboardController;
use App\Http\Controllers\Owner\ProductMasterController;
use App\Http\Controllers\Owner\RatePackageController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\UnitMasterController;
use App\Http\Controllers\Owner\UserController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UnitGridController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
| Tampilan Pelanggan (Public View) tanpa login: landing page di `/` dan live
| monitor layar TV di `/display`. Untuk user yang sudah login, `index()` tetap
| mengalihkan ke halaman internal sesuai role (Owner -> dashboard, kasir ->
| grid unit / gatekeeper shift, pelanggan -> dashboard booking).
*/
Route::get('/', [CustomerDisplayController::class, 'index'])->name('customer.home');
Route::get('/display', [CustomerDisplayController::class, 'liveDisplay'])->name('customer.display');
Route::get('/display/data', [CustomerDisplayController::class, 'displayData'])->name('customer.display.data');

// Cek status booking dari perangkat mana pun (butuh kode + nomor WhatsApp).
Route::get('/cek-status', [CustomerDisplayController::class, 'checkStatus'])->name('booking.check');
Route::post('/cek-status', [CustomerDisplayController::class, 'searchStatus'])
    ->middleware('throttle:10,1')
    ->name('booking.check.search');

/*
|--------------------------------------------------------------------------
| PELANGGAN — wajib login (akun CustomerArea, daftar lewat nomor WhatsApp)
|--------------------------------------------------------------------------
| Nama dan nomor WhatsApp diambil dari akun, jadi kasir selalu punya kontak
| yang bisa dihubungi. Booking yang dibuat langsung mengunci slot jamnya
| supaya pelanggan lain tidak bisa memesan jam yang sama.
*/
Route::middleware(['auth', 'role.customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/', [CustomerBookingController::class, 'index'])->name('dashboard');

    Route::post('/bookings', [CustomerBookingController::class, 'store'])
        ->middleware('throttle:12,1')
        ->name('bookings.store');

    Route::get('/bookings/status', [CustomerBookingController::class, 'status'])
        ->middleware('throttle:60,1')
        ->name('bookings.status');
});

/*
|--------------------------------------------------------------------------
| Pemesanan menu via barcode — PUBLIK (tanpa auth)
|--------------------------------------------------------------------------
| Pelanggan memindai barcode produk dengan kamera HP di halaman `/order`.
| Pesanan identified lewat `orders.token` yang disimpan di session browser;
| endpoint di bawah menolak token yang bukan milik session tersebut.
*/
Route::prefix('order')->name('customer.order.')->group(function () {
    Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
    Route::post('/', [CustomerOrderController::class, 'store'])->name('store');

    Route::get('/{token}', [CustomerOrderController::class, 'show'])->name('show');
    Route::post('/{token}/scan', [CustomerOrderController::class, 'scan'])->name('scan');
    Route::post('/{token}/place', [CustomerOrderController::class, 'place'])->name('place');
    Route::patch('/{token}/items/{item}', [CustomerOrderController::class, 'updateItem'])->name('items.update');
    Route::delete('/{token}/items/{item}', [CustomerOrderController::class, 'destroyItem'])->name('items.destroy');
});

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

        // Daftar Booking reservasi online
        Route::get('/pos/bookings', [PosController::class, 'bookings'])->name('pos.bookings');
        Route::post('/pos/bookings/{booking}/confirm', [PosController::class, 'confirmBooking'])->name('pos.bookings.confirm');
        Route::post('/pos/bookings/{booking}/cancel', [PosController::class, 'cancelBooking'])->name('pos.bookings.cancel');
        Route::post('/pos/bookings/{booking}/complete', [PosController::class, 'completeBooking'])->name('pos.bookings.complete');

        // Pesanan menu hasil scan barcode pelanggan
        Route::get('/pos/orders', [PosController::class, 'orders'])->name('pos.orders');
        Route::post('/pos/orders/{order}/settle', [PosController::class, 'settleOrder'])->name('pos.orders.settle');
        Route::post('/pos/orders/{order}/cancel', [PosController::class, 'cancelOrder'])->name('pos.orders.cancel');

        // Rekonsiliasi akhir shift
        Route::get('/shift/end', [ShiftController::class, 'end'])->name('shift.end');
        Route::post('/shift/end', [ShiftController::class, 'close'])->name('shift.end.store');
    });

    Route::get('/shift/{shift}/summary', [ShiftController::class, 'summary'])->name('shift.summary');

    // Fallback berdasar role
    Route::get('/dashboard', function () {
        $user = auth()->user();

        if ($user->isCustomer()) {
            return redirect()->route('customer.dashboard');
        }

        return $user->isOwner()
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
    // `products/labels` WAJIB didaftarkan sebelum resource route, kalau
    // tidak akan tertangkap wildcard `products/{product}` dan 404.
    Route::get('products/labels', [ProductMasterController::class, 'labels'])->name('products.labels');
    Route::resource('products', ProductMasterController::class)->except('show');
    Route::patch('products/{product}/stock', [ProductMasterController::class, 'adjustStock'])->name('products.stock');

    // Manajemen user kasir
    Route::resource('users', UserController::class)->except('show');
});

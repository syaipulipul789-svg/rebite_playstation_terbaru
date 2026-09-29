<?php

namespace Tests;

use App\Enums\ProductCategory;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\RatePackage;
use App\Models\RentalSession;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function makeOwner(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Owner Rebite',
            'username' => 'owner',
            'email' => 'owner@rebite.test',
            'password' => 'password',
            'role' => UserRole::OWNER,
            'is_active' => true,
        ], $attributes));
    }

    protected function makeCashier(string $username = 'kasir01', array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($username),
            'username' => $username,
            'email' => $username.'@rebite.test',
            'password' => 'password',
            'role' => UserRole::KASIR,
            'is_active' => true,
        ], $attributes));
    }

    protected function makeUnit(array $attributes = []): Unit
    {
        static $sequence = 0;
        $sequence++;

        return Unit::create(array_merge([
            'code' => 'PS5-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'name' => 'PS5 Reguler '.$sequence,
            'type' => 'PS5',
            'hourly_rate' => 12000,
            'status' => UnitStatus::READY,
        ], $attributes));
    }

    protected function makePackage(array $attributes = []): RatePackage
    {
        return RatePackage::create(array_merge([
            'name' => 'Paket 1 Jam',
            'duration_minutes' => 60,
            'price' => 10000,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    protected function makeProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Indomie Goreng',
            'category' => ProductCategory::SNACK,
            'price' => 8000,
            'stock' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ], $attributes));
    }

    protected function makeOpenShift(User $cashier, float $startingCash = 200000): Shift
    {
        return Shift::create([
            'user_id' => $cashier->id,
            'start_time' => now()->subHours(2),
            'starting_cash' => $startingCash,
            'system_cash_revenue' => 0,
            'system_qris_revenue' => 0,
            'status' => ShiftStatus::OPEN,
        ]);
    }

    /**
     * Jalankan satu siklus sewa penuh (mulai -> tambah F&B opsional -> bayar)
     * lalu kembalikan model RentalSession yang sudah selesai.
     *
     * Dipakai test rekap shift karena ShiftService::close() menghitung ulang
     * pendapatan dari sesi nyata, bukan dari angka yang di-set manual.
     */
    protected function startAndCompleteSession(
        User $cashier,
        RatePackage $package,
        string $paymentMethod = 'CASH',
        ?Product $product = null,
        int $qty = 1,
    ): RentalSession {
        $unit = $this->makeUnit();

        $session = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->assertCreated()->json('session');

        if ($product !== null) {
            $this->actingAs($cashier)->postJson(
                route('api.sessions.items.store', $session['id']),
                ['product_id' => $product->id, 'qty' => $qty],
            )->assertCreated();
        }

        $this->actingAs($cashier)->postJson(
            route('api.sessions.complete', $session['id']),
            ['payment_method' => $paymentMethod],
        )->assertOk();

        return RentalSession::findOrFail($session['id']);
    }
}

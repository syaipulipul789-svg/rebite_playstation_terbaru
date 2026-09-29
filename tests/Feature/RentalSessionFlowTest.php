<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use App\Enums\UnitStatus;
use App\Models\AuditLog;
use Tests\TestCase;

class RentalSessionFlowTest extends TestCase
{
    public function test_kasir_membuka_sesi_dengan_paket_dan_unit_jadi_busy(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $package = $this->makePackage();

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.store'), [
                'unit_id' => $unit->id,
                'rate_package_id' => $package->id,
            ])
            ->assertCreated()
            ->assertJsonPath('session.status', RentalSessionStatus::RUNNING->value)
            ->assertJsonPath('session.rental_fee', 10000);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'status' => UnitStatus::BUSY->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_SESSION_STARTED,
        ]);
    }

    public function test_unit_tidak_bisa_disewa_dua_kali(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $package = $this->makePackage();

        $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->assertCreated();

        $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->assertStatus(422);
    }

    public function test_open_play_menghitung_tarif_per_jam(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit(['hourly_rate' => 12000]);

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.store'), [
                'unit_id' => $unit->id,
                'is_free_play' => 1,
                'open_play_minutes' => 150,
            ])
            ->assertCreated()
            ->assertJsonPath('session.planned_minutes', 150)
            ->assertJsonPath('session.rental_fee', 36000); // 3 jam utuh x 12.000
    }

    public function test_manambah_item_menurunkan_stok(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $package = $this->makePackage();
        $product = $this->makeProduct(['stock' => 5, 'price' => 8000]);

        $sessionId = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->json('session.id');

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.items.store', $sessionId), [
                'product_id' => $product->id,
                'qty' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('item.subtotal_label', 'Rp 16.000');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
    }

    public function test_item_ditolak_bila_stok_tidak_cukup(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $package = $this->makePackage();
        $product = $this->makeProduct(['stock' => 1]);

        $sessionId = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->json('session.id');

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.items.store', $sessionId), [
                'product_id' => $product->id,
                'qty' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_extra_time_menambah_durasi_dan_biaya(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit(['hourly_rate' => 12000]);
        $package = $this->makePackage();

        $sessionId = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->json('session.id');

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.extend', $sessionId), ['extra_minutes' => 60])
            ->assertOk()
            ->assertJsonPath('session.planned_minutes', 120)
            ->assertJsonPath('session.rental_fee', 22000); // 10.000 + 1 jam x 12.000
    }

    public function test_menyelesaikan_sewa_melepas_unit_dan_mengunci_harga(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $package = $this->makePackage();
        $product = $this->makeProduct(['price' => 10000, 'stock' => 10]);

        $sessionId = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->json('session.id');

        $this->actingAs($cashier)->postJson(route('api.sessions.items.store', $sessionId), [
            'product_id' => $product->id,
            'qty' => 1,
        ])->assertCreated();

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $sessionId), ['payment_method' => 'CASH'])
            ->assertOk()
            ->assertJsonPath('session.status', RentalSessionStatus::COMPLETED->value)
            ->assertJsonPath('session.payment_method', PaymentMethod::CASH->value)
            ->assertJsonPath('session.grand_total', 20000);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'status' => UnitStatus::READY->value,
        ]);
    }

    public function test_pembatalan_mengembalikan_stok_produk(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $package = $this->makePackage();
        $product = $this->makeProduct(['stock' => 10]);

        $sessionId = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->json('session.id');

        $this->actingAs($cashier)->postJson(route('api.sessions.items.store', $sessionId), [
            'product_id' => $product->id,
            'qty' => 3,
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7]);

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.cancel', $sessionId), ['reason' => 'Pelanggan batal datang.'])
            ->assertOk()
            ->assertJsonPath('session.status', RentalSessionStatus::CANCELLED->value);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 10]);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => UnitStatus::READY->value]);
    }

    public function test_pendapatan_tunai_masuk_ke_rekap_shift(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);
        $unit = $this->makeUnit();
        $package = $this->makePackage(['price' => 15000]);

        $sessionId = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->json('session.id');

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $sessionId), ['payment_method' => 'CASH'])
            ->assertOk();

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'system_cash_revenue' => 15000,
        ]);
    }

    public function test_qris_tidak_masuk_ke_rekap_tunai(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);
        $unit = $this->makeUnit();
        $package = $this->makePackage(['price' => 15000]);

        $sessionId = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->json('session.id');

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $sessionId), ['payment_method' => 'QRIS'])
            ->assertOk();

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'system_cash_revenue' => 0,
            'system_qris_revenue' => 15000,
        ]);
    }

    public function test_grid_api_menyertakan_timer_dan_status(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $package = $this->makePackage();

        $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->assertCreated();

        $this->actingAs($cashier)
            ->getJson(route('api.units.index'))
            ->assertOk()
            ->assertJsonPath('units.0.status', UnitStatus::BUSY->value)
            ->assertJsonPath('units.0.session.planned_minutes', 60)
            ->assertJsonStructure([
                'server_time',
                'units' => [['id', 'code', 'name', 'status', 'session']],
            ]);
    }
}

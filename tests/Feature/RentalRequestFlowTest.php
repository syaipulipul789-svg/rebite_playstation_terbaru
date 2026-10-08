<?php

namespace Tests\Feature;

use App\Enums\RentalRequestStatus;
use App\Enums\UnitStatus;
use App\Models\RentalRequest;
use Tests\TestCase;

class RentalRequestFlowTest extends TestCase
{
    public function test_pelanggan_bisa_melihat_dan_mengajukan_permintaan_sewa(): void
    {
        $customer = $this->makeCustomer();
        $unit = $this->makeUnit(['hourly_rate' => 12000]);

        $this->actingAs($customer)->get(route('customer.rentals.index'))
            ->assertOk()
            ->assertSee('Ajukan Permintaan Sewa');

        $this->actingAs($customer)->post(route('customer.rentals.store'), [
            'unit_id' => $unit->id,
            'start_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_time' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('success');

        $request = RentalRequest::query()->firstOrFail();

        $this->assertSame($customer->id, $request->user_id);
        $this->assertSame($unit->id, $request->unit_id);
        $this->assertSame(RentalRequestStatus::PENDING, $request->status);
        $this->assertSame(120, $request->duration_minutes);
        $this->assertSame('24000.00', $request->total_price);
        $this->assertSame('12000.00', $request->hourly_rate);
    }

    public function test_permintaan_sewa_kurang_dari_satu_jam_ditolak(): void
    {
        $customer = $this->makeCustomer();
        $unit = $this->makeUnit();

        $this->actingAs($customer)->post(route('customer.rentals.store'), [
            'unit_id' => $unit->id,
            'start_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_time' => now()->addDay()->addMinutes(30)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('rental_requests', 0);
    }

    public function test_kasir_dengan_shift_aktif_bisa_melihat_dan_mengkonfirmasi_permintaan(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit();
        $customer = $this->makeCustomer();
        $this->actingAs($customer)->post(route('customer.rentals.store'), [
            'unit_id' => $unit->id,
            'start_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_time' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
        ]);

        $request = RentalRequest::query()->firstOrFail();

        $this->actingAs($cashier)->get(route('pos.rental-requests.index'))
            ->assertOk()
            ->assertSee($request->rentalRequestCode());

        $this->actingAs($cashier)->post(route('pos.rental-requests.confirm', $request))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(RentalRequestStatus::CONFIRMED, $request->fresh()->status);
        $this->assertSame($cashier->id, $request->fresh()->confirmed_by);
    }

    public function test_pelanggan_tidak_bisa_membuka_daftar_permintaan_sewa_pos(): void
    {
        $customer = $this->makeCustomer();
        $this->makeUnit();

        $this->actingAs($customer)->get(route('pos.rental-requests.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('pos.bookings'))->assertForbidden();
    }

    public function test_mengkonfirmasi_ulang_permintaan_yang_sudah_terkonfirmasi_memberi_error(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit();
        $customer = $this->makeCustomer();
        $this->actingAs($customer)->post(route('customer.rentals.store'), [
            'unit_id' => $unit->id,
            'start_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_time' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
        ]);

        $request = RentalRequest::query()->firstOrFail();

        $this->actingAs($cashier)->from(route('pos.rental-requests.index'))
            ->post(route('pos.rental-requests.confirm', $request))
            ->assertRedirect(route('pos.rental-requests.index'))
            ->assertSessionHas('success');

        $this->actingAs($cashier)->from(route('pos.rental-requests.index'))
            ->post(route('pos.rental-requests.confirm', $request))
            ->assertRedirect(route('pos.rental-requests.index'))
            ->assertSessionHas('error');

        $this->assertSame(RentalRequestStatus::CONFIRMED, $request->fresh()->status);
    }

    public function test_menyelesaikan_permintaan_yang_belum_dikonfirmasi_memberi_error(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit();
        $customer = $this->makeCustomer();
        $this->actingAs($customer)->post(route('customer.rentals.store'), [
            'unit_id' => $unit->id,
            'start_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_time' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
        ]);

        $request = RentalRequest::query()->firstOrFail();

        $this->actingAs($cashier)->from(route('pos.rental-requests.index'))
            ->post(route('pos.rental-requests.complete', $request))
            ->assertRedirect(route('pos.rental-requests.index'))
            ->assertSessionHas('error');

        $this->assertSame(RentalRequestStatus::PENDING, $request->fresh()->status);
    }

    public function test_unit_berstatus_maintenance_tidak_muncul_di_form_pelanggan(): void
    {
        $customer = $this->makeCustomer();
        $maintenance = $this->makeUnit(['status' => UnitStatus::MAINTENANCE, 'code' => 'PS5-SVC']);
        $ready = $this->makeUnit();

        $this->actingAs($customer)->get(route('customer.rentals.index'))
            ->assertOk()
            ->assertSee($ready->name)
            ->assertDontSee($maintenance->name);
    }
}

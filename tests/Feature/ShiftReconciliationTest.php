<?php

namespace Tests\Feature;

use App\Enums\RentalSessionStatus;
use App\Enums\ShiftStatus;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_tanpa_shift_diarahkan_ke_halaman_mulai_shift(): void
    {
        $cashier = $this->makeCashier();

        $this->actingAs($cashier)
            ->get(route('units.index'))
            ->assertRedirect(route('shift.start'));
    }

    public function test_api_tanpa_shift_mengembalikan_423(): void
    {
        $cashier = $this->makeCashier();

        $this->actingAs($cashier)
            ->getJson(route('api.units.index'))
            ->assertStatus(423);
    }

    public function test_owner_tidak_perlu_shift(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk();
    }

    public function test_modal_awal_berformat_titik_koma_diterima(): void
    {
        $cashier = $this->makeCashier();

        $this->actingAs($cashier)
            ->post(route('shift.start.store'), ['starting_cash' => '200.000'])
            ->assertRedirect(route('units.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $cashier->id,
            'starting_cash' => 200000,
            'status' => ShiftStatus::OPEN->value,
        ]);
    }

    public function test_kasir_tidak_boleh_membuka_shift_kedua(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $this->actingAs($cashier)
            ->post(route('shift.start.store'), ['starting_cash' => 100000])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_selisih_nol_tidak_wajib_catatan(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);

        $this->actingAs($cashier)
            ->post(route('shift.end.store'), ['actual_physical_cash' => 200000])
            ->assertRedirect(route('shift.summary', $shift));

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'status' => ShiftStatus::CLOSED->value,
            'discrepancy' => 0,
            'note' => null,
        ]);
    }

    public function test_selisih_nonzero_wajib_catatan(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier, 200000);

        $this->actingAs($cashier)
            ->from(route('shift.end'))
            ->post(route('shift.end.store'), ['actual_physical_cash' => 195000])
            ->assertRedirect(route('shift.end'))
            ->assertSessionHasErrors('note');
    }

    public function test_selisih_nonzero_dengan_catatan_berhasil_disimpan(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);

        $this->actingAs($cashier)
            ->post(route('shift.end.store'), [
                'actual_physical_cash' => 195000,
                'note' => 'Kurang karena kembalian tidak dikembalikan pelanggan.',
            ])
            ->assertRedirect(route('shift.summary', $shift));

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'discrepancy' => -5000,
            'note' => 'Kurang karena kembalian tidak dikembalikan pelanggan.',
        ]);
    }

    public function test_hidden_expected_cash_tidak_bisa_manipulasi_validasi(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier, 200000);

        // Kasir memalsukan expected_cash = 195000 supaya selisih terlihat nol.
        $this->actingAs($cashier)
            ->from(route('shift.end'))
            ->post(route('shift.end.store'), [
                'actual_physical_cash' => 195000,
                'expected_cash' => 195000,
            ])
            ->assertSessionHasErrors('note');
    }

    public function test_pendapatan_qris_tidak_ikut_menaikkan_ekspektasi_kas(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);
        $package = $this->makePackage(['price' => 50000]);

        // Satu sesi dibayar QRIS — tidak boleh masuk ke ekspektasi kas tunai.
        $qrisSession = $this->startAndCompleteSession($cashier, $package, 'QRIS');

        $this->actingAs($cashier)
            ->post(route('shift.end.store'), ['actual_physical_cash' => 200000])
            ->assertRedirect(route('shift.summary', $shift));

        // Modal 200.000 saja; 50.000 QRIS tidak menambah ekspektasi kas.
        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'system_cash_revenue' => 0,
            'system_qris_revenue' => 50000,
            'discrepancy' => 0,
        ]);

        $this->assertDatabaseHas('rental_sessions', [
            'id' => $qrisSession->id,
            'payment_method' => 'QRIS',
        ]);
    }

    public function test_pendapatan_tunai_menaikkan_ekspektasi_kas(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);
        $package = $this->makePackage(['price' => 50000]);

        $this->startAndCompleteSession($cashier, $package, 'CASH');

        // Ekspektasi kas = 200.000 modal + 50.000 tunai = 250.000
        $this->actingAs($cashier)
            ->post(route('shift.end.store'), ['actual_physical_cash' => 250000])
            ->assertRedirect(route('shift.summary', $shift));

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'system_cash_revenue' => 50000,
            'discrepancy' => 0,
        ]);
    }

    public function test_pendapatan_fnb_ikut_terhitung_dalam_rekap_tunai(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);
        $package = $this->makePackage(['price' => 30000]);
        $product = $this->makeProduct(['price' => 10000, 'stock' => 10]);

        $session = $this->startAndCompleteSession($cashier, $package, 'CASH', $product, 2);

        $this->actingAs($cashier)
            ->post(route('shift.end.store'), ['actual_physical_cash' => 250000])
            ->assertRedirect(route('shift.summary', $shift));

        // 30.000 sewa + 20.000 (2 x 10.000 F&B) = 50.000 tunai
        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'system_cash_revenue' => 50000,
            'discrepancy' => 0,
        ]);

        $this->assertDatabaseHas('rental_sessions', ['id' => $session->id]);
    }

    public function test_shift_tidak_bisa_ditutup_selagi_ada_sesi_berjalan(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier, 200000);

        $unit = $this->makeUnit();
        $package = $this->makePackage();

        $session = $this->actingAs($cashier)->postJson(route('api.sessions.store'), [
            'unit_id' => $unit->id,
            'rate_package_id' => $package->id,
        ])->assertCreated()->json('session');

        $this->actingAs($cashier)
            ->from(route('shift.end'))
            ->post(route('shift.end.store'), ['actual_physical_cash' => 200000])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('rental_sessions', [
            'id' => $session['id'],
            'status' => RentalSessionStatus::RUNNING->value,
        ]);
    }

    public function test_penutupan_shift_menulis_audit_log(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier, 200000);

        $this->actingAs($cashier)
            ->post(route('shift.end.store'), [
                'actual_physical_cash' => 190000,
                'note' => 'Selisih karena mesin EDC dropout.',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_SHIFT_CLOSED,
            'user_id' => $cashier->id,
        ]);
    }

    public function test_halaman_rekonsilisi_menampilkan_angka_server(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);

        // Angka rekonsiliasi dihitung ulang dari sesi nyata, jadi test memakai
        // sesi sungguhan (bukan sekadar meng-set kolom shift).
        $this->startAndCompleteSession($cashier, $this->makePackage(['price' => 30000]), 'CASH');
        $this->startAndCompleteSession($cashier, $this->makePackage(['name' => 'Paket QRIS', 'price' => 15000]), 'QRIS');

        $this->actingAs($cashier)
            ->get(route('shift.end'))
            ->assertOk()
            ->assertViewHas('preview', fn (array $preview) => $preview['starting_cash'] === 200000.0
                // QRIS tidak menambah ekspektasi kas tunai
                && $preview['expected_cash'] === 230000.0
                && $preview['cash_revenue'] === 30000.0
                && $preview['qris_revenue'] === 15000.0
                && $preview['total_revenue'] === 45000.0);
    }

    public function test_angka_ekspektasi_tidak_bisa_manipulasi_lewat_hidden_field(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier, 200000);

        $this->startAndCompleteSession($cashier, $this->makePackage(['price' => 30000]), 'CASH');

        // Kasir mengirim actual = 240.000 dan ikut menyuruhkan
        // expected_cash = 240.000 agar selisih terlihat nol. Karena field
        // tak dikenal diabaikan server, ekspektasi tetap dihitung ulang.
        $response = $this->actingAs($cashier)
            ->post(route('shift.end.store'), [
                'actual_physical_cash' => 240000,
                'expected_cash' => 240000,
            ]);

        $response->assertSessionHasErrors('note');

        $this->assertDatabaseHas('shifts', [
            'status' => ShiftStatus::OPEN,
        ]);
    }
}

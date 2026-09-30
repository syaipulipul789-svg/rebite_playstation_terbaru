<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use App\Enums\UnitStatus;
use App\Models\RentalSession;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use Tests\TestCase;

class CustomerDisplayPageTest extends TestCase
{
    public function test_halaman_landing_publik_menampilkan_unit_paket_dan_menu(): void
    {
        $unit = $this->makeUnit(['code' => 'PS4-01', 'name' => 'PS4 Reguler 01', 'type' => 'PS4', 'hourly_rate' => 8000]);
        $package = $this->makePackage(['name' => 'Paket 2 Jam', 'price' => 15000]);
        $product = $this->makeProduct(['name' => 'Kopi Susu Gula Aren', 'price' => 15000]);

        $this->get(route('customer.home'))
            ->assertOk()
            ->assertSee('READY')
            ->assertSee('PS4 Reguler 01')
            ->assertSee('Rp 8.000')
            ->assertSee('Paket 2 Jam')
            ->assertSee('Kopi Susu Gula Aren');
    }

    public function test_landing_menampilkan_status_in_use_beserta_sisa_waktu(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $unit = $this->makeUnit(['status' => UnitStatus::BUSY]);

        $this->startRunningSession($cashier, $shift, $unit, minutesAgo: 30, plannedMinutes: 60);

        $this->get(route('customer.home'))
            ->assertOk()
            ->assertSee('IN USE')
            ->assertSee('Sisa ± 30 menit');
    }

    public function test_halaman_monitor_live_publik_bisa_dirender(): void
    {
        $unit = $this->makeUnit(['code' => 'PS4-01', 'type' => 'PS4']);

        $this->get(route('customer.display'))
            ->assertOk()
            ->assertSee('LIVE')
            ->assertSee('PS4-01')
            ->assertSee('READY');
    }

    public function test_snapshot_display_publik_menyertakan_timer_dan_status(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $busyUnit = $this->makeUnit(['code' => 'PS5-01', 'type' => 'PS5', 'status' => UnitStatus::BUSY]);
        $readyUnit = $this->makeUnit(['code' => 'PS4-01', 'type' => 'PS4']);

        $this->startRunningSession($cashier, $shift, $busyUnit, minutesAgo: 30, plannedMinutes: 60);

        $this->getJson(route('customer.display.data'))
            ->assertOk()
            ->assertJsonStructure([
                'server_time',
                'server_timestamp',
                'units' => [['id', 'code', 'name', 'type', 'status', 'session']],
            ])
            ->assertJsonPath('units.0.status', UnitStatus::READY->value)
            ->assertJsonPath('units.0.session', null)
            ->assertJsonPath('units.1.status', UnitStatus::BUSY->value)
            ->assertJsonPath(
                'units.1.session.planned_end_timestamp',
                $busyUnit->runningSession->start_time->getTimestampMs() + (60 * 60 * 1000),
            );
    }

    public function test_snapshot_display_menandai_waktu_habis(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $unit = $this->makeUnit(['status' => UnitStatus::BUSY]);

        $this->startRunningSession($cashier, $shift, $unit, minutesAgo: 90, plannedMinutes: 60);

        $this->getJson(route('customer.display.data'))
            ->assertOk()
            ->assertJsonPath('units.0.session.remaining_seconds', 0)
            ->assertJsonPath('units.0.session.is_time_up', true);
    }

    public function test_halaman_awal_tetap_mengalihkan_user_login_sesuai_role(): void
    {
        $owner = $this->makeOwner();
        $this->actingAs($owner)->get('/')->assertRedirect(route('owner.dashboard'));

        $cashier = $this->makeCashier();
        $this->actingAs($cashier)->get('/')->assertRedirect(route('shift.start'));

        $this->makeOpenShift($cashier);
        $this->actingAs($cashier)->get('/')->assertRedirect(route('units.index'));
    }

    private function startRunningSession(
        User $cashier,
        Shift $shift,
        Unit $unit,
        int $minutesAgo,
        int $plannedMinutes,
    ): RentalSession {
        return RentalSession::create([
            'unit_id' => $unit->id,
            'shift_id' => $shift->id,
            'user_id' => $cashier->id,
            'start_time' => now()->subMinutes($minutesAgo),
            'planned_minutes' => $plannedMinutes,
            'duration_minutes' => $plannedMinutes,
            'is_free_play' => false,
            'package_name' => 'Paket 1 Jam',
            'rental_fee' => 10000,
            'status' => RentalSessionStatus::RUNNING,
            'payment_method' => PaymentMethod::CASH,
        ]);
    }
}

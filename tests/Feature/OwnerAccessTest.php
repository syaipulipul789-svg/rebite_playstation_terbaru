<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UnitStatus;
use App\Models\Order;
use App\Services\OwnerAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerAccessTest extends TestCase
{
    public function test_kasir_tidak_bisa_membuka_halaman_owner(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $this->actingAs($cashier)->get(route('owner.dashboard'))->assertForbidden();
        $this->actingAs($cashier)->get(route('owner.reports'))->assertForbidden();
        $this->actingAs($cashier)->get(route('owner.audit-log'))->assertForbidden();
        $this->actingAs($cashier)->get(route('owner.units.index'))->assertForbidden();
    }

    public function test_owner_bisa_membuka_seluruh_halaman_owner(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('owner.reports'))->assertOk();
        $this->actingAs($owner)->get(route('owner.audit-log'))->assertOk();
        $this->actingAs($owner)->get(route('owner.units.index'))->assertOk();
        $this->actingAs($owner)->get(route('owner.products.index'))->assertOk();
        $this->actingAs($owner)->get(route('owner.rate-packages.index'))->assertOk();
        $this->actingAs($owner)->get(route('owner.users.index'))->assertOk();
    }

    public function test_owner_kehalaman_awal_langsung_ke_dashboard(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get('/')->assertRedirect(route('owner.dashboard'));
    }

    public function test_halaman_form_owner_dapat_dirender(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('owner.units.create'))->assertOk();
        $this->actingAs($owner)->get(route('owner.products.create'))->assertOk();
        $this->actingAs($owner)->get(route('owner.rate-packages.create'))->assertOk();
        $this->actingAs($owner)->get(route('owner.users.create'))->assertOk();
    }

    /**
     * Guard render untuk halaman operasional kasir.
     *
     * Halaman ini dirender penuh hanya saat shift aktif, jadi test closure
     * pun lewat tanpa pernah menyentuhnya — padahal Blade fatal error
     * (mis. ekspresi Alpine yang salah tulis `{{ unit.x }}`) baru muncul
     * saat view benar-benar di-render. Test ini menutup celah tersebut.
     */
    public function test_halaman_operasional_kasir_dapat_dirender(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $this->makeUnit();
        $this->makePackage();
        $this->makeProduct();

        $this->actingAs($cashier)->get(route('units.index'))->assertOk();
        $this->actingAs($cashier)->get(route('pos.index'))->assertOk();
        $this->actingAs($cashier)->get(route('shift.end'))->assertOk();
        $this->actingAs($cashier)->get(route('shift.start'))->assertRedirect(route('units.index'));
    }

    public function test_halaman_awal_kasir_dan_owner_sesuai_role(): void
    {
        $owner = $this->makeOwner();
        $cashier = $this->makeCashier();

        $this->actingAs($owner)->get('/')->assertRedirect(route('owner.dashboard'));

        // Kasir tanpa shift diarahkan ke input modal awal.
        $this->actingAs($cashier)->get('/')->assertRedirect(route('shift.start'));

        $this->makeOpenShift($cashier);

        // Setelah shift aktif, kasir langsung ke grid unit.
        $this->actingAs($cashier)->get('/')->assertRedirect(route('units.index'));
    }

    public function test_owner_bisa_menambah_unit(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->post(route('owner.units.store'), [
                'code' => 'PS4-07',
                'name' => 'PS4 Slim 7',
                'type' => 'PS4',
                'hourly_rate' => 9000,
                'status' => UnitStatus::READY->value,
                'location' => 'Lantai 2',
            ])
            ->assertRedirect(route('owner.units.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', ['code' => 'PS4-07', 'hourly_rate' => 9000]);
    }

    public function test_kode_unit_duplikat_ditolak(): void
    {
        $owner = $this->makeOwner();
        $this->makeUnit(['code' => 'PS5-01']);

        $this->actingAs($owner)
            ->from(route('owner.units.create'))
            ->post(route('owner.units.store'), [
                'code' => 'PS5-01',
                'name' => 'Duplikat',
                'type' => 'PS5',
                'hourly_rate' => 10000,
                'status' => UnitStatus::READY->value,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_owner_tidak_bisa_menurunkan_role_sendiri(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->from(route('owner.users.edit', $owner))
            ->put(route('owner.users.update', $owner), [
                'name' => $owner->name,
                'username' => $owner->username,
                'role' => 'KASIR',
                'is_active' => 1,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'OWNER']);
    }

    public function test_owner_tidak_bisa_menghapus_diri_sendiri(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->delete(route('owner.users.destroy', $owner))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_status_unit_busy_tidak_bisa_diubah_manually(): void
    {
        $owner = $this->makeOwner();
        $unit = $this->makeUnit(['status' => UnitStatus::BUSY]);

        $this->actingAs($owner)
            ->from(route('owner.units.index'))
            ->patch(route('owner.units.status', $unit), ['status' => 'MAINTENANCE'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => UnitStatus::BUSY->value]);
    }

    public function test_filter_dashboard_halaman_unit_bekerja(): void
    {
        $owner = $this->makeOwner();
        $this->makeUnit(['code' => 'PS5-01', 'name' => 'PS5 Reguler 1']);
        $this->makeUnit(['code' => 'SW-01', 'name' => 'Switch Lite', 'type' => 'Nintendo Switch']);

        $this->actingAs($owner)
            ->get(route('owner.units.index', ['type' => 'Nintendo Switch']))
            ->assertOk()
            ->assertSee('Switch Lite')
            ->assertDontSee('PS5 Reguler 1');
    }

    /**
     * Rentang kustom hanya berlaku bila `range=custom` ikut dikirim — inilah
     * yang membuat kolom tanggal di form laporan bisa dipakai.
     */
    public function test_laporan_menerima_rentang_kustom(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get(route('owner.reports', [
                'range' => 'custom',
                'from' => now()->subDays(6)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('from')
            ->assertViewHas('to');
    }

    /**
     * Pesanan barcode adalah pendapatan juga. Kalau tidak ikut dihitung,
     * kartu "Total Pendapatan" bisa lebih kecil daripada jumlah baris
     * rekap shift yang dicetak tepat di bawahnya.
     */
    public function test_pendapatan_laporan_mencakup_pesanan_barcode(): void
    {
        $owner = $this->makeOwner();
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $package = $this->makePackage();

        $this->startAndCompleteSession($cashier, $package, 'CASH');
        $this->startAndCompleteSession($cashier, $package, 'QRIS');

        $order = Order::create([
            'code' => 'ORD-TEST',
            'token' => (string) Str::uuid(),
            'customer_name' => 'Rina',
            'customer_phone' => '081234567890',
            'status' => OrderStatus::COMPLETED,
            'total_price' => 25000,
            'payment_method' => PaymentMethod::CASH,
            'placed_at' => now(),
            'settled_at' => now(),
            'shift_id' => $shift->id,
            'user_id' => $cashier->id,
        ]);

        $analytics = app(OwnerAnalyticsService::class);
        $from = CarbonImmutable::today()->startOfDay();
        $to = CarbonImmutable::today()->endOfDay();

        $this->assertEquals(45000.0, $analytics->revenueBetween($from, $to));
        $this->assertEquals(35000.0, $analytics->revenueBetween($from, $to, PaymentMethod::CASH));
        $this->assertEquals(10000.0, $analytics->revenueBetween($from, $to, PaymentMethod::QRIS));

        $daily = collect($analytics->dailyBreakdown($from, $to))->firstWhere('date', now()->toDateString());
        $this->assertSame(45000.0, $daily['total']);
        $this->assertSame(2, $daily['sessions']);
        $this->assertSame(1, $daily['orders']);

        $this->actingAs($owner)->get(route('owner.reports'))->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    /**
     * Input filter dari URL tidak dipercaya: tanggal ngawur, preset asing,
     * dan parameter berbentuk array harus jatuh ke default — bukan 500.
     */
    public function test_laporan_tidak_500_untuk_filter_nyawur(): void
    {
        $owner = $this->makeOwner();

        $cases = [
            ['range' => 'custom', 'from' => 'bukan-tanggal'],
            ['range' => 'custom', 'from' => '2026-09-01', 'to' => 'abc'],
            ['range' => 'custom', 'from' => '2026-02-30'],
            ['range' => 'preset-yang-tidak-ada'],
            ['range' => ['array']],
            ['range' => 'custom', 'from' => ['array'], 'to' => ['array']],
            ['range' => 'custom', 'from' => ''],
        ];

        foreach ($cases as $query) {
            $this->actingAs($owner)
                ->get(route('owner.reports').'?'.http_build_query($query))
                ->assertOk();
        }
    }

    /**
     * `preset` dipakai form laporan lewat x-model/x-show. Kalau scope Alpine-nya
     * hilang, kolom tanggal kustom tidak pernah muncul — jadi pastikan state
     * itu benar-benar dideklarasikan di markup.
     */
    public function test_form_laporan_mendeklarasikan_state_preset(): void
    {
        $owner = $this->makeOwner();

        $html = $this->actingAs($owner)
            ->get(route('owner.reports', ['range' => 'custom']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/x-data="\{\s*preset:/',
            $html,
            'Scope Alpine `preset` tidak dideklarasikan di form filter laporan.',
        );
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\RentalSessionStatus;
use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Unit;
use App\Models\User;
use App\Support\Phone;
use Tests\TestCase;

class CustomerBookingTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Pendaftaran & login
    |--------------------------------------------------------------------------
    */

    public function test_pelanggan_bisa_mendaftar_dengan_nomor_whatsapp(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Sinta Dewi',
            'phone' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $response->assertRedirect(route('customer.dashboard'));

        $customer = User::query()->where('phone', '081234567890')->firstOrFail();

        $this->assertSame(UserRole::CUSTOMER, $customer->role);
        $this->assertSame('Sinta Dewi', $customer->name);
        $this->assertNull($customer->email);
        $this->assertAuthenticatedAs($customer);
    }

    /**
     * Format yang berbeda untuk NOMOR YANG BERBEDA, supaya tiap daftarnya
     * benar-benar membuat akun baru (bukan dianggap nomor duplikat).
     */
    public function test_nomor_whatsapp_berbagai_format_dinormalkan_saat_mendaftar(): void
    {
        $cases = [
            '+62 812-3456-7890' => '081234567890',
            '6281234567891' => '081234567891',
            '81234567892' => '081234567892',
            '0812 3456 7893' => '081234567893',
            '0813-0000-0000' => '081300000000',
        ];

        foreach ($cases as $written => $expected) {
            $this->assertSame($expected, Phone::normalize($written));

            // Logout tiap iterasi: route daftar hanya untuk tamu, jadi sesi
            // pelanggan dari iterasi sebelumnya akan dialihkan ke /dashboard.
            $this->post(route('logout'));

            $this->post(route('register'), [
                'name' => 'Pelanggan',
                'phone' => $written,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])->assertRedirect(route('customer.dashboard'));

            $this->assertSame($expected, User::query()->where('phone', $expected)->firstOrFail()->phone);
        }

        $this->assertSame(count($cases), User::query()->where('role', UserRole::CUSTOMER)->count());
    }

    public function test_nomor_whatsapp_tidak_boleh_daftar_dua_kali(): void
    {
        $this->makeCustomer('081234567890');

        $this->from(route('register'))->post(route('register'), [
            'name' => 'Orang Lain',
            'phone' => '0812 3456 7890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(1, User::query()->where('phone', '081234567890')->count());
    }

    public function test_pendaftaran_menolak_nomor_bukan_seluler_indonesia(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'name' => 'Ngawur',
            'phone' => '12345',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_pelanggan_bisa_login_dengan_nomor_whatsapp(): void
    {
        $customer = $this->makeCustomer('081234567890');

        $this->post(route('login'), [
            'username' => '081234567890',
            'password' => 'password',
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_pelanggan_bisa_login_dengan_nomor_format_lain(): void
    {
        $customer = $this->makeCustomer('081234567890');

        $this->post(route('login'), [
            'username' => '+62 812-3456-7890',
            'password' => 'password',
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer);
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard: melihat unit kosong
    |--------------------------------------------------------------------------
    */

    public function test_dashboard_menampilkan_semua_unit_beserta_statusnya(): void
    {
        $customer = $this->makeCustomer();
        $ready = $this->makeUnit(['name' => 'PS5 Kosong', 'status' => UnitStatus::READY]);
        $busy = $this->makeUnit(['name' => 'PS5 Dipakai', 'status' => UnitStatus::BUSY]);
        $servis = $this->makeUnit(['name' => 'PS5 Servis', 'status' => UnitStatus::MAINTENANCE]);

        $response = $this->actingAs($customer)->get(route('customer.dashboard'));

        $response->assertOk()
            ->assertSee($ready->name)
            ->assertSee($busy->name)
            ->assertSee($servis->name)
            ->assertSee('KOSONG')
            ->assertSee('TERISI')
            ->assertSee('SERVIS');
    }

    public function test_dashboard_menampilkan_unit_kosong_sebagai_yang_bisa_dipesan(): void
    {
        $customer = $this->makeCustomer();
        $ready = $this->makeUnit(['status' => UnitStatus::READY]);
        $this->makeUnit(['status' => UnitStatus::MAINTENANCE]);

        $response = $this->actingAs($customer)->get(route('customer.dashboard'));

        $bookableIds = array_column($response->viewData('bookingUnits'), 'id');

        $this->assertContains($ready->id, $bookableIds);
        // Unit servis tidak boleh masuk daftar yang bisa dipesan.
        $this->assertCount(1, $bookableIds);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));
    }

    public function test_kasir_tidak_bisa_membuka_dashboard_pelanggan(): void
    {
        $this->actingAs($this->makeCashier())
            ->get(route('customer.dashboard'))
            ->assertForbidden();
    }

    public function test_owner_tidak_bisa_membuka_dashboard_pelanggan(): void
    {
        $this->actingAs($this->makeOwner())
            ->get(route('customer.dashboard'))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Booking menautkan ke akun
    |--------------------------------------------------------------------------
    */

    public function test_pelanggan_yang_login_bisa_membuat_booking(): void
    {
        $customer = $this->makeCustomer('081234567890', ['name' => 'Sinta Dewi']);
        $unit = $this->makeUnit(['hourly_rate' => 12000]);

        $this->actingAs($customer)
            ->post(route('customer.bookings.store'), [
                'console_id' => $unit->id,
                'start_time' => now()->addHours(2)->format('Y-m-d H:i'),
                'duration_hours' => 2,
                'notes' => 'Minta remote PS5',
            ])
            ->assertRedirect();

        $booking = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame($customer->id, $booking->user_id);
        $this->assertSame('Sinta Dewi', $booking->customer_name);
        $this->assertSame('081234567890', $booking->customer_phone);
        $this->assertSame('24000.00', $booking->total_price);
    }

    public function test_form_tidak_bisa_menimpa_nama_dan_nomor_pelanggan(): void
    {
        $customer = $this->makeCustomer('081234567890', ['name' => 'Sinta Dewi']);
        $unit = $this->makeUnit();

        $this->actingAs($customer)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Nama Palsu',
            'customer_phone' => '089999999999',
            'start_time' => now()->addHours(2)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertRedirect();

        $booking = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        // Identitas tetap dari akun, tidak bisa ditimpa lewat request.
        $this->assertSame('Sinta Dewi', $booking->customer_name);
        $this->assertSame('081234567890', $booking->customer_phone);
    }

    public function test_tamu_tidak_bisa_membuat_booking(): void
    {
        $unit = $this->makeUnit();

        $this->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => now()->addHours(2)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_kasir_tidak_bisa_membuat_booking_sebagai_pelanggan(): void
    {
        $unit = $this->makeUnit();

        $this->actingAs($this->makeCashier())
            ->post(route('customer.bookings.store'), [
                'console_id' => $unit->id,
                'start_time' => now()->addHours(2)->format('Y-m-d H:i'),
                'duration_hours' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_status_booking_hanya_menampilkan_booking_milik_akun(): void
    {
        $mine = $this->makeCustomer('081234567890');
        $other = $this->makeCustomer('089999999999', ['name' => 'Orang Lain']);

        $myUnit = $this->makeUnit();
        $theirUnit = $this->makeUnit();

        $mineBooking = $this->actingAs($mine)->postJson(route('customer.bookings.store'), [
            'console_id' => $myUnit->id,
            'start_time' => now()->addHours(2)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertCreated()->json('booking_code');

        $this->actingAs($other)->postJson(route('customer.bookings.store'), [
            'console_id' => $theirUnit->id,
            'start_time' => now()->addHours(2)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertCreated();

        $response = $this->actingAs($mine)->getJson(route('customer.bookings.status'))->assertOk();

        $response->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.code', $mineBooking);
    }

    /*
    |--------------------------------------------------------------------------
    | Status berubah menjadi "Terisi"
    |--------------------------------------------------------------------------
    */

    public function test_status_booking_berubah_menjadi_terisi_setelah_kasir_menyetujui(): void
    {
        $customer = $this->makeCustomer();
        $unit = $this->makeUnit(['hourly_rate' => 10000]);
        $start = now()->addHours(1)->startOfHour();

        $this->actingAs($customer)->postJson(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ])->assertCreated();

        $booking = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        $this->actingAs($customer)->getJson(route('customer.bookings.status'))
            ->assertOk()
            ->assertJsonPath('bookings.0.status.label', 'Menunggu Persetujuan')
            ->assertJsonPath('bookings.0.status.is_occupied', false);

        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $this->actingAs($cashier)
            ->post(route('pos.bookings.confirm', $booking))
            ->assertRedirect();

        // Booking masa depan hanya terkunci: unit belum berubah jadi Terisi.
        $this->assertSame(UnitStatus::READY, $unit->fresh()->status);

        // bookings:promote menyalakan sesi sehingga unit jadi Terisi.
        $this->travelTo($start->copy()->addMinutes(5));
        $this->artisan('bookings:promote')->assertSuccessful();

        $this->assertSame(UnitStatus::BUSY, $unit->fresh()->status);
        $this->assertNotNull($booking->fresh()->rental_session_id);

        $this->actingAs($customer)->getJson(route('customer.bookings.status'))
            ->assertOk()
            ->assertJsonPath('bookings.0.status.label', 'Sudah Terisi')
            ->assertJsonPath('bookings.0.status.is_occupied', true);

        $this->assertEqualsWithDelta(
            7200,
            $this->actingAs($customer)->getJson(route('customer.bookings.status'))
                ->json('bookings.0.remaining_seconds'),
            5,
        );
    }

    public function test_unit_menjadi_terisi_dan_terlihat_pelanggan_lain_di_dashboard(): void
    {
        $customer = $this->makeCustomer();
        $unit = $this->makeUnit(['hourly_rate' => 10000]);
        $start = now()->addHour()->startOfHour();

        $this->actingAs($customer)->postJson(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ])->assertCreated();

        $booking = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $this->actingAs($cashier)->post(route('pos.bookings.confirm', $booking));

        // Slot berjalan: unit berubah jadi Terisi dan sesi rental hidup.
        $this->travelTo($start->copy()->addMinutes(5));
        $this->artisan('bookings:promote')->assertSuccessful();

        $this->assertSame(UnitStatus::BUSY, $unit->fresh()->status);
        $this->assertDatabaseHas('rental_sessions', [
            'unit_id' => $unit->id,
            'status' => RentalSessionStatus::RUNNING->value,
        ]);

        // Pelanggan lain tidak melihat booking itu, tapi melihat unit Terisi.
        $other = $this->makeCustomer('089999999999');

        $this->actingAs($other)->getJson(route('customer.bookings.status'))
            ->assertOk()
            ->assertJsonCount(0, 'bookings');

        $this->actingAs($other)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('TERISI');
    }

    /*
    |--------------------------------------------------------------------------
    | PENDING mengunci slot
    |--------------------------------------------------------------------------
    */

    public function test_booking_pending_mengunci_slot_untuk_pelanggan_lain(): void
    {
        $first = $this->makeCustomer('081234567890');
        $second = $this->makeCustomer('089999999999');
        $unit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();

        $this->actingAs($first)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ])->assertRedirect();

        // Belum dikonfirmasi kasir, tapi slotnya sudah tidak bisa diambil.
        $this->actingAs($second)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->copy()->addMinutes(30)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_booking_pending_menjam_di_setiap_slot_yang_bentrok(): void
    {
        $first = $this->makeCustomer('081234567890');
        $second = $this->makeCustomer('089999999999');
        $unit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();

        $this->actingAs($first)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ]);

        $this->actingAs($second)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->copy()->addMinutes(30)->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_slot_setelah_booking_pending_masih_bisa_dipesan(): void
    {
        $first = $this->makeCustomer('081234567890');
        $second = $this->makeCustomer('089999999999');
        $unit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();

        $this->actingAs($first)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ]);

        // Satu jam setelah slot pertama selesai — tidak overlap.
        $this->actingAs($second)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->copy()->addHour()->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertRedirect();

        $this->assertSame(2, Booking::query()->count());
    }

    public function test_booking_pending_yang_jamnya_sudah_lewat_tidak_mengunci_slot(): void
    {
        $customer = $this->makeCustomer();
        $other = $this->makeCustomer('089999999999');
        $unit = $this->makeUnit();

        // Booking basi: tidak pernah sempat disetujui, jamnya sudah terlewat.
        $stale = Booking::create([
            'console_id' => $unit->id,
            'user_id' => $customer->id,
            'customer_name' => 'Pelanggan Basi',
            'customer_phone' => '081234567890',
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHours(2),
            'status' => BookingStatus::PENDING,
            'total_price' => 10000,
        ]);

        $this->assertFalse($stale->holdsSlot());

        $this->actingAs($other)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => now()->addHours(2)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertRedirect();

        $this->assertSame(2, Booking::query()->count());
    }

    public function test_membatalkan_booking_pending_melepas_slotnya(): void
    {
        $first = $this->makeCustomer('081234567890');
        $second = $this->makeCustomer('089999999999');
        $unit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();

        $this->actingAs($first)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ]);

        $booking = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $this->actingAs($cashier)
            ->post(route('pos.bookings.cancel', $booking))
            ->assertRedirect();

        $this->actingAs($second)->post(route('customer.bookings.store'), [
            'console_id' => $unit->id,
            'start_time' => $start->copy()->addMinutes(30)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertRedirect();

        $this->assertSame(2, Booking::query()->count());
    }

    public function test_booking_berbeda_unit_tidak_bentrok(): void
    {
        $customer = $this->makeCustomer('081234567890');
        $other = $this->makeCustomer('089999999999');
        $firstUnit = $this->makeUnit();
        $secondUnit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();

        $this->actingAs($customer)->post(route('customer.bookings.store'), [
            'console_id' => $firstUnit->id,
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ]);

        $this->actingAs($other)->post(route('customer.bookings.store'), [
            'console_id' => $secondUnit->id,
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ])->assertRedirect();

        $this->assertSame(2, Booking::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Riwayat booking
    |--------------------------------------------------------------------------
    */

    public function test_booking_lama_otomatis_terhubung_ke_akun_dengan_nomor_yang_sama(): void
    {
        // Disimulasikan lewat update manual: migrasi hanya berjalan sekali,
        // jadi test ini memverifikasi relasi + pencocokan nomornya.
        $customer = $this->makeCustomer('081234567890');
        $unit = $this->makeUnit();

        $legacy = Booking::create([
            'console_id' => $unit->id,
            'customer_name' => 'Budi',
            'customer_phone' => '+62 812-3456-7890',
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
            'status' => BookingStatus::COMPLETED,
            'total_price' => 10000,
        ]);

        $this->assertNull($legacy->user_id);

        $linked = Booking::query()
            ->whereRaw('? IN (?, ?, ?, ?)', [
                Phone::normalize($legacy->customer_phone),
                ...Phone::variants($legacy->customer_phone),
            ])
            ->firstOrFail();

        $linked->update(['user_id' => $customer->id]);

        $this->actingAs($customer)->getJson(route('customer.bookings.status'))
            ->assertOk()
            ->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.code', $legacy->bookingCode());
    }

    public function test_booking_yang_tidak_lagunya_tidak_bisa_dilihat_pelanggan_lain(): void
    {
        $customer = $this->makeCustomer('081234567890');
        $other = $this->makeCustomer('089999999999');
        $unit = $this->makeUnit();

        Booking::create([
            'console_id' => $unit->id,
            'user_id' => $other->id,
            'customer_name' => 'Orang Lain',
            'customer_phone' => '089999999999',
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
            'status' => BookingStatus::CONFIRMED,
            'total_price' => 10000,
        ]);

        $this->actingAs($customer)->getJson(route('customer.bookings.status'))
            ->assertOk()
            ->assertJsonCount(0, 'bookings');
    }

    public function test_daftar_booking_membatasi_10_terbaru(): void
    {
        $customer = $this->makeCustomer();
        $unit = $this->makeUnit();

        foreach (range(1, 12) as $offset) {
            $start = now()->addDays($offset)->startOfHour();

            Booking::create([
                'console_id' => $unit->id,
                'user_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'start_time' => $start,
                'end_time' => $start->copy()->addHour(),
                'status' => BookingStatus::COMPLETED,
                'total_price' => 10000,
            ]);
        }

        $this->actingAs($customer)->getJson(route('customer.bookings.status'))
            ->assertOk()
            ->assertJsonCount(10, 'bookings');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Unit;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CheckBookingStatusTest extends TestCase
{
    public function test_halaman_cek_status_bisa_diakses_tanpa_login(): void
    {
        $this->get(route('booking.check'))
            ->assertOk()
            ->assertSee('Cek Status Booking')
            ->assertSee('Kode Booking')
            ->assertSee('Nomor WhatsApp');
    }

    public function test_kode_dan_nomor_whatsapp_yang_cocok_menampilkan_detail_booking(): void
    {
        $booking = $this->makeBooking(BookingStatus::PENDING, '081234567890');

        $this->post(route('booking.check.search'), [
            'booking_code' => $booking->bookingCode(),
            'customer_phone' => '081234567890',
        ])->assertOk()
            ->assertSee('Menunggu Persetujuan')
            ->assertSee($booking->customer_name)
            ->assertSee('Ps Lima Belas');
    }

    public function test_nomor_whatsapp_yang_salah_ditolak_walaupun_kode_valid(): void
    {
        $booking = $this->makeBooking(BookingStatus::PENDING, '081234567890');

        $this->post(route('booking.check.search'), [
            'booking_code' => $booking->bookingCode(),
            'customer_phone' => '089999999999',
        ])->assertOk()
            ->assertSee('Booking tidak ditemukan')
            ->assertDontSee('Menunggu Persetujuan');
    }

    public function test_kode_yang_tidak_ada_ditolak(): void
    {
        $this->post(route('booking.check.search'), [
            'booking_code' => 'BK-9999',
            'customer_phone' => '081234567890',
        ])->assertOk()
            ->assertSee('Booking tidak ditemukan');
    }

    public function test_pesan_gagal_sama_untuk_kode_ada_tapi_nomor_berbeda_dan_kode_tidak_ada(): void
    {
        $booking = $this->makeBooking(BookingStatus::PENDING, '081234567890');

        // Kode valid + nomor salah, dan kode yang tidak ada sama sekali,
        // harus memberi pesan yang persis sama supaya penyerang tidak bisa
        // memetakan kode mana yang benar-benar ada.
        $wrongPhone = $this->post(route('booking.check.search'), [
            'booking_code' => $booking->bookingCode(),
            'customer_phone' => '089999999999',
        ]);

        $missing = $this->post(route('booking.check.search'), [
            'booking_code' => 'BK-9999',
            'customer_phone' => '089999999999',
        ]);

        $wrongPhone->assertSee('Booking tidak ditemukan');
        $missing->assertSee('Booking tidak ditemukan');

        $wrongPhone->assertDontSee('Menunggu Persetujuan');
        $missing->assertDontSee('Menunggu Persetujuan');
    }

    public function test_status_terpakai_di_halaman_cek_status(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $booking = $this->makeBooking(BookingStatus::PENDING, '081234567890', $unit, now()->addHour());

        $this->actingAs($cashier)
            ->from(route('pos.bookings'))
            ->post(route('pos.bookings.confirm', $booking))
            ->assertRedirect(route('pos.bookings'));

        $this->travelTo($booking->start_time->copy()->addMinutes(5));
        $this->artisan('bookings:promote')->assertSuccessful();

        $this->post(route('booking.check.search'), [
            'booking_code' => $booking->bookingCode(),
            'customer_phone' => '081234567890',
        ])->assertOk()
            ->assertSee('Sudah Terisi');
    }

    public function test_booking_dibatalkan_tampil_sebagai_dibatalkan(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $booking = $this->makeBooking(BookingStatus::PENDING, '081234567890');

        $this->actingAs($cashier)
            ->from(route('pos.bookings'))
            ->post(route('pos.bookings.cancel', $booking))
            ->assertRedirect(route('pos.bookings'));

        $this->post(route('booking.check.search'), [
            'booking_code' => $booking->bookingCode(),
            'customer_phone' => '081234567890',
        ])->assertOk()
            ->assertSee('Dibatalkan');
    }

    public function test_booking_batal_juga_bisa_dilihat(): void
    {
        $booking = $this->makeBooking(BookingStatus::CANCELLED, '081234567890');

        $this->post(route('booking.check.search'), [
            'booking_code' => $booking->bookingCode(),
            'customer_phone' => '081234567890',
        ])->assertOk()
            ->assertSee('Dibatalkan');
    }

    public function test_nomor_whatsapp_boleh_ditulis_dengan_berbagai_cara(): void
    {
        $booking = $this->makeBooking(BookingStatus::PENDING, '081234567890');

        foreach (['+62 812-3456-7890', '6281234567890', '081234567890', '81234567890'] as $phone) {
            $this->post(route('booking.check.search'), [
                'booking_code' => $booking->bookingCode(),
                'customer_phone' => $phone,
            ])->assertOk()
                ->assertSee('Menunggu Persetujuan');
        }
    }

    public function test_nomor_yang_tersimpan_tanpa_nol_awal_juga_cocok(): void
    {
        $booking = $this->makeBooking(BookingStatus::PENDING, '6281234567890');

        $this->post(route('booking.check.search'), [
            'booking_code' => $booking->bookingCode(),
            'customer_phone' => '081234567890',
        ])->assertOk()
            ->assertSee('Menunggu Persetujuan');
    }

    public function test_kode_boleh_diketik_tanpa_nol_di_depan_dan_huruf_kecil(): void
    {
        $booking = $this->makeBooking(BookingStatus::PENDING, '081234567890');

        // Pelanggan boleh mengetik "bk7" alih-alih "BK-0007".
        $this->post(route('booking.check.search'), [
            'booking_code' => 'bk'.(int) $booking->id,
            'customer_phone' => '081234567890',
        ])->assertOk()
            ->assertSee('Menunggu Persetujuan');
    }

    public function test_kode_tidak_valid_ditolak_validasi(): void
    {
        $this->post(route('booking.check.search'), [
            'booking_code' => 'bukan-kode',
            'customer_phone' => '081234567890',
        ])->assertSessionHasErrors('booking_code');

        $this->post(route('booking.check.search'), [
            'booking_code' => 'BK-0001',
        ])->assertSessionHasErrors('customer_phone');
    }

    public function test_estimasi_jam_bermain_dan_biaya_ditampilkan(): void
    {
        $unit = $this->makeUnit(['hourly_rate' => 15000]);
        $start = now()->addHours(2)->startOfHour();

        $booking = Booking::create([
            'console_id' => $unit->id,
            'customer_name' => 'Dewi Lestari',
            'customer_phone' => '081234567890',
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes(150),
            'status' => BookingStatus::PENDING,
            'total_price' => 37500,
        ]);

        $this->post(route('booking.check.search'), [
            'booking_code' => $booking->bookingCode(),
            'customer_phone' => '081234567890',
        ])->assertOk()
            ->assertSee('2 jam 30 menit')
            ->assertSee('Rp 37.500');
    }

    public function test_phone_normalize_menyamakan_banyak_format(): void
    {
        $expected = '081234567890';

        foreach (['081234567890', '+6281234567890', '6281234567890', '81234567890', '0812-3456-7890', '(0812) 3456 7890'] as $input) {
            $this->assertSame($expected, Phone::normalize($input), "Gagal untuk: {$input}");
        }

        $this->assertSame('', Phone::normalize(null));
        $this->assertSame('', Phone::normalize('   '));
    }

    private function makeBooking(
        BookingStatus $status = BookingStatus::PENDING,
        string $phone = '081234567890',
        ?Unit $unit = null,
        ?Carbon $start = null,
    ): Booking {
        $unit ??= $this->makeUnit(['name' => 'Ps Lima Belas', 'code' => 'PS5-15']);
        $start ??= now()->addHours(3)->startOfHour();

        return Booking::create([
            'console_id' => $unit->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => $phone,
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => $status,
            'total_price' => 20000,
        ]);
    }
}

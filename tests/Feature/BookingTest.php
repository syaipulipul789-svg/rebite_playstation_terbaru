<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductCategory;
use App\Enums\RentalSessionStatus;
use App\Enums\UnitStatus;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\RentalSession;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingTest extends TestCase
{
    public function test_tamu_dapat_membuat_booking_pending_pada_unit_ready(): void
    {
        $unit = $this->makeUnit(['hourly_rate' => 12000]);

        $response = $this->from(route('customer.home'))->post(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'start_time' => now()->addHours(2)->format('Y-m-d H:i'),
            'duration_hours' => 2,
            'notes' => 'Minta remote PS5',
        ]);

        $response->assertRedirect(route('customer.home'));

        $booking = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertSame('24000.00', $booking->total_price);
        $this->assertSame(2, $booking->durationHours());
        $this->assertSame('Minta remote PS5', $booking->notes);
    }

    public function test_booking_melalui_ajax_mengembalikan_ringkasan_json(): void
    {
        $unit = $this->makeUnit(['hourly_rate' => 10000]);

        $this->postJson(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Sinta',
            'customer_phone' => '081234567890',
            'start_time' => now()->addHours(1)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertCreated()
            ->assertJsonStructure([
                'booking_code',
                'console_name',
                'start_time_label',
                'end_time_label',
                'total_price_label',
                'status' => ['value', 'label', 'hint', 'badge_class', 'is_occupied'],
            ])
            ->assertJsonPath('console_name', $unit->name)
            ->assertJsonPath('status.value', BookingStatus::PENDING->value)
            ->assertJsonPath('status.label', 'Menunggu Persetujuan')
            ->assertJsonPath('status.is_occupied', false);
    }

    public function test_status_booking_menunggu_sampai_kasir_menyetujui_lalu_menjadi_terisi(): void
    {
        $unit = $this->makeUnit(['hourly_rate' => 10000]);
        $start = now()->addHours(1)->startOfHour();

        $this->postJson(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Sinta',
            'customer_phone' => '081234567890',
            'start_time' => $start->format('Y-m-d H:i'),
            'duration_hours' => 2,
        ])->assertCreated();

        $booking = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        // 1. Right after submitting: waiting for cashier approval.
        $this->getJson(route('booking.status'))
            ->assertOk()
            ->assertJsonPath('bookings.0.code', $booking->bookingCode())
            ->assertJsonPath('bookings.0.status.value', BookingStatus::PENDING->value)
            ->assertJsonPath('bookings.0.status.label', 'Menunggu Persetujuan')
            ->assertJsonPath('bookings.0.status.is_occupied', false)
            ->assertJsonPath('bookings.0.remaining_seconds', null);

        // 2. Cashier approves: CONFIRMED, but not yet playing.
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $this->actingAs($cashier)
            ->from(route('pos.bookings'))
            ->post(route('pos.bookings.confirm', $booking))
            ->assertRedirect(route('pos.bookings'));

        $this->getJson(route('booking.status'))
            ->assertOk()
            ->assertJsonPath('bookings.0.status.value', BookingStatus::CONFIRMED->value)
            ->assertJsonPath('bookings.0.status.label', 'Disetujui')
            ->assertJsonPath('bookings.0.status.is_occupied', false);

        // 3. Start time arrives: unit is promoted, status becomes "Terisi".
        $this->travelTo($start->copy()->addMinutes(5));
        $this->artisan('bookings:promote')->assertSuccessful();

        $this->getJson(route('booking.status'))
            ->assertOk()
            ->assertJsonPath('bookings.0.status.value', BookingStatus::CONFIRMED->value)
            ->assertJsonPath('bookings.0.status.label', 'Sudah Terisi')
            ->assertJsonPath('bookings.0.status.is_occupied', true);
    }

    public function test_booking_yang_dibatalkan_terlihat_sebagai_dibatalkan(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();

        $this->postJson(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Rudi',
            'customer_phone' => '081234567890',
            'start_time' => now()->addHours(1)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertCreated();

        $booking = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        $this->actingAs($cashier)
            ->from(route('pos.bookings'))
            ->post(route('pos.bookings.cancel', $booking))
            ->assertRedirect(route('pos.bookings'));

        $this->getJson(route('booking.status'))
            ->assertOk()
            ->assertJsonPath('bookings.0.status.value', BookingStatus::CANCELLED->value)
            ->assertJsonPath('bookings.0.status.label', 'Dibatalkan');
    }

    public function test_halaman_status_booking_hanya_menampilkan_booking_milik_session(): void
    {
        $unit = $this->makeUnit();

        $this->postJson(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Sinta',
            'customer_phone' => '081234567890',
            'start_time' => now()->addHours(1)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertCreated();

        $mine = Booking::query()->where('console_id', $unit->id)->firstOrFail();

        // Booking milik orang lain yang dibuat di luar session ini.
        $otherUnit = $this->makeUnit(['code' => 'PS5-99', 'name' => 'PS5 Lain']);
        $other = Booking::create([
            'console_id' => $otherUnit->id,
            'customer_name' => 'Orang Lain',
            'customer_phone' => '089999999999',
            'start_time' => now()->addHours(1),
            'end_time' => now()->addHours(2),
            'status' => BookingStatus::PENDING,
            'total_price' => 10000,
        ]);

        $response = $this->getJson(route('booking.status'))->assertOk();

        $response->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.code', $mine->bookingCode());

        $this->assertNotContains(
            $other->bookingCode(),
            array_column($response->json('bookings'), 'code')
        );
    }

    public function test_pelanggan_lain_tidak_bisa_menautkan_booking_ke_pesanan_menebak_kode(): void
    {
        $victimUnit = $this->makeUnit();
        $victim = Booking::create([
            'console_id' => $victimUnit->id,
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'start_time' => now()->addHours(1),
            'end_time' => now()->addHours(2),
            'status' => BookingStatus::PENDING,
            'total_price' => 10000,
        ]);

        $ownUnit = $this->makeUnit(['code' => 'PS5-88', 'name' => 'PS5 Milik Pemesan']);
        $product = Product::create([
            'name' => 'Kopi',
            'category' => ProductCategory::SNACK,
            'barcode' => '8991002101291',
            'price' => 10000,
            'stock' => 5,
            'is_active' => true,
        ]);

        // Session pemesan tidak pernah memegang token booking milik korban,
        // jadi menebak kode "BK-####" tidak boleh attaching apa pun.
        $this->post(route('customer.order.store'), [
            'customer_name' => 'Pemesan',
            'customer_phone' => '081234567890',
            'unit_id' => $ownUnit->id,
            'booking_code' => $victim->bookingCode(),
        ])->assertRedirect(route('customer.order.index'));

        $token = session('customer_order_token');

        $this->withSession(['customer_order_token' => $token])
            ->postJson(route('customer.order.scan', $token), ['barcode' => '8991002101291'])
            ->assertCreated();

        $this->withSession(['customer_order_token' => $token])
            ->postJson(route('customer.order.place', $token))
            ->assertOk();

        $this->assertNull(
            Order::query()->where('token', $token)->firstOrFail()->booking_id,
            'Booking milik orang lain tidak boleh tertaut ke pesanan pemesan.'
        );
    }

    public function test_booking_unit_yang_sedang_dipakai_untuk_jam_berikutnya_diizinkan(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $unit = $this->makeUnit(['status' => UnitStatus::BUSY]);

        $this->startRunningSession($cashier, $shift, $unit, minutesAgo: 30, plannedMinutes: 60);
        $sessionEnd = now()->addMinutes(30);

        $this->post(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Agus',
            'customer_phone' => '081234567890',
            'start_time' => $sessionEnd->addMinutes(30)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertRedirect();

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_booking_ditolak_saat_bentrok_dengan_sesi_rental_berjalan(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $unit = $this->makeUnit(['status' => UnitStatus::BUSY]);

        $this->startRunningSession($cashier, $shift, $unit, minutesAgo: 30, plannedMinutes: 60);

        $this->from(route('customer.home'))->post(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Agus',
            'customer_phone' => '081234567890',
            'start_time' => now()->addMinutes(15)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_unit_dalam_servis_ditolak(): void
    {
        $unit = $this->makeUnit(['status' => UnitStatus::MAINTENANCE]);

        $this->from(route('customer.home'))->post(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Dewi',
            'customer_phone' => '081234567890',
            'start_time' => now()->addHours(1)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertSessionHasErrors('console_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_ditolak_saat_bentrok_dengan_booking_confirm_lain(): void
    {
        $unit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();

        $this->makeBooking($unit, $start, BookingStatus::CONFIRMED);

        $this->from(route('customer.home'))->post(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Eko',
            'customer_phone' => '081234567890',
            'start_time' => $start->copy()->addMinutes(30)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_booking_pending_tidak_menghalangi_booking_pending_lain(): void
    {
        $unit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();

        $this->makeBooking($unit, $start, BookingStatus::PENDING);

        $this->post(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Fajar',
            'customer_phone' => '081234567890',
            'start_time' => $start->copy()->addMinutes(30)->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertRedirect();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_booking_tidak_boleh_dimulai_di_masa_lalu(): void
    {
        $unit = $this->makeUnit();

        $this->from(route('customer.home'))->post(route('booking.store'), [
            'console_id' => $unit->id,
            'customer_name' => 'Gita',
            'customer_phone' => '081234567890',
            'start_time' => now()->subHour()->format('Y-m-d H:i'),
            'duration_hours' => 1,
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_kasir_dapat_melihat_booking_dan_mengkonfirmasinya(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $booking = $this->makeBooking($unit, now()->addHours(2)->startOfHour(), BookingStatus::PENDING);

        $this->actingAs($cashier)
            ->get(route('pos.bookings'))
            ->assertOk()
            ->assertSee($booking->bookingCode())
            ->assertSee('Menunggu');

        $this->actingAs($cashier)
            ->post(route('pos.bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);
    }

    public function test_konfirmasi_booking_ditolak_saat_bentrok_dengan_booking_confirm_lain(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();

        $first = $this->makeBooking($unit, $start, BookingStatus::CONFIRMED);
        $second = $this->makeBooking($unit, $start->copy()->addMinutes(30), BookingStatus::PENDING);

        $this->actingAs($cashier)
            ->post(route('pos.bookings.confirm', $second))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(BookingStatus::PENDING, $second->fresh()->status);
        $this->assertSame(BookingStatus::CONFIRMED, $first->fresh()->status);
    }

    public function test_kasir_dapat_membatalkan_booking(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $booking = $this->makeBooking($unit, now()->addHours(2)->startOfHour(), BookingStatus::PENDING);

        $this->actingAs($cashier)
            ->post(route('pos.bookings.cancel', $booking))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    public function test_kasir_dapat_menandai_booking_selesai(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $booking = $this->makeBooking($unit, now()->addHours(2)->startOfHour(), BookingStatus::CONFIRMED);

        $this->actingAs($cashier)
            ->post(route('pos.bookings.complete', $booking))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(BookingStatus::COMPLETED, $booking->fresh()->status);
    }

    public function test_konfirmasi_booking_yang_slotnya_sedang_berjalan_menandai_unit_terisi(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        // Slot sudah berjalan: pelanggan sudah di dalam ruangan, jadi
        // konfirmasi harus langsung menyalakan sesi rental supaya unit
        // berubah jadi "Terisi".
        $booking = $this->makeBooking($unit, now()->subMinutes(30), BookingStatus::PENDING);

        $this->actingAs($cashier)
            ->post(route('pos.bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHas('success');

        $confirmed = $booking->fresh();

        $this->assertSame(BookingStatus::CONFIRMED, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertSame($cashier->id, $confirmed->confirmed_by);
        $this->assertNotNull($confirmed->rental_session_id);

        $this->assertSame(UnitStatus::BUSY, $unit->fresh()->status);
        $this->assertTrue($confirmed->hasRunningSession());

        $session = $confirmed->rentalSession;
        $this->assertSame(RentalSessionStatus::RUNNING, $session->status);
        $this->assertSame($unit->id, $session->unit_id);
        $this->assertSame($shift->id, $session->shift_id);
        $this->assertSame(60, $session->planned_minutes);
    }

    public function test_unit_yang_menjadi_terisi_ditampilkan_pada_monitor_dengan_sisa_waktu(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $booking = $this->makeBooking($unit, now()->subMinutes(20), BookingStatus::PENDING);

        $this->actingAs($cashier)->post(route('pos.bookings.confirm', $booking));

        $response = $this->getJson(route('customer.display.data'))->assertOk();

        $unitPayload = collect($response->json('units'))->firstWhere('code', $unit->code);

        $this->assertSame(UnitStatus::BUSY->value, $unitPayload['status']);
        // Sisa waktu dihitung dari saat kasir mengonfirmasi, bukan dari jam
        // mulai booking — jadi pelanggan tetap dapat durasi penuh.
        $this->assertEqualsWithDelta(3600, $unitPayload['session']['remaining_seconds'], 5);
        $this->assertFalse($unitPayload['session']['is_time_up']);
    }

    public function test_konfirmasi_booking_masa_depan_tidak_langsung_menandai_unit_terisi(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $booking = $this->makeBooking($unit, now()->addHours(2)->startOfHour(), BookingStatus::PENDING);

        $this->actingAs($cashier)
            ->post(route('pos.bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHas('success');

        $confirmed = $booking->fresh();

        $this->assertSame(BookingStatus::CONFIRMED, $confirmed->status);
        // Unit harus tetap kosong sampai jadwalnya benar-benar tiba.
        $this->assertNull($confirmed->rental_session_id);
        $this->assertSame(UnitStatus::READY, $unit->fresh()->status);
        $this->assertDatabaseCount('rental_sessions', 0);
    }

    public function test_command_promote_menandai_unit_terisi_saat_jam_booking_mulai(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $start = now()->addHours(2)->startOfHour();
        $booking = $this->makeBooking($unit, $start, BookingStatus::CONFIRMED);

        $this->artisan('bookings:promote')->assertSuccessful();
        $this->assertDatabaseCount('rental_sessions', 0);

        // Maju ke jam mulai booking.
        $this->travelTo($start->copy()->addMinutes(5));
        $this->artisan('bookings:promote')->assertSuccessful();

        $this->assertNotNull($booking->fresh()->rental_session_id);
        $this->assertSame(UnitStatus::BUSY, $unit->fresh()->status);
        $this->assertDatabaseHas('rental_sessions', [
            'unit_id' => $unit->id,
            'status' => RentalSessionStatus::RUNNING->value,
        ]);
    }

    public function test_menandai_booking_selesai_mengembalikan_unit_ke_ready_dan_mencatat_pembayaran(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $unit = $this->makeUnit(['hourly_rate' => 12000]);
        $booking = $this->makeBooking($unit, now()->subMinutes(30), BookingStatus::PENDING);

        $this->actingAs($cashier)->post(route('pos.bookings.confirm', $booking));

        $this->actingAs($cashier)
            ->post(route('pos.bookings.complete', $booking), [
                'payment_method' => 'QRIS',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $completed = $booking->fresh();

        $this->assertSame(BookingStatus::COMPLETED, $completed->status);
        $this->assertFalse($completed->hasRunningSession());
        $this->assertSame(UnitStatus::READY, $unit->fresh()->status);

        $session = $completed->rentalSession;
        $this->assertSame(RentalSessionStatus::COMPLETED, $session->status);
        $this->assertSame(PaymentMethod::QRIS, $session->payment_method);
        $this->assertSame($shift->id, $session->shift_id);
    }

    public function test_booking_dengan_sesi_yang_masih_berjalan_tidak_bisa_dibatalkan(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $unit = $this->makeUnit();
        $booking = $this->makeBooking($unit, now()->subMinutes(30), BookingStatus::PENDING);

        $this->actingAs($cashier)->post(route('pos.bookings.confirm', $booking));

        $this->actingAs($cashier)
            ->post(route('pos.bookings.cancel', $booking))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);
        $this->assertSame(UnitStatus::BUSY, $unit->fresh()->status);
    }

    public function test_konfirmasi_booking_ditolak_saat_unit_sudah_dipakai_sesi_berjalan(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $unit = $this->makeUnit(['status' => UnitStatus::BUSY]);
        $booking = $this->makeBooking($unit, now()->subMinutes(30), BookingStatus::PENDING);

        $this->startRunningSession($cashier, $shift, $unit, minutesAgo: 10, plannedMinutes: 60);

        $this->actingAs($cashier)
            ->post(route('pos.bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(BookingStatus::PENDING, $booking->fresh()->status);
        $this->assertDatabaseCount('rental_sessions', 1);
    }

    private function makeBooking(Unit $unit, Carbon $start, BookingStatus $status): Booking
    {
        return Booking::create([
            'console_id' => $unit->id,
            'customer_name' => 'Pelanggan Tes',
            'customer_phone' => '081234567890',
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(1),
            'status' => $status,
            'total_price' => 12000.0,
        ]);
    }

    public function test_pesan_konfirmasi_whatsapp_berisi_nama_kode_unit_dan_jam(): void
    {
        $unit = $this->makeUnit(['name' => 'PS5 Reguler 7']);
        $start = now()->addHours(2)->startOfHour();
        $booking = $this->makeBooking($unit, $start, BookingStatus::CONFIRMED);

        $this->assertSame(
            sprintf(
                'Halo Pelanggan Tes, booking Anda dengan Kode %s untuk unit PS5 Reguler 7 '
                .'pada jam %s telah DISETUJUI oleh Kasir Rebite Playstation. '
                .'Silakan cek status di web kami. Terima kasih!',
                $booking->bookingCode(),
                $start->format('d M Y H:i'),
            ),
            $booking->whatsappApprovalMessage(),
        );
    }

    public function test_tautan_konfirmasi_whatsapp_mengarah_ke_wa_me_dengan_pesan_terisi(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $booking = $this->makeBooking($this->makeUnit(), now()->addHours(2)->startOfHour(), BookingStatus::CONFIRMED);

        $expectedUrl = 'https://wa.me/6281234567890?text='.rawurlencode($booking->whatsappApprovalMessage());

        $this->assertSame($expectedUrl, $booking->whatsappApprovalUrl());

        $this->actingAs($cashier)
            ->get(route('pos.bookings'))
            ->assertOk()
            ->assertSee('wa.me/6281234567890?text=', escape: false)
            ->assertSee('target="_blank"', escape: false);
    }

    public function test_tautan_whatsapp_tidak_muncul_sebelum_booking_disetujui(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $this->makeBooking($this->makeUnit(), now()->addHours(2)->startOfHour(), BookingStatus::PENDING);

        $this->actingAs($cashier)
            ->get(route('pos.bookings'))
            ->assertOk()
            ->assertDontSee('Kirim WA Konfirmasi')
            ->assertDontSee('wa.me', escape: false);
    }

    public function test_booking_dengan_nomor_tidak_valid_tidak_menghasilkan_tautan_whatsapp(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $booking = $this->makeBooking(
            $this->makeUnit(),
            now()->addHours(2)->startOfHour(),
            BookingStatus::CONFIRMED,
        );
        $booking->update(['customer_phone' => 'ADWD']);

        $this->assertNull($booking->fresh()->whatsappApprovalUrl());

        $this->actingAs($cashier)
            ->get(route('pos.bookings'))
            ->assertOk()
            ->assertSee('Nomor tidak valid')
            ->assertDontSee('wa.me', escape: false);
    }

    public function test_nomor_wa_me_dinormalkan_dari_beberapa_cara_penulisan(): void
    {
        $this->assertSame('628123456789', Phone::whatsappNumber('081234567890'));
        $this->assertSame('628123456789', Phone::whatsappNumber('+62 812-3456-7890'));
        $this->assertSame('628123456789', Phone::whatsappNumber('628123456789'));
        $this->assertSame('628123456789', Phone::whatsappNumber('81234567890'));
        $this->assertSame('6281234567890', Phone::whatsappNumber('0812345678900'));

        $this->assertNull(Phone::whatsappNumber('ADWD'));
        $this->assertNull(Phone::whatsappNumber('w2121easad'));
        $this->assertNull(Phone::whatsappNumber(''));
        $this->assertNull(Phone::whatsappNumber(null));
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
            'duration_minutes' => 0,
            'is_free_play' => false,
            'package_name' => 'Paket 1 Jam',
            'rental_fee' => 10000,
            'status' => RentalSessionStatus::RUNNING,
            'payment_method' => PaymentMethod::CASH,
        ]);
    }
}

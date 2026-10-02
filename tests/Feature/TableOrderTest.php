<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductCategory;
use App\Enums\RentalSessionStatus;
use App\Enums\ShiftStatus;
use App\Models\Order;
use App\Models\RentalSession;
use App\Services\OrderService;
use Tests\TestCase;

class TableOrderTest extends TestCase
{
    /* ------------------------------------------------------------------ *
     * Halaman menu
     * ------------------------------------------------------------------ */

    public function test_unit_tanpa_sesi_aktif_menampilkan_alert_tidak_aktif(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-07']);

        $this->get(route('table.order.show', ['unit_code' => $unit->code]))
            ->assertOk()
            ->assertViewIs('table-order.inactive')
            ->assertSee('Unit sedang tidak aktif. Silakan hubungi kasir untuk memulai main.');
    }

    public function test_menu_hanya_buka_saat_sesi_berjalan(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-08']);
        $product = $this->makeProduct(['name' => 'Kopi Susu', 'category' => ProductCategory::COFFEE]);

        $this->get(route('table.order.show', ['unit_code' => $unit->code]))
            ->assertOk()
            ->assertViewIs('table-order.inactive')
            ->assertDontSee('Kopi Susu');

        $session = $this->startRental($unit);

        $this->get(route('table.order.show', ['unit_code' => $unit->code]))
            ->assertOk()
            ->assertViewIs('table-order.show')
            ->assertSee('Kopi Susu')
            ->assertViewHas('session.id', $session->id);

        $this->assertTrue($session->isRunning());
    }

    public function test_produk_stok_nol_tidak_ditampilkan(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-09']);
        $this->startRental($unit);

        $this->makeProduct(['name' => 'Es Teh Manis', 'category' => ProductCategory::TEA, 'stock' => 3]);
        $this->makeProduct(['name' => 'Soda Kaleng', 'category' => ProductCategory::SWEET_DRINKS, 'stock' => 0]);

        $this->get(route('table.order.show', ['unit_code' => $unit->code]))
            ->assertOk()
            ->assertSee('Es Teh Manis')
            ->assertDontSee('Soda Kaleng');
    }

    public function test_kode_unit_tidak_dikenal_menampilkan_halaman_unit_tidak_ditemukan(): void
    {
        $this->get('/m/PS9-99')
            ->assertOk()
            ->assertViewIs('table-order.unknown')
            ->assertSee('PS9-99');
    }

    /* ------------------------------------------------------------------ *
     * Mengirim pesanan
     * ------------------------------------------------------------------ */

    public function test_pelanggan_bisa_mirim_pesanan_yang_tertaut_sesi_aktif(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-01']);
        $session = $this->startRental($unit);
        $product = $this->makeProduct(['name' => 'Indomie Goreng', 'price' => 8000, 'stock' => 10]);

        $response = $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ]);

        $response->assertOk()->assertJsonStructure(['redirect']);

        $order = Order::sole();

        $this->assertSame($session->id, $order->rental_session_id);
        $this->assertSame($unit->id, $order->unit_id);
        $this->assertSame(OrderStatus::PREPARING, $order->status);
        $this->assertSame(16000.0, $order->totalPrice());
        $this->assertNotNull($order->placed_at);

        $this->assertSame(8.0, (float) $product->fresh()->stock);
        $this->assertSame(2, $order->itemCount());
    }

    public function test_pesanan_ditolak_saat_unit_tidak_punya_sesi_aktif(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-02']);
        $product = $this->makeProduct();

        $this->get(route('table.order.show', ['unit_code' => $unit->code]))
            ->assertOk()
            ->assertViewIs('table-order.inactive');

        $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('unit');

        $this->assertSame(0, Order::count());
        $this->assertSame(20.0, (float) $product->fresh()->stock);
    }

    public function test_pesanan_ditolak_saat_sesi_sudah_selesai(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-18']);
        $session = $this->startRental($unit, $cashier);
        $product = $this->makeProduct(['stock' => 10]);

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $session->id), [
                'payment_method' => PaymentMethod::CASH->value,
            ])
            ->assertOk();

        $this->assertSame(RentalSessionStatus::COMPLETED, $session->fresh()->status);

        $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('unit')
            ->assertJsonPath('errors.unit.0', OrderService::INACTIVE_SESSION_MESSAGE);

        $this->assertSame(0, Order::count());
        $this->assertSame(10.0, (float) $product->fresh()->stock);
    }

    public function test_produk_sama_dengan_catatan_berbeda_jadi_dua_baris(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-03']);
        $session = $this->startRental($unit);
        $product = $this->makeProduct(['name' => 'Nasi Goreng', 'price' => 15000, 'stock' => 10]);

        $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [
                ['product_id' => $product->id, 'qty' => 1, 'notes' => 'Pedas'],
                ['product_id' => $product->id, 'qty' => 2, 'notes' => 'Telor Ceplok'],
            ],
        ])->assertOk();

        $order = Order::sole();

        $this->assertCount(2, $order->items);
        $this->assertEqualsCanonicalizing(
            ['Pedas', 'Telor Ceplok'],
            $order->items->pluck('notes')->all(),
        );

        // 1 x 15.000 + 2 x 15.000
        $this->assertSame(45000.0, $order->totalPrice());
        $this->assertSame($session->id, $order->rental_session_id);
    }

    public function test_produk_sama_tanpa_catatan_digabung_satu_baris(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-04']);
        $this->startRental($unit);
        $product = $this->makeProduct(['price' => 8000, 'stock' => 10]);

        $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
                ['product_id' => $product->id, 'qty' => 2],
            ],
        ])->assertOk();

        $order = Order::sole();

        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items->first()->qty);
        $this->assertSame(24000.0, $order->totalPrice());
        $this->assertSame(7.0, (float) $product->fresh()->stock);
    }

    public function test_stok_habis_menolak_pesanan_dan_tidak_mengurangi_stok(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-05']);
        $session = $this->startRental($unit);
        $product = $this->makeProduct(['stock' => 1]);

        $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [['product_id' => $product->id, 'qty' => 5]],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
        $this->assertSame(1.0, (float) $product->fresh()->stock);
        $this->assertSame(0.0, RentalSession::findOrFail($session->id)->ordersTotal());
    }

    /**
     * Satu produk bisa muncul berkali-kali di payload (mis. pelanggan menekan
     * tombol produk yang sama tiga kali). Baris yang melebihi stok harus
     * ditolak, bukan hanya baris yang sendirian melebihi stok.
     */
    public function test_baris_ganda_yang_melebihi_stok_ditolak(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-07']);
        $this->startRental($unit);
        $product = $this->makeProduct(['stock' => 3]);

        $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [
                ['product_id' => $product->id, 'qty' => 2, 'notes' => 'Pedas'],
                ['product_id' => $product->id, 'qty' => 2, 'notes' => 'Original'],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
        $this->assertSame(3.0, (float) $product->fresh()->stock);
    }

    public function test_keranjang_kosong_ditolak(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-06']);
        $this->startRental($unit);

        $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(0, Order::count());
    }

    /* ------------------------------------------------------------------ *
     * Pelacakan status
     * ------------------------------------------------------------------ */

    public function test_pelanggan_melacak_status_pesanan_sampai_diantar(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-10']);
        $this->startRental($unit, $cashier);
        $product = $this->makeProduct(['name' => 'Soda Kaleng', 'stock' => 5]);

        $order = $this->placeOrder($unit, $product);

        $this->get(route('table.order.tracking', ['unit_code' => $unit->code, 'token' => $order->token]))
            ->assertOk()
            ->assertViewIs('table-order.tracking')
            ->assertSee('Pesanan diterima')
            ->assertSee('Soda Kaleng');

        $statusUrl = route('table.order.status', ['unit_code' => $unit->code, 'token' => $order->token]);

        $this->getJson($statusUrl)
            ->assertOk()
            ->assertJsonPath('status', OrderStatus::PREPARING->value)
            ->assertJsonPath('status_label', 'Sedang Dimasak')
            ->assertJsonPath('is_final', false)
            ->assertJsonPath('item_count', 1);

        $this->actingAs($cashier)
            ->post(route('pos.orders.serve', $order))
            ->assertRedirect();

        $this->getJson($statusUrl)
            ->assertOk()
            ->assertJsonPath('status', OrderStatus::SERVED->value)
            ->assertJsonPath('status_label', 'Sudah Diantar');
    }

    public function test_token_pesanan_unit_lain_tidak_bisa_diakses(): void
    {
        $unitA = $this->makeUnit(['code' => 'PS3-11']);
        $unitB = $this->makeUnit(['code' => 'PS3-12']);

        $this->startRental($unitA);
        $this->startRental($unitB);

        $order = $this->placeOrder($unitA, $this->makeProduct());

        $this->get(route('table.order.tracking', ['unit_code' => $unitB->code, 'token' => $order->token]))
            ->assertRedirect(route('table.order.show', ['unit_code' => $unitB->code]));

        $this->getJson(route('table.order.status', ['unit_code' => $unitB->code, 'token' => $order->token]))
            ->assertNotFound();
    }

    public function test_status_berhenti_di_pakai_saat_pesanan_sudah_final(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-13']);
        $this->startRental($unit, $cashier);

        $order = $this->placeOrder($unit, $this->makeProduct());

        $this->actingAs($cashier)
            ->post(route('pos.orders.serve', $order))
            ->assertRedirect();

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $order->rental_session_id), [
                'payment_method' => PaymentMethod::CASH->value,
            ])
            ->assertOk();

        $this->getJson(route('table.order.status', ['unit_code' => $unit->code, 'token' => $order->token]))
            ->assertOk()
            ->assertJsonPath('status', OrderStatus::COMPLETED->value)
            ->assertJsonPath('is_final', true);
    }

    /* ------------------------------------------------------------------ *
     * Pembayaran gabungan
     * ------------------------------------------------------------------ */

    public function test_checkout_rental_melunasi_pesanan_qr_dan_menggabungkan_total(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier, 200000);

        $unit = $this->makeUnit(['code' => 'PS3-14']);
        $session = $this->startRental($unit, $cashier);
        $product = $this->makeProduct(['price' => 12000, 'stock' => 10]);

        $order = $this->placeOrder($unit, $product, quantity: 2);

        // 10.000 (paket) + 24.000 (2 x 12.000) = 34.000
        $this->assertSame(34000.0, $session->grandTotal());

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $session->id), [
                'payment_method' => PaymentMethod::CASH->value,
            ])
            ->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertSame(PaymentMethod::CASH, $order->payment_method);
        $this->assertSame($shift->id, $order->shift_id);
        $this->assertNotNull($order->settled_at);

        // Kas harus menghitung Rental + F&B tepat satu kali.
        $this->assertSame(34000.0, (float) $shift->fresh()->system_cash_revenue);
    }

    public function test_pesanan_qr_yang_dibatalkan_tidak_ikut_ditagihkan(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-15']);
        $session = $this->startRental($unit, $cashier);
        $product = $this->makeProduct(['price' => 12000, 'stock' => 10]);

        $kept = $this->placeOrder($unit, $product, quantity: 1);
        $cancelled = $this->placeOrder($unit, $product, quantity: 2);

        $this->actingAs($cashier)
            ->from(route('pos.orders'))
            ->post(route('pos.orders.cancel', $cancelled), ['reason' => 'Pelanggan berubah pikiran'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::CANCELLED, $cancelled->fresh()->status);

        // 10.000 (paket) + 12.000 (1 x 12.000); pesanan batal tidak ikut.
        $this->assertSame(22000.0, $session->grandTotal());

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $session->id), [
                'payment_method' => PaymentMethod::QRIS->value,
            ])
            ->assertOk();

        $this->assertSame(OrderStatus::COMPLETED, $kept->fresh()->status);
        $this->assertSame(22000.0, (float) $shift->fresh()->system_qris_revenue);
    }

    public function test_pesanan_qr_tidak_bisa_dibayar_manual_dari_pos(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-16']);
        $this->startRental($unit, $cashier);

        $order = $this->placeOrder($unit, $this->makeProduct());

        $this->actingAs($cashier)
            ->from(route('pos.orders'))
            ->post(route('pos.orders.settle', $order), [
                'payment_method' => PaymentMethod::CASH->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
    }

    public function test_pembatalan_sesi_ikut_membatalkan_pesanan_qr_dan_mengembalikan_stok(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-17']);
        $session = $this->startRental($unit, $cashier);
        $product = $this->makeProduct(['stock' => 10]);

        $order = $this->placeOrder($unit, $product, quantity: 3);

        $this->assertSame(7.0, (float) $product->fresh()->stock);

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.cancel', $session->id), ['reason' => 'Unit rusak'])
            ->assertOk();

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(10.0, (float) $product->fresh()->stock);
        $this->assertSame(0.0, $session->fresh()->ordersTotal());
    }

    /* ------------------------------------------------------------------ *
     * Lembar QR meja
     * ------------------------------------------------------------------ */

    public function test_owner_bisa_mencetak_lembar_qr_semua_unit(): void
    {
        $owner = $this->makeOwner();
        $this->makeUnit(['code' => 'PS3-01']);
        $this->makeUnit(['code' => 'PB-01']);

        $response = $this->actingAs($owner)->get(route('owner.units.qr'));

        $response->assertOk()
            ->assertViewIs('owner.units.qr')
            ->assertSee('PS3-01')
            ->assertSee('PB-01')
            // SVG dirender server-side supaya tajam saat dicetak.
            ->assertSee('<svg', false)
            ->assertSee(url('/m/PS3-01'), false)
            ->assertSee(url('/m/PB-01'), false);
    }

    public function test_lembar_qr_bisa_difilter_per_tipe(): void
    {
        $owner = $this->makeOwner();
        $this->makeUnit(['code' => 'PS3-02', 'type' => 'PS3']);
        $this->makeUnit(['code' => 'PB-02', 'type' => 'PLAYBOX']);

        $this->actingAs($owner)
            ->get(route('owner.units.qr', ['type' => 'PS3']))
            ->assertOk()
            ->assertSee('PS3-02')
            ->assertDontSee('PB-02');
    }

    public function test_halaman_qr_hanya_untuk_owner(): void
    {
        $this->actingAs($this->makeCashier())
            ->get(route('owner.units.qr'))
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ *
     * Layar kasir: badge, drawer order, dan aksi JSON
     * ------------------------------------------------------------------ */

    public function test_grid_unit_mengirim_ringkasan_pesanan_qr_untuk_badge(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-20']);
        $this->startRental($unit, $cashier);

        // Tanpa pesanan, badge tidak boleh muncul sama sekali.
        $this->actingAs($cashier)
            ->getJson(route('api.units.index'))
            ->assertOk()
            ->assertJsonPath('units.0.order_summary.count', 0)
            ->assertJsonPath('units.0.order_summary.latest', null);

        $product = $this->makeProduct(['name' => 'Es Teh Manis', 'stock' => 9]);
        $this->placeOrder($unit, $product, 2);

        $this->actingAs($cashier)
            ->getJson(route('api.units.index'))
            ->assertOk()
            ->assertJsonPath('units.0.order_summary.count', 1)
            ->assertJsonPath('units.0.order_summary.preparing_count', 1)
            ->assertJsonPath('units.0.order_summary.latest.headline', '2x Es Teh Manis')
            ->assertJsonPath('units.0.order_summary.latest.status', OrderStatus::PREPARING->value);
    }

    public function test_modal_detail_sesi_mengirim_pesanan_qr_lengkap_dengan_url_aksi(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-21']);
        $session = $this->startRental($unit, $cashier);
        $order = $this->placeOrder($unit, $this->makeProduct(['name' => 'Roti Bakar', 'stock' => 4]));

        $this->actingAs($cashier)
            ->getJson(route('api.sessions.show', $session->id))
            ->assertOk()
            ->assertJsonPath('session.orders.0.code', $order->code)
            ->assertJsonPath('session.orders.0.can_serve', true)
            // Tagihan harus memuat pesanan QR, bukan hanya sewa.
            // Paket 1 Jam = 10.000 + 1 pcs Roti Bakar @ 8.000.
            ->assertJsonPath('session.orders_total', 8000)
            ->assertJsonPath('session.grand_total', 18000)
            ->assertJsonPath('session.orders.0.serve_url', route('api.orders.serve', $order))
            ->assertJsonPath('session.orders.0.cancel_url', route('api.orders.cancel', $order));
    }

    public function test_kasir_bisa_menandai_pesanan_qr_sudah_diantar_lewat_api(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-22']);
        $session = $this->startRental($unit, $cashier);
        $order = $this->placeOrder($unit, $this->makeProduct(['stock' => 5]));

        $this->actingAs($cashier)
            ->postJson(route('api.orders.serve', $order))
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::SERVED->value)
            ->assertJsonPath('order.can_serve', false);

        $this->assertSame(OrderStatus::SERVED, $order->fresh()->status);

        // aksi ulang harus ditolak, bukan diam-diam berubah status
        $this->actingAs($cashier)
            ->postJson(route('api.orders.serve', $order))
            ->assertStatus(422);

        // status SERVED tetap ikut ditagihkan saat checkout
        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $session->id), [
                'payment_method' => PaymentMethod::CASH->value,
            ])
            ->assertOk();

        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
    }

    public function test_kasir_bisa_membatalkan_pesanan_qr_lewat_api_dan_stok_kembali(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-23']);
        $this->startRental($unit, $cashier);
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->placeOrder($unit, $product, 3);

        $this->assertSame(2.0, (float) $product->fresh()->stock);

        $this->actingAs($cashier)
            ->postJson(route('api.orders.cancel', $order), ['reason' => 'Pelanggan berubah pikiran'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::CANCELLED->value);

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(5.0, (float) $product->fresh()->stock);
    }

    public function test_nota_cetak_mencetak_pesanan_qr_meja(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-25']);
        $session = $this->startRental($unit, $cashier);
        $order = $this->placeOrder($unit, $this->makeProduct(['name' => 'Kentang Goreng', 'stock' => 6]));

        $this->actingAs($cashier)
            ->postJson(route('api.sessions.complete', $session->id), [
                'payment_method' => PaymentMethod::CASH->value,
            ])
            ->assertOk();

        $this->actingAs($cashier)
            ->get(route('pos.receipt', $session->id))
            ->assertOk()
            ->assertViewIs('pos.receipt')
            ->assertSee('Pesanan QR Meja')
            ->assertSee($order->code)
            ->assertSee('Kentang Goreng')
            ->assertSee('Subtotal QR meja');
    }

    public function test_halaman_pesanan_menandai_order_qr_dibayar_lewat_checkout_sewa(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);

        $unit = $this->makeUnit(['code' => 'PS3-26']);
        $session = $this->startRental($unit, $cashier);
        $this->placeOrder($unit, $this->makeProduct(['stock' => 4]));

        $this->actingAs($cashier)
            ->get(route('pos.orders', ['status' => 'awaiting']))
            ->assertOk()
            ->assertSee('QR Meja')
            ->assertSee('Bayar saat checkout sewa')
            // Order QR tidak boleh punya tombol terima bayar manual.
            ->assertDontSee(route('pos.orders.settle', $session->orders()->first()));
    }

    public function test_aksi_pesanan_qr_hanya_bisa_dilakukan_kasir_yang_aktif(): void
    {
        $unit = $this->makeUnit(['code' => 'PS3-24']);
        $this->startRental($unit);
        $order = $this->placeOrder($unit, $this->makeProduct());

        // Tanpa shift OPEN, middleware `shift.active` harus menahan aksi.
        $this->actingAs($this->makeCashier())
            ->postJson(route('api.orders.serve', $order))
            ->assertStatus(423);

        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
    }

    /* ------------------------------------------------------------------ *
     * Helper
     * ------------------------------------------------------------------ */

    private function startRental($unit, $cashier = null): RentalSession
    {
        $cashier ??= $this->makeCashier('kasir-'.$unit->code);

        // Reuse shift OPEN milik kasir yang sama kalau sudah ada. Membuka
        // shift kedua membuat `$shift` di test dan shift yang dipakai sesi
        // berbeda, sehingga assertion rekap kas menguji shift yang salah.
        if (! $cashier->shifts()->where('status', ShiftStatus::OPEN)->exists()) {
            $this->makeOpenShift($cashier);
        }

        $sessionId = $this->actingAs($cashier)
            ->postJson(route('api.sessions.store'), [
                'unit_id' => $unit->id,
                'rate_package_id' => $this->makePackage()->id,
            ])
            ->assertCreated()
            ->json('session.id');

        return RentalSession::findOrFail($sessionId);
    }

    private function placeOrder($unit, $product, int $quantity = 1): Order
    {
        $this->postJson(route('table.order.store', ['unit_code' => $unit->code]), [
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
            'items' => [['product_id' => $product->id, 'qty' => $quantity]],
        ])->assertOk();

        return Order::latest('id')->firstOrFail();
    }
}

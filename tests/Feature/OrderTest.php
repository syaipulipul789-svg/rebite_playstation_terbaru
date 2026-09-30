<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductCategory;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Support\Barcode;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    public function test_pelanggan_baru_dibuatkan_pesanan_draft_dan_token_disimpan_di_session(): void
    {
        $this->post(route('customer.order.store'), [
            'customer_name' => 'Rina Wijaya',
            'customer_phone' => '081234567890',
        ])->assertRedirect(route('customer.order.index'));

        $order = Order::query()->sole();

        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame('Rina Wijaya', $order->customer_name);
        $this->assertNotNull($order->token);
        $this->assertSame($order->token, session('customer_order_token'));
    }

    public function test_halaman_scan_menampilkan_keranjang_yang_sudah_ada_di_session(): void
    {
        $order = $this->openOrder();

        $this->withSession(['customer_order_token' => $order->token])
            ->get(route('customer.order.index'))
            ->assertOk()
            ->assertSee($order->code);
    }

    public function test_pemindaian_barcode_menambah_produk_dan_mengurangi_stok(): void
    {
        $order = $this->openOrder();
        $product = $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10]);

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215'])
            ->assertCreated()
            ->assertJsonPath('order.total_label', 'Rp 8.000')
            ->assertJsonPath('order.items.0.product_name', 'Indomie Goreng')
            ->assertJsonPath('order.items.0.qty', 1);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 1,
        ]);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_barcode_yang_dipindai_ulang_menaikkan_qty_bukan_membuat_baris_baru(): void
    {
        $order = $this->openOrder();
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10]);

        $this->withSession(['customer_order_token' => $order->token]);

        $this->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215'])->assertCreated();
        $this->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215', 'qty' => 2])
            ->assertCreated()
            ->assertJsonCount(1, 'order.items')
            ->assertJsonPath('order.items.0.qty', 3);

        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(7, Product::query()->sole()->stock);
    }

    public function test_barcode_dengan_format_berbeda_tetap_mengenali_produk_yang_sama(): void
    {
        $order = $this->openOrder();
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10]);

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '899-1002-101215'])
            ->assertCreated()
            ->assertJsonPath('order.items.0.qty', 1);
    }

    public function test_barcode_tidak_dikenal_ditolak_tanpa_mengubah_stok(): void
    {
        $order = $this->openOrder();
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10]);

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '0000000000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');

        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(10, Product::query()->sole()->stock);
    }

    public function test_pemindaian_ditolak_saat_stok_tidak_cukup(): void
    {
        $order = $this->openOrder();
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 1]);

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215', 'qty' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');

        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(1, Product::query()->sole()->stock);
    }

    public function test_pelanggan_lain_tidak_bisa_menambah_item_ke_pesanan_bukan_miliknya(): void
    {
        $order = $this->openOrder();
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10]);

        $this->withSession(['customer_order_token' => (string) Str::uuid()])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215'])
            ->assertForbidden();

        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_pelanggan_dapat_mengubah_dan_menghapus_item(): void
    {
        $order = $this->openOrder();
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10]);

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215'])
            ->assertCreated();

        $item = $order->items()->sole();

        $this->patchJson(route('customer.order.items.update', [$order->token, $item->id]), ['qty' => 4])
            ->assertOk()
            ->assertJsonPath('order.total_label', 'Rp 32.000');

        $this->assertSame(6, Product::query()->sole()->stock);

        $this->deleteJson(route('customer.order.items.destroy', [$order->token, $item->id]))
            ->assertOk()
            ->assertJsonPath('order.total_label', 'Rp 0');

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
        $this->assertSame(10, Product::query()->sole()->stock);
    }

    public function test_menurunkan_qty_mengembalikan_seluruh_stok_baris(): void
    {
        $order = $this->openOrder();
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10]);

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215', 'qty' => 4])
            ->assertCreated();

        $item = $order->items()->sole();

        $this->patchJson(route('customer.order.items.update', [$order->token, $item->id]), ['qty' => 0])
            ->assertOk();

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
        $this->assertSame(10, Product::query()->sole()->stock);
    }

    public function test_pesanan_kosong_tidak_bisa_dikirim_ke_kasir(): void
    {
        $order = $this->openOrder();

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.place', $order->token))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_pelanggan_mengirim_pesanan_lalu_item_tidak_bisa_diubah(): void
    {
        $order = $this->placeOrder();

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215'])
            ->assertStatus(422);

        $this->assertSame(OrderStatus::PLACED, $order->fresh()->status);
    }

    public function test_pelanggan_yang_membuka_pesanan_baru_tidak_membatalkan_pesanan_yang_sudah_dibayar(): void
    {
        $placed = $this->placeOrder();

        $this->withSession(['customer_order_token' => $placed->token])
            ->post(route('customer.order.store'), [
                'customer_name' => 'Rina Wijaya',
                'customer_phone' => '081234567890',
            ])->assertRedirect();

        // Pesanan yang sudah dikirim kasir harus tetap utuh supaya uang
        // yang menunggu pembayaran tidak hilang, dan stoknya tetap terkunci.
        $this->assertSame(OrderStatus::PLACED, $placed->fresh()->status);
        $this->assertSame(9, Product::query()->sole()->stock);
    }

    public function test_kasir_menerima_pembayaran_dan_uangnya_masuk_rekap_shift(): void
    {
        $cashier = $this->makeCashier();
        $shift = $this->makeOpenShift($cashier);
        $order = $this->placeOrder();

        $this->actingAs($cashier)
            ->post(route('pos.orders.settle', $order), ['payment_method' => 'CASH'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $settled = $order->fresh();

        $this->assertSame(OrderStatus::COMPLETED, $settled->status);
        $this->assertSame(PaymentMethod::CASH, $settled->payment_method);
        $this->assertSame($shift->id, $settled->shift_id);
        $this->assertSame($cashier->id, $settled->user_id);
        $this->assertNotNull($settled->settled_at);
        $this->assertEquals(8000.0, $shift->fresh()->system_cash_revenue);
        $this->assertEquals(0.0, $shift->fresh()->system_qris_revenue);
    }

    public function test_halaman_pos_menampilkan_pesanan_menunggu_bayar(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $order = $this->placeOrder();

        $this->actingAs($cashier)
            ->get(route('pos.orders'))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Terima Bayar');
    }

    public function test_kasir_membatalkan_pesanan_mengembalikan_stok(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $order = $this->placeOrder();

        $this->actingAs($cashier)
            ->post(route('pos.orders.cancel', $order), ['reason' => 'Pelanggan salah scan'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $cancelled = $order->fresh();

        $this->assertSame(OrderStatus::CANCELLED, $cancelled->status);
        $this->assertSame(0.0, (float) $cancelled->total_price);
        $this->assertSame('Pelanggan salah scan', $cancelled->notes);
        $this->assertSame(10, Product::query()->sole()->stock);
        // Baris item disimpan supaya histori pesanan yang dibatalkan bisa
        // ditelusuri.
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
    }

    public function test_pesanan_yang_sudah_dibayar_tidak_bisa_dibatalkan(): void
    {
        $cashier = $this->makeCashier();
        $this->makeOpenShift($cashier);
        $order = $this->placeOrder();

        $this->actingAs($cashier)->post(route('pos.orders.settle', $order), ['payment_method' => 'CASH']);

        $this->actingAs($cashier)
            ->post(route('pos.orders.cancel', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
    }

    public function test_pesanan_bisa_ditautkan_ke_booking_pelanggan(): void
    {
        $unit = $this->makeUnit();
        $booking = Booking::create([
            'console_id' => $unit->id,
            'customer_name' => 'Rina Wijaya',
            'customer_phone' => '081234567890',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'status' => BookingStatus::CONFIRMED,
            'total_price' => 12000.0,
        ]);

        // Booking hanya boleh ditautkan kalau session browser ini yang
        // membuatnya, jadi token publiknya ikut dibawa. Ini kondisi nyata
        // setelah pelanggan membuat booking lewat `POST /booking`.
        $this->withSession(['customer_booking_tokens' => [$booking->public_token]])
            ->post(route('customer.order.store'), [
                'customer_name' => 'Rina Wijaya',
                'customer_phone' => '081234567890',
                'booking_code' => $booking->bookingCode(),
            ])->assertRedirect();

        $this->assertSame($booking->id, Order::query()->sole()->booking_id);
    }

    public function test_produk_baru_dari_owner_langsung_dapat_barcode(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('owner.products.store'), [
            'name' => 'Teh Pucuk',
            'category' => ProductCategory::DRINK->value,
            'price' => 5000,
            'stock' => 30,
            'low_stock_threshold' => 5,
        ])->assertRedirect();

        $product = Product::query()->sole();

        $this->assertTrue($product->hasBarcode());
        $this->assertSame(13, strlen($product->barcode));
        $this->assertSame(Barcode::normalize($product->barcode), $product->barcode);
    }

    public function test_barcode_label_dapat_dicetak_oleh_owner(): void
    {
        $owner = $this->makeOwner();
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10]);

        $this->actingAs($owner)
            ->get(route('owner.products.labels'))
            ->assertOk()
            ->assertSee('8991002101215');
    }

    private function openOrder(): Order
    {
        $this->post(route('customer.order.store'), [
            'customer_name' => 'Rina Wijaya',
            'customer_phone' => '081234567890',
        ]);

        return Order::query()->latest('id')->firstOrFail();
    }

    private function placeOrder(): Order
    {
        $this->makeProduct(['barcode' => '8991002101215', 'stock' => 10, 'price' => 8000]);

        $order = $this->openOrder();

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.scan', $order->token), ['barcode' => '8991002101215'])
            ->assertCreated();

        $this->withSession(['customer_order_token' => $order->token])
            ->postJson(route('customer.order.place', $order->token))
            ->assertOk();

        return $order->fresh();
    }
}

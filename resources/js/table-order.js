/**
 * Halaman pemesanan mandiri /m/{unit_code}.
 *
 * Keranjang disimpan sepenuhnya di browser lalu dikirim sebagai satu POST,
 * bukan diisi baris demi baris lewat AJAX. Alasannya stok harus selalu
 * dicek server di detik yang sama pesanan dibuat; kalau item dikirim satu per
 * satu, pelanggan bisa melihat "stok cukup" lalu gagal di tengah jalan
 * setelah sebagian item sudah masuk. Satu POST = satu transaksi.
 *
 * Identitas (nama + WA) disimpan di localStorage dengan key per halaman unit
 * supaya pesan kedua di unit yang sama tidak perlu mengetik ulang.
 */
const IDENTITY_KEY = 'rebite.tableOrder.identity';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('tableMenu', (config = {}) => ({
        storeUrl: config.storeUrl ?? null,
        menu: config.menu ?? [],
        remainingSeconds: config.remainingSeconds ?? 0,

        /** `uid` unik supaya produk dengan catatan berbeda punya key sendiri. */
        cart: [],
        cartOpen: false,
        formOpen: false,

        customerName: '',
        customerPhone: '',
        notes: '',

        busy: false,
        error: null,

        remainingLabel: '00:00',

        /** @type {number|null} */
        ticker: null,

        nextUid: 1,

        init() {
            this.loadIdentity();
            this.remainingLabel = this.clock(this.remainingSeconds);
            this.startTicker();
        },

        destroy() {
            if (this.ticker !== null) {
                clearInterval(this.ticker);
                this.ticker = null;
            }
        },

        /* ---------------------------------------------------------------- *
         * Sisa waktu main
         * ---------------------------------------------------------------- */

        /**
         * Berdetak lokal tiap detik dari nilai yang dikirim server. Tidak
         * perlu polling server hanya untuk jam — polling hanya perlu kalau
         * halaman ini perlu tahu status pesanan.
         */
        startTicker() {
            if (this.ticker !== null) {
                return;
            }

            this.ticker = setInterval(() => {
                if (this.remainingSeconds > 0) {
                    this.remainingSeconds--;
                }

                this.remainingLabel = this.clock(this.remainingSeconds);
            }, 1000);
        },

        clock(seconds) {
            const safe = Math.max(0, seconds);
            const minutes = Math.floor(safe / 60);
            const rest = safe % 60;

            return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
        },

        /* ---------------------------------------------------------------- *
         * Keranjang
         * ---------------------------------------------------------------- */

        /**
         * Produk yang sama digabung selama belum punya catatan. Begitu ada
         * catatan, baris baru dibuat: "pedas" dan "tidak pedas" adalah dua
         * permintaan berbeda dan kasir harus membacanya terpisah.
         */
        add(productId) {
            this.error = null;

            const product = this.findProduct(productId);

            if (!product) {
                return;
            }

            const existing = this.cart.find(
                (line) => line.productId === productId && (line.notes ?? '').trim() === '',
            );

            if (existing) {
                if (existing.qty >= product.stock) {
                    this.error = `Stok ${product.name} tinggal ${product.stock}.`;
                    return;
                }

                existing.qty++;
            } else {
                this.cart.push({
                    uid: this.nextUid++,
                    productId: product.id,
                    name: product.name,
                    price: product.price,
                    priceLabel: product.price_label,
                    stock: product.stock,
                    qty: 1,
                    notes: '',
                });
            }

            this.cartOpen = true;
        },

        increment(index) {
            const line = this.cart[index];

            if (!line) {
                return;
            }

            if (line.qty >= line.stock) {
                this.error = `Stok ${line.name} tinggal ${line.stock}.`;
                return;
            }

            line.qty++;
        },

        decrement(index) {
            const line = this.cart[index];

            if (!line) {
                return;
            }

            line.qty--;

            if (line.qty <= 0) {
                this.cart.splice(index, 1);
            }
        },

        remove(index) {
            this.cart.splice(index, 1);
        },

        qtyOf(productId) {
            const line = this.cart.find(
                (item) => item.productId === productId && (item.notes ?? '').trim() === '',
            );

            return line ? line.qty : 0;
        },

        inCart(productId) {
            return this.qtyOf(productId) > 0;
        },

        findProduct(productId) {
            for (const group of this.menu) {
                const found = group.products.find((product) => product.id === productId);

                if (found) {
                    return found;
                }
            }

            return null;
        },

        get cartCount() {
            return this.cart.reduce((sum, line) => sum + line.qty, 0);
        },

        get total() {
            return this.cart.reduce((sum, line) => sum + Number(line.price) * line.qty, 0);
        },

        get totalLabel() {
            return window.Rebite?.rupiah
                ? window.Rebite.rupiah(this.total)
                : `Rp ${this.total.toLocaleString('id-ID')}`;
        },

        /* ---------------------------------------------------------------- *
         * Kirim
         * ---------------------------------------------------------------- */

        openForm() {
            if (this.cart.length === 0) {
                this.error = 'Keranjang masih kosong.';
                return;
            }

            this.error = null;
            this.formOpen = true;
        },

        closeForm() {
            if (!this.busy) {
                this.formOpen = false;
            }
        },

        submit() {
            this.error = null;

            if (this.cart.length === 0 || this.busy) {
                return;
            }

            if (this.customerName.trim() === '' || this.customerPhone.trim() === '') {
                this.error = 'Nama dan nomor WhatsApp wajib diisi.';
                return;
            }

            this.busy = true;

            const form = new FormData();
            form.append('customer_name', this.customerName.trim());
            form.append('customer_phone', this.customerPhone.trim());

            if (this.notes.trim() !== '') {
                form.append('notes', this.notes.trim());
            }

            this.cart.forEach((line, index) => {
                form.append(`items[${index}][product_id]`, line.productId);
                form.append(`items[${index}][qty]`, line.qty);

                if ((line.notes ?? '').trim() !== '') {
                    form.append(`items[${index}][notes]`, line.notes.trim());
                }
            });

            window.axios
                .post(this.storeUrl, form)
                .then((response) => {
                    this.saveIdentity();
                    window.location.href = response.data.redirect;
                })
                .catch((error) => {
                    this.busy = false;

                    const status = error.response?.status;

                    if (status === 422) {
                        this.error = this.firstMessage(error.response.data);
                        return;
                    }

                    if (status === 419) {
                        this.error = 'Sesi halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.';
                        return;
                    }

                    this.error = error.response?.data?.message
                        ?? 'Pesanan gagal dikirim. Silakan coba lagi.';
                });
        },

        /**
         * Laravel mengembalikan satu pesan per field; cukup tampilkan yang
         * pertama karena kasir melihat pesan yang sama di layarnya.
         */
        firstMessage(data) {
            const errors = data?.errors ?? {};

            for (const messages of Object.values(errors)) {
                if (Array.isArray(messages) && messages.length > 0) {
                    return messages[0];
                }

                if (typeof messages === 'string') {
                    return messages;
                }
            }

            return 'Data pesanan tidak lengkap.';
        },

        /* ---------------------------------------------------------------- *
         * Identitas tersimpan per unit
         * ---------------------------------------------------------------- */

        loadIdentity() {
            try {
                const stored = JSON.parse(localStorage.getItem(IDENTITY_KEY) ?? '{}');
                const saved = stored?.[window.location.pathname] ?? {};

                this.customerName = saved.name ?? '';
                this.customerPhone = saved.phone ?? '';
                this.notes = saved.notes ?? '';
            } catch {
                this.customerName = '';
                this.customerPhone = '';
                this.notes = '';
            }
        },

        saveIdentity() {
            try {
                const stored = JSON.parse(localStorage.getItem(IDENTITY_KEY) ?? '{}');

                stored[window.location.pathname] = {
                    name: this.customerName,
                    phone: this.customerPhone,
                    notes: this.notes,
                };

                localStorage.setItem(IDENTITY_KEY, JSON.stringify(stored));
            } catch {
                // Penyimpanan browser penuh atau ditolak tidak boleh
                // menggagalkan pesanan yang sudah berhasil dibuat.
            }
        },
    }));

    /**
     * Halaman pelacakan /m/{unit_code}/orders/{token}.
     *
     * Poll berhenti sendiri begitu status final (lunas atau batal) supaya HP
     * pelanggan yang ditinggal terbuka semalaman tidak terus menabrak server.
     */
    window.Alpine.data('tableOrderStatus', (config = {}) => ({
        statusUrl: config.statusUrl ?? null,
        pollMs: config.pollMs ?? 6000,
        initial: config.initial ?? null,

        status: null,
        statusLabel: null,
        badgeClass: null,
        items: [],
        itemCount: 0,
        total: null,
        isFinal: false,

        /** @type {number|null} */
        poller: null,

        init() {
            if (this.initial) {
                this.apply(this.initial);
            }

            if (!this.isFinal) {
                this.poller = setInterval(() => this.refresh(), this.pollMs);
            }
        },

        destroy() {
            if (this.poller !== null) {
                clearInterval(this.poller);
                this.poller = null;
            }
        },

        refresh() {
            if (this.isFinal) {
                this.stop();
                return;
            }

            window.axios
                .get(this.statusUrl)
                .then((response) => this.apply(response.data))
                .catch(() => {
                    // Jari selang atau offline: diamkan saja, poll berikutnya
                    // akan mencoba lagi.
                });
        },

        apply(data) {
            if (!data || typeof data.status !== 'string') {
                return;
            }

            this.status = data.status;
            this.statusLabel = data.status_label ?? this.statusLabel;
            this.badgeClass = data.badge_class ?? this.badgeClass;
            this.total = data.total ?? this.total;

            if (Array.isArray(data.items)) {
                this.items = data.items;
            }

            this.itemCount = Number(data.item_count ?? this.items.length);
            this.isFinal = Boolean(data.is_final);

            if (this.isFinal) {
                this.stop();
            }
        },

        stop() {
            if (this.poller !== null) {
                clearInterval(this.poller);
                this.poller = null;
            }
        },

        /**
         * Langkah dianggap tercapai kalau status sekarang sudah melewati
         * langkah itu. Pesanan batal tidak mencapai langkah mana pun.
         */
        stepReached(index, key) {
            if (this.status === 'CANCELLED') {
                return false;
            }

            const flow = ['PREPARING', 'SERVED', 'COMPLETED'];
            const current = flow.indexOf(this.status);
            const target = flow.indexOf(key);

            if (current === -1 || target === -1) {
                return false;
            }

            return current >= target;
        },
    }));
});

/**
 * Grid Monitoring Unit real-time.
 *
 * Alur:
 *  1. Fetch snapshot dari /api/units, lalu polling tiap `pollInterval` ms.
 *  2. Countdown setiap detik dihitung dari `planned_end_timestamp` (epoch ms)
 *     yang dikirim server — jadi jam lokal kasir yang salah 5 menit tidak
 *     membuat timer meleset.
 *  3. Saat sisa waktu <= 0 card berubah jadi Kuning + bunyi alert (satu kali
 *     per sesi, ditandai di frontend lewat Set `alertedSessions`).
 *  4. Klik card -> modal: Hijau = Mulai Sewa, Merah = Detail Sewa.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('unitGrid', (config) => ({
        /* Konfigurasi dari Blade */
        apiIndex: config.apiIndex,
        apiMeta: config.apiMeta,
        apiStore: config.apiStore,
        apiSession: config.apiSession,
        csrfToken: config.csrfToken,
        pollInterval: config.pollInterval ?? 5000,

        /* State */
        units: [],
        loading: true,
        lastSync: null,
        serverSkewMs: 0,
        filter: 'ALL',
        query: '',
        typeFilter: 'ALL',

        /* Timer loop */
        ticker: null,
        poller: null,
        alertedSessions: new Set(),

        /**
         * Order terakhir yang sudah dilihat kasir, per sesi.
         *
         * Disimpan di memori (bukan localStorage) supaya badge "baru" hilang
         * begitu halaman kasir ditutup — kasir baru yang membuka grid harus
         * melihat semua pesanan yang masih menunggu.
         */
        seenOrders: new Map(),

        /* Modal state */
        modal: null, // 'start' | 'detail' | 'receipt'
        activeUnit: null,
        meta: { rate_packages: [], products: [] },
        metaLoading: false,
        detail: null,
        receipt: null,
        submitting: false,
        errors: {},

        /* Form state */
        form: {
            rate_package_id: null,
            is_free_play: false,
            open_play_minutes: 120,
            payment_method: 'CASH',
            note: '',
            extra_minutes: 30,
            product_id: null,
            qty: 1,
        },

        /* ---------------------------------------------------------------- */

        init() {
            this.refresh();

            this.poller = setInterval(() => this.refresh(), this.pollInterval);
            this.ticker = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            clearInterval(this.poller);
            clearInterval(this.ticker);
        },

        /* ---------------------------------------------------------------- *
         * Sinkronisasi data
         * ---------------------------------------------------------------- */

        async refresh() {
            try {
                const { data } = await window.axios.get(this.apiIndex);

                this.units = data.units;
                this.serverSkewMs = data.server_timestamp - Date.now();
                this.lastSync = new Date();
                this.loading = false;

                this.markSeenOrders();
                this.syncOpenDetail();
            } catch (error) {
                this.loading = false;
                this.handleAuthError(error);

                clearInterval(this.poller);
            }
        },

        /**
         * Tandai pesanan yang sudah ada saat halaman pertama dibuka sebagai
         * "sudah dilihat" supaya badge tidak berdering untuk pesanan lama.
         */
        markSeenOrders() {
            this.units.forEach((unit) => this.seenOrders.set(unit.session?.id, unit.order_summary?.latest?.id ?? null));
        },

        /**
         * Badge berdering hanya untuk pesanan yang masuk setelah kasir membuka
         * grid dan belum pernah dibuka detailnya.
         */
        hasUnseenOrder(unit) {
            const latest = unit.order_summary?.latest;

            return Boolean(latest) && this.seenOrders.get(unit.session?.id) !== latest.id;
        },

        orderBadgeClass(unit) {
            const preparing = unit.order_summary?.preparing_count > 0;

            return preparing
                ? 'bg-amber-400/90 text-amber-950'
                : 'bg-brand-400/90 text-brand-950';
        },

        /**
         * Modal detail tetap terbuka & ikut ter-update saat polling.
         *
         * Snapshot dari /api/units tidak memuat item F&B maupun total, jadi
         * timer di-update dari snapshot, sementara data tagihan lengkap
         * diambil dari /api/sessions/{id} agar tidak memakai data basi.
         */
        syncOpenDetail() {
            if (this.modal !== 'detail' || !this.detail?.session) return;

            const fresh = this.units.find((u) => u.session?.id === this.detail.session.id);

            if (!fresh) {
                this.closeModal();
                return;
            }

            this.detail = {
                ...this.detail,
                ...fresh,
                session: { ...this.detail.session, ...fresh.session },
            };
        },

        /* ---------------------------------------------------------------- *
         * Timer per detik
         * ---------------------------------------------------------------- */

        tick() {
            const now = Date.now() + this.serverSkewMs;

            this.units.forEach((unit) => {
                if (!unit.session) return;

                unit.session.remaining_seconds = Math.max(
                    0,
                    Math.round((unit.session.planned_end_timestamp - now) / 1000),
                );
                unit.session.is_time_up = unit.session.remaining_seconds <= 0;

                if (
                    unit.session.is_time_up &&
                    !this.alertedSessions.has(unit.session.id)
                ) {
                    this.alertedSessions.add(unit.session.id);
                    window.Alpine.store('sound').alert();
                    window.Alpine.store('toast').warning(
                        `Waktu ${unit.name} (${unit.code}) habis!`,
                    );
                }
            });
        },

        /* ---------------------------------------------------------------- *
         * Filter & tampilan
         * ---------------------------------------------------------------- */

        get types() {
            return ['ALL', ...new Set(this.units.map((u) => u.type))];
        },

        get visibleUnits() {
            return this.units.filter((unit) => {
                const matchStatus =
                    this.filter === 'ALL' || unit.status === this.filter;

                const matchType =
                    this.typeFilter === 'ALL' || unit.type === this.typeFilter;

                const q = this.query.trim().toLowerCase();
                const matchQuery =
                    q === '' ||
                    unit.name.toLowerCase().includes(q) ||
                    unit.code.toLowerCase().includes(q);

                return matchStatus && matchType && matchQuery;
            });
        },

        get counts() {
            return {
                ALL: this.units.length,
                READY: this.units.filter((u) => u.status === 'READY').length,
                BUSY: this.units.filter((u) => u.status === 'BUSY').length,
                MAINTENANCE: this.units.filter((u) => u.status === 'MAINTENANCE').length,
                TIME_UP: this.units.filter((u) => u.session?.is_time_up).length,
            };
        },

        get activeShift() {
            return this.units.find((u) => u.session)?.session?.cashier ?? null;
        },

        formatClock(unit) {
            return window.Rebite.clock(unit.session?.remaining_seconds ?? 0);
        },

        formatElapsed(unit) {
            const minutes = Math.floor((unit.session?.elapsed_seconds ?? 0) / 60);
            return window.Rebite.clock(minutes * 60);
        },

        statusClasses(unit) {
            if (unit.status === 'MAINTENANCE') {
                return 'border-amber-500/40 bg-amber-500/5';
            }

            if (unit.status === 'BUSY') {
                return unit.session?.is_time_up
                    ? 'border-amber-400/70 bg-amber-500/10 animate-flash'
                    : 'border-rose-500/40 bg-rose-500/5';
            }

            return 'border-emerald-500/40 bg-emerald-500/5';
        },

        dotClass(unit) {
            if (unit.status === 'MAINTENANCE') return 'bg-amber-400';
            if (unit.status === 'BUSY') {
                return unit.session?.is_time_up ? 'bg-amber-400' : 'bg-rose-400';
            }
            return 'bg-emerald-400';
        },

        /* ---------------------------------------------------------------- *
         * Modal
         * ---------------------------------------------------------------- */

        async openCard(unit) {
            this.errors = {};
            this.activeUnit = unit;

            if (unit.status === 'READY') {
                await this.openStart(unit);
            } else if (unit.status === 'BUSY') {
                await this.openDetail(unit);
            } else {
                window.Alpine.store('toast').warning(
                    `${unit.name} sedang dalam masa perbaikan.`,
                );
            }
        },

        async openStart(unit) {
            this.modal = 'start';
            this.form.rate_package_id = null;
            this.form.is_free_play = false;
            this.form.open_play_minutes = 120;

            await this.loadMeta(unit);
        },

        async openDetail(unit) {
            this.form.payment_method = 'CASH';
            this.form.extra_minutes = 30;
            this.form.product_id = null;
            this.form.qty = 1;
            this.form.note = '';

            this.modal = 'detail';
            this.detail = unit;

            // Membuka detail = kasir sudah melihat pesanan terbaru unit ini.
            this.seenOrders.set(unit.session?.id, unit.order_summary?.latest?.id ?? null);

            await this.loadMeta(unit);
            await this.reloadDetail();
        },

        async loadMeta(unit) {
            this.metaLoading = true;

            try {
                const { data } = await window.axios.get(
                    this.apiMeta.replace('__ID__', unit.id),
                );

                this.meta = {
                    unit: data.unit,
                    rate_packages: data.rate_packages,
                    products: data.products,
                };
            } catch (error) {
                this.handleAuthError(error);
            } finally {
                this.metaLoading = false;
            }
        },

        closeModal() {
            this.modal = null;
            this.activeUnit = null;
            this.detail = null;
            this.receipt = null;
            this.errors = {};
            this.submitting = false;
        },

        get selectedPackage() {
            return this.meta.rate_packages.find(
                (p) => p.id === this.form.rate_package_id,
            );
        },

        /**
         * Estimasi sewa di modal start.
         *
         * Paket -> harga paket. Open Play (atau paket kosong) -> tarif per jam
         * unit, dibulatkan ke atas per jam, sama seperti PricingService.
         */
        get estimatedFee() {
            if (this.form.rate_package_id) {
                return this.selectedPackage?.price ?? 0;
            }

            const rate = this.meta.unit?.hourly_rate ?? 0;
            const minutes = this.form.open_play_minutes || 0;

            if (!rate || !minutes) return 0;

            return Math.max(1, Math.ceil(minutes / 60)) * rate;
        },

        selectPackage(pkg) {
            this.form.rate_package_id = pkg.id;
            this.form.is_free_play = false;
        },

        selectFreePlay() {
            this.form.is_free_play = true;
            this.form.rate_package_id = null;
        },

        /* ---------------------------------------------------------------- *
         * Aksi
         * ---------------------------------------------------------------- */

        async startRental() {
            this.submitting = true;
            this.errors = {};

            try {
                const { data } = await window.axios.post(this.apiStore, {
                    unit_id: this.activeUnit.id,
                    rate_package_id: this.form.rate_package_id,
                    is_free_play: this.form.is_free_play,
                    open_play_minutes: this.form.is_free_play
                        ? this.form.open_play_minutes
                        : null,
                });

                window.Alpine.store('toast').success(data.message);
                this.closeModal();
                await this.refresh();
            } catch (error) {
                this.handleError(error, 'Gagal memulai sewa.');
            } finally {
                this.submitting = false;
            }
        },

        async extendTime() {
            this.submitting = true;
            this.errors = {};

            try {
                const { data } = await window.axios.post(
                    `${this.apiSession(this.detail.session.id)}/extend`,
                    { extra_minutes: this.form.extra_minutes },
                );

                window.Alpine.store('toast').success(data.message);
                await this.refresh();
            } catch (error) {
                this.handleError(error, 'Gagal menambah durasi.');
            } finally {
                this.submitting = false;
            }
        },

        async addItem() {
            if (!this.form.product_id) {
                this.errors.product_id = 'Pilih produk terlebih dahulu.';
                return;
            }

            this.submitting = true;
            this.errors = {};

            try {
                const { data } = await window.axios.post(
                    `${this.apiSession(this.detail.session.id)}/items`,
                    { product_id: this.form.product_id, qty: this.form.qty },
                );

                window.Alpine.store('toast').success(data.message);
                this.form.product_id = null;
                this.form.qty = 1;

                await this.reloadDetail();
            } catch (error) {
                this.handleError(error, 'Gagal menambah pesanan.');
            } finally {
                this.submitting = false;
            }
        },

        async completeRental() {
            this.submitting = true;
            this.errors = {};

            try {
                const { data } = await window.axios.post(
                    `${this.apiSession(this.detail.session.id)}/complete`,
                    {
                        payment_method: this.form.payment_method,
                        note: this.form.note || null,
                    },
                );

                window.Alpine.store('toast').success(data.message);

                this.receipt = data.receipt;
                this.modal = 'receipt';
                this.detail = null;

                await this.refresh();
            } catch (error) {
                this.handleError(error, 'Gagal menyelesaikan sewa.');
            } finally {
                this.submitting = false;
            }
        },

        async cancelRental() {
            this.submitting = true;

            try {
                const { data } = await window.axios.post(
                    `${this.apiSession(this.detail.session.id)}/cancel`,
                    { reason: this.form.note || 'Dibatalkan kasir' },
                );

                window.Alpine.store('toast').warning(data.message);
                this.closeModal();
                await this.refresh();
            } catch (error) {
                this.handleError(error, 'Gagal membatalkan sewa.');
            } finally {
                this.submitting = false;
            }
        },

        async serveOrder(order) {
            this.submitting = true;

            try {
                const { data } = await window.axios.post(order.serve_url);

                window.Alpine.store('toast').success(
                    `Pesanan ${order.code} ditandai sudah diantar.`,
                );

                await this.refresh();
                await this.reloadDetail();
            } catch (error) {
                this.handleError(error, 'Gagal menandai pesanan diantar.');
            } finally {
                this.submitting = false;
            }
        },

        async cancelOrder(order) {
            if (! window.confirm(`Batalkan pesanan ${order.code}?`)) return;

            this.submitting = true;

            try {
                const { data } = await window.axios.post(order.cancel_url);

                window.Alpine.store('toast').warning(data.message);

                await this.refresh();
                await this.reloadDetail();
            } catch (error) {
                this.handleError(error, 'Gagal membatalkan pesanan.');
            } finally {
                this.submitting = false;
            }
        },

        /**
         * Tarik ulang tagihan lengkap (item F&B + pesanan QR + total) dari
         * endpoint sesi. Endpoint /api/units hanya mengirim ringkasan unit,
         * jadi angka tagihan di modal harus selalu berasal dari sini.
         */
        async reloadDetail() {
            if (!this.detail?.session) return;

            try {
                const { data } = await window.axios.get(
                    this.apiSession(this.detail.session.id),
                );

                this.detail = { ...this.detail, ...data.session };
            } catch (error) {
                this.handleAuthError(error);
            }
        },

        printReceipt() {
            window.print();
        },

        /* ---------------------------------------------------------------- *
         * Error handling
         * ---------------------------------------------------------------- */

        handleError(error, fallback) {
            const status = error.response?.status;
            const payload = error.response?.data;

            if (status === 422) {
                this.errors = payload?.errors ?? {};
                window.Alpine.store('toast').error(
                    Object.values(payload?.errors ?? {}).flat()[0] ?? fallback,
                );
                return;
            }

            this.handleAuthError(error, fallback);
        },

        handleAuthError(error, fallback) {
            if (error.response?.status === 423) {
                window.location.href = error.response.data.redirect;
                return;
            }

            window.Alpine.store('toast').error(
                fallback ?? error.response?.data?.message ?? 'Terjadi kesalahan.',
            );
        },
    }));
});

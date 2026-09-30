/**
 * Pemesanan menu pelanggan lewat pemindai barcode (Tampilan Pelanggan).
 *
 * Alur: pelanggan buka /order -> buat pesanan (nama + WA) -> halaman scan
 * menyalakan kamera (html5-qrcode). Setiap kode yang terbaca langsung
 * dikirim ke POST /order/{token}/scan; balasan JSON berisi keranjang
 * terbaru lalu dirender tanpa reload.
 *
 * Catatan anti-duplikat: kamera memindai 10-30x/detik selama label tetap
 * di dalam frame, jadi kode yang sama sengaja diabaikan selama
 * `rescanCooldownMs` supaya satu gesekan tidak menambah 20 item.
 *
 * html5-qrcode di-import dinamis: library ini cukup berat (~300kB) dan
 * hanya diperlukan saat tombol kamera ditekan, jadi tidak ikut terunduh
 * oleh pelanggan yang sudah selesai memesan.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('barcodeOrder', (config = {}) => ({
        orderToken: config.orderToken ?? null,
        scanUrl: config.scanUrl ?? null,
        showUrl: config.showUrl ?? null,

        order: config.order ?? null,
        items: this.order?.items ?? [],
        totalLabel: this.order?.total_label ?? 'Rp 0',
        isEditable: this.order?.is_editable ?? false,

        scanning: false,
        cameraError: null,
        cameraStarting: false,
        manualCode: '',
        message: null,
        error: null,
        busy: false,
        placing: false,

        poller: null,

        lastScannedCode: null,
        lastScannedAt: 0,
        rescanCooldownMs: 2500,

        reader: null,

        init() {
            if (!this.orderToken) return;

            // Polling status supaya halaman otomatis mencerminkan perubahan
            // dari kasir (mis. "Sudah Dibayar") tanpa pelanggan perlu refresh.
            this.poller = setInterval(() => this.poll(), 10000);
        },

        destroy() {
            clearInterval(this.poller);
            this.stopScanner();
        },

        get isEmpty() {
            return this.items.length === 0;
        },

        /* ------------------------------------------------------------------ *
         * Kamera
         * ------------------------------------------------------------------ */

        async startScanner() {
            if (this.scanning || this.cameraStarting) return;

            this.cameraError = null;
            this.cameraStarting = true;

            try {
                const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');

                this.reader = new Html5Qrcode('barcode-reader', {
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                        Html5QrcodeSupportedFormats.QR_CODE,
                    ],
                    verbose: false,
                });

                await this.reader.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 260, height: 160 }, aspectRatio: 1.6 },
                    (decodedText) => this.handleDetected(decodedText),
                    () => {
                        // Callback error dipanggil puluhan kali/detik saat
                        // tidak ada kode di dalam frame — sengaja diabaikan
                        // agar tidak membanjiri pesan di layar.
                    },
                );

                this.scanning = true;
            } catch (err) {
                this.cameraError =
                    err?.name === 'NotAllowedError'
                        ? 'Izin kamera ditolak. Aktifkan izin kamera di browser, atau ketik kode barcode secara manual.'
                        : 'Kamera tidak bisa dinyalakan. Pastikan HP tidak sedang dipakai aplikasi lain, lalu ketik kode barcode secara manual.';
            } finally {
                this.cameraStarting = false;
            }
        },

        async stopScanner() {
            if (!this.reader) return;

            try {
                await this.reader.stop();
                await this.reader.clear();
            } catch {
                /* Reader sudah berhenti / belum pernah start. */
            }

            this.reader = null;
            this.scanning = false;
        },

        async toggleScanner() {
            if (this.scanning) {
                await this.stopScanner();

                return;
            }

            await this.startScanner();
        },

        /* ------------------------------------------------------------------ *
         * Pemindaian
         * ------------------------------------------------------------------ */

        handleDetected(decodedText) {
            const code = (decodedText ?? '').trim();

            if (code === '') return;

            const now = Date.now();
            if (code === this.lastScannedCode && now - this.lastScannedAt < this.rescanCooldownMs) {
                return;
            }

            this.lastScannedCode = code;
            this.lastScannedAt = now;

            this.scan(code);
        },

        submitManual() {
            const code = this.manualCode.trim();

            if (code === '') return;

            this.manualCode = '';
            this.lastScannedCode = null;
            this.scan(code);
        },

        async scan(barcode) {
            if (this.busy || !this.isEditable) return;

            this.busy = true;
            this.error = null;

            try {
                const { data } = await window.axios.post(this.scanUrl, { barcode, qty: 1 });

                this.message = data.message;
                this.applyOrder(data.order);
            } catch (err) {
                this.error = this.extractError(err, 'Barcode gagal dibaca. Coba pindai ulang.');
            } finally {
                this.busy = false;
            }
        },

        /* ------------------------------------------------------------------ *
         * Keranjang
         * ------------------------------------------------------------------ */

        async changeQty(item, qty) {
            if (this.busy || !this.isEditable) return;

            this.busy = true;
            this.error = null;

            try {
                const { data } = await window.axios.patch(
                    `${this.baseUrl()}/items/${item.id}`,
                    { qty },
                );

                this.applyOrder(data.order);
            } catch (err) {
                this.error = this.extractError(err, 'Gagal mengubah jumlah.');
            } finally {
                this.busy = false;
            }
        },

        async removeItem(item) {
            await this.changeQty(item, 0);
        },

        async place() {
            if (this.placing) return;

            this.placing = true;
            this.error = null;

            try {
                const { data } = await window.axios.post(`${this.baseUrl()}/place`);

                this.message = data.message;
                this.applyOrder(data.order);
                await this.stopScanner();
            } catch (err) {
                this.error = this.extractError(err, 'Gagal mengirim pesanan.');
            } finally {
                this.placing = false;
            }
        },

        async poll() {
            if (!this.showUrl) return;

            try {
                const { data } = await window.axios.get(this.showUrl);

                this.applyOrder(data.order);
            } catch {
                // Jaringan putus sesaat — poller tetap mencoba lagi.
            }
        },

        /* ------------------------------------------------------------------ *
         * Helper
         * ------------------------------------------------------------------ */

        baseUrl() {
            return this.scanUrl.replace(/\/scan$/, '');
        },

        applyOrder(order) {
            this.order = order;
            this.items = order.items ?? [];
            this.totalLabel = order.total_label;
            this.isEditable = order.is_editable;
        },

        extractError(err, fallback) {
            return err.response?.data?.message ?? fallback;
        },

        statusClass() {
            if (this.order?.status === 'COMPLETED') {
                return 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30';
            }

            if (this.order?.status === 'PLACED') {
                return 'bg-sky-500/15 text-sky-300 ring-1 ring-inset ring-sky-500/30';
            }

            if (this.order?.status === 'CANCELLED') {
                return 'bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-slate-500/30';
            }

            return 'bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30';
        },
    }));
});

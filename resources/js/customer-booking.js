/**
 * Panel booking reservasi online (Tampilan Pelanggan / Public View).
 *
 * Data unit yang boleh dipesan dikirim via `bookingUnits` dari
 * CustomerDisplayController@index (hanya unit READY / BUSY). Form dikirim
 * pakai axios ke POST /booking; sukses/error ditampilkan langsung di dalam
 * modal tanpa reload halaman.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('bookingPanel', (config = {}) => ({
        units: config.units ?? [],
        open: false,
        unit: null,
        form: {
            customer_name: '',
            customer_phone: '',
            start_time: '',
            duration_hours: 1,
            notes: '',
        },
        submitting: false,
        error: null,
        fieldErrors: {},
        result: null,

        openBooking(unitId) {
            const unit = this.units.find((u) => u.id === unitId);
            if (!unit) return;

            this.unit = unit;
            this.form = {
                customer_name: '',
                customer_phone: '',
                start_time: this.defaultStartTime(unit),
                duration_hours: 1,
                notes: '',
            };
            this.submitting = false;
            this.error = null;
            this.fieldErrors = {};
            this.result = null;
            this.open = true;
            this.lockScroll();
        },

        close() {
            // Setelah booking dibuat, section "Booking Saya" baru muncul
            // setelah reload karena dirender server dari session browser.
            if (this.result) {
                window.location.reload();
                return;
            }

            this.open = false;
            this.unit = null;
            this.unlockScroll();
        },

        destroy() {
            this.unlockScroll();
        },

        lockScroll() {
            document.body.style.overflow = 'hidden';
        },

        unlockScroll() {
            document.body.style.overflow = '';
        },

        /**
         * Jam mulai default: jam utuh berikutnya; untuk unit BUSY dipindah ke
         * sesudah sesi berjalan selesai (slot bebas berikutnya).
         */
        defaultStartTime(unit) {
            const nextHour = new Date(Date.now() + 60 * 60 * 1000);
            nextHour.setMinutes(0, 0, 0);

            if (unit.session_end_timestamp && unit.session_end_timestamp > nextHour.getTime()) {
                return this.toInputValue(new Date(unit.session_end_timestamp));
            }

            return this.toInputValue(nextHour);
        },

        toInputValue(date) {
            const pad = (n) => String(n).padStart(2, '0');

            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },

        get pricePreview() {
            if (!this.unit) return '';

            const hours = Number(this.form.duration_hours) || 0;

            return this.unit.is_free ? 'Gratis' : window.Rebite.rupiah(this.unit.hourly_rate * hours);
        },

        async submit() {
            this.submitting = true;
            this.error = null;
            this.fieldErrors = {};

            try {
                const { data } = await window.axios.post('/booking', {
                    console_id: this.unit.id,
                    ...this.form,
                });

                this.result = {
                    booking_code: data.booking_code,
                    console_name: data.console_name,
                    start_time_label: data.start_time_label,
                    end_time_label: data.end_time_label,
                    total_price_label: data.total_price_label,
                    status: data.status,
                };
            } catch (err) {
                const response = err.response;

                if (response?.data?.errors) {
                    this.fieldErrors = response.data.errors;
                } else {
                    this.error = response?.data?.message ?? 'Terjadi kesalahan. Silakan coba lagi.';
                }
            } finally {
                this.submitting = false;
            }
        },

        fieldError(key) {
            return this.fieldErrors[key]?.[0] ?? '';
        },
    }));

    /**
     * Panel "Booking Saya" di landing page.
     *
     * Menampilkan status booking milik session browser ini dan polling
     * berkala supaya pelanggan melihat perubahan "Menunggu Persetujuan" ->
     * "Sudah Terisi" begitu kasir menyetujui, tanpa perlu refresh manual.
     */
    window.Alpine.data('myBookings', (config = {}) => ({
        bookings: config.initial ?? [],
        url: config.url ?? '/booking/status',
        pollInterval: 10000,
        lastSync: '',

        init() {
            this.timer = setInterval(() => this.tick(), 1000);
            this.poll();
            this.pollTimer = setInterval(() => this.poll(), this.pollInterval);
        },

        destroy() {
            clearInterval(this.timer);
            clearInterval(this.pollTimer);
        },

        async poll() {
            try {
                const { data } = await window.axios.get(this.url);

                this.bookings = data.bookings;
                this.lastSync = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                });
            } catch (err) {
                this.lastSync = 'Gagal memuat status. Coba muat ulang halaman.';
            }
        },

        /** Hitung mundur lokal; poll berikutnya mengoreksi kembali. */
        tick() {
            this.bookings.forEach((booking) => {
                if (booking.remaining_seconds !== null) {
                    booking.remaining_seconds = Math.max(0, booking.remaining_seconds - 1);
                }
            });
        },

        remainingLabel(booking) {
            const total = booking.remaining_seconds ?? 0;

            if (total <= 0) return 'WAKTU HABIS';

            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const seconds = total % 60;
            const pad = (n) => String(n).padStart(2, '0');

            return (hours > 0 ? hours + 'j ' : '') + pad(minutes) + 'm ' + pad(seconds) + 'd';
        },
    }));
});
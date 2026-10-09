/**
 * Panel booking untuk dashboard pelanggan (butuh login).
 *
 * Data unit yang boleh dipesan dikirim via `bookingUnits` dari
 * CustomerBookingController@index (hanya unit READY / BUSY). Form dikirim
 * pakai axios ke endpoint `config.url`; sukses/error ditampilkan langsung di
 * dalam modal tanpa reload halaman. Nama dan nomor WhatsApp diambil dari akun
 * pelanggan, jadi tidak ada di form.
 *
 * Modal yang sama juga melayani "Ajukan Sewa" (mode `rental`): pilih rentang
 * waktu + paket, dikirim ke `config.rentalUrl`. Riwayat permintaan sewa ikut
 * dikirim via `config.rentals` dan ditambahkan langsung setelah pengiriman
 * berhasil.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('bookingPanel', (config = {}) => ({
        units: config.units ?? [],
        url: config.url ?? '/customer/bookings',
        rentalUrl: config.rentalUrl ?? '/customer/rentals',
        ratePackages: config.ratePackages ?? [],
        rentals: config.rentals ?? [],
        open: false,
        mode: 'booking',
        unit: null,
        form: {
            start_time: '',
            duration_hours: 1,
            notes: '',
        },
        rentalForm: {
            start_time: '',
            end_time: '',
            package_id: '',
            notes: '',
        },
        submitting: false,
        error: null,
        fieldErrors: {},
        result: null,
        rentalResult: null,

        get availableCount() {
            return this.units.filter((unit) => unit.status === 'READY' && !unit.is_reserved).length;
        },

        get occupiedCount() {
            return this.units.filter((unit) => unit.status === 'BUSY' || unit.is_reserved).length;
        },

        unitCard(unitId) {
            return this.units.find((unit) => unit.id === unitId);
        },

        updateUnits(units) {
            this.units = units;
            if (this.unit) {
                this.unit = this.unitCard(this.unit.id) ?? this.unit;
            }
        },

        unitDescription(unitId) {
            const unit = this.unitCard(unitId);
            if (!unit) return '';

            if (unit.status === 'BUSY') {
                return unit.session_remaining_minutes === null
                    ? 'Sedang dipakai pengunjung'
                    : unit.session_remaining_minutes === 0
                        ? 'Waktu habis · segera selesai'
                        : `Sisa ± ${unit.session_remaining_minutes} menit · ${unit.session_package_name}`;
            }

            if (unit.is_reserved) return `Dipesan: ${unit.reservation_label}`;

            return unit.is_free ? 'Gratis · Open Play' : `${window.Rebite.rupiah(unit.hourly_rate)} / jam`;
        },

        openBooking(unitId) {
            const unit = this.unitCard(unitId);
            if (!unit) return;

            this.unit = unit;
            this.form = {
                start_time: this.defaultStartTime(unit),
                duration_hours: 1,
                notes: '',
            };
            this.mode = 'booking';
            this.submitting = false;
            this.error = null;
            this.fieldErrors = {};
            this.result = null;
            this.rentalResult = null;
            this.open = true;
            this.lockScroll();
        },

        openRental(unitId) {
            const unit = this.unitCard(unitId);
            if (!unit) return;

            const start = this.defaultStartTime(unit);

            this.unit = unit;
            this.rentalForm = {
                start_time: start,
                end_time: this.toInputValue(new Date(new Date(start).getTime() + 2 * 60 * 60 * 1000)),
                package_id: '',
                notes: '',
            };
            this.mode = 'rental';
            this.submitting = false;
            this.error = null;
            this.fieldErrors = {};
            this.result = null;
            this.rentalResult = null;
            this.open = true;
            this.lockScroll();
        },

        close() {
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
                nextHour.setTime(unit.session_end_timestamp);
            }

            if (unit.reservation_start_timestamp &&
                unit.reservation_start_timestamp < nextHour.getTime() + 60 * 60 * 1000 &&
                unit.reservation_end_timestamp > nextHour.getTime()) {
                nextHour.setTime(unit.reservation_end_timestamp);
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

        get selectedPackage() {
            return this.ratePackages.find((pkg) => pkg.id === Number(this.rentalForm.package_id)) ?? null;
        },

        get rentalDurationMinutes() {
            const start = new Date(this.rentalForm.start_time);
            const end = new Date(this.rentalForm.end_time);

            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
                return 0;
            }

            return Math.round((end - start) / 60000);
        },

        /**
         * Perkiraan harga untuk permintaan sewa — aturannya sama persis
         * dengan PricingService di server: durasi dibulatkan ke jam utuh
         * (ke atas), jadi angka di modal sama dengan tagihan final.
         */
        get rentalPricePreview() {
            if (!this.unit || this.rentalDurationMinutes <= 0) return '';

            const rate = this.unit.hourly_rate ?? 0;
            const pkg = this.selectedPackage;
            const total = pkg
                ? pkg.price + rate * this.billableHours(Math.max(0, this.rentalDurationMinutes - pkg.duration_minutes))
                : rate * this.billableHours(this.rentalDurationMinutes);

            return window.Rebite.rupiah(Math.round(total));
        },

        /** Jam yang ditagih untuk sebuah durasi: pecahan jam = 1 jam penuh. */
        billableHours(minutes) {
            if (minutes <= 0) return 0;

            return Math.max(1, Math.ceil(minutes / 60));
        },

        async submit() {
            this.submitting = true;
            this.error = null;
            this.fieldErrors = {};

            try {
                const { data } = await window.axios.post(this.url, {
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

        async submitRental() {
            this.submitting = true;
            this.error = null;
            this.fieldErrors = {};

            try {
                const { data } = await window.axios.post(this.rentalUrl, {
                    unit_id: this.unit.id,
                    start_time: this.rentalForm.start_time,
                    end_time: this.rentalForm.end_time,
                    package_id: this.rentalForm.package_id || null,
                    notes: this.rentalForm.notes,
                });

                this.rentalResult = data;
                this.rentals.unshift(data);
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
     * Panel "Booking Saya" di dashboard pelanggan.
     *
     * Menampilkan status booking milik akun yang sedang login dan polling
     * berkala supaya pelanggan melihat perubahan status "Menunggu" ->
     * "Sudah Terisi" tanpa perlu refresh manual.
     */
    window.Alpine.data('myBookings', (config = {}) => ({
        bookings: config.initial ?? [],
        url: config.url ?? '/customer/bookings/status',
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
                window.dispatchEvent(new CustomEvent('customer-units-updated', { detail: data.units }));
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
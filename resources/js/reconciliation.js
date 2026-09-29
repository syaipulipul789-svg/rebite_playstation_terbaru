/**
 * Rekonsiliasi Akhir Shift.
 *
 * Sisi client hanya untuk UX: menghitung selisih secara live sambil kasir
 * mengetik uang fisik, dan memunculkan/menyembunyikan kolom WAJIB
 * "Catatan Keterangan Selisih" ketika selisih != 0.
 *
 * Angka final SELALU dihitung ulang di server (ShiftService::close) —
 * nilai yang dikirim client hanya dipakai untuk validasi required note.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('reconciliation', (config) => ({
        startingCash: Number(config.startingCash),
        cashRevenue: Number(config.cashRevenue),
        qrisRevenue: Number(config.qrisRevenue),
        actualInput: '',
        note: '',
        showNote: false,
        submitAttempted: false,

        init() {
            this.recompute();
        },

        /* Nilai uang fisik yang sudah dibersihkan dari titik & pemisah. */
        get actual() {
            const digits = this.actualInput.replace(/[^0-9]/g, '');
            return digits === '' ? 0 : Number(digits);
        },

        /* Dipakai hidden field agar server menerima angka murni, bukan "200.000". */
        get actualValue() {
            return this.actualInput === '' ? '' : this.actual;
        },

        /* Total Ekspektasi Kas = Modal Awal + Total Pendapatan Tunai. */
        get expected() {
            return this.startingCash + this.cashRevenue;
        },

        /* Selisih = Uang Fisik Riil - Total Ekspektasi Kas. */
        get discrepancy() {
            return this.actual - this.expected;
        },

        get hasDiscrepancy() {
            return this.actualInput !== '' && this.discrepancy !== 0;
        },

        get isBalanced() {
            return this.actualInput !== '' && this.discrepancy === 0;
        },

        get discrepancyLabel() {
            const value = this.discrepancy;

            if (value === 0) return 'Rp 0';

            return (value > 0 ? '+' : '-') + ' ' + window.Rebite.rupiah(Math.abs(value));
        },

        get discrepancyDirection() {
            if (this.discrepancy > 0) return 'LEBIH';
            if (this.discrepancy < 0) return 'KURANG';
            return 'SEJAK';
        },

        get noteError() {
            return this.submitAttempted && this.hasDiscrepancy && this.note.trim() === '';
        },

        recompute() {
            this.showNote = this.hasDiscrepancy;
        },

        formatInput(event) {
            let value = event.target.value.replace(/[^0-9]/g, '');

            if (value === '') {
                this.actualInput = '';
                this.recompute();
                return;
            }

            value = value.replace(/^0+(?=\d)/, '');
            this.actualInput = new Intl.NumberFormat('id-ID').format(Number(value));
            event.target.value = this.actualInput;

            this.recompute();
        },

        submit() {
            this.submitAttempted = true;

            if (this.hasDiscrepancy && this.note.trim() === '') {
                window.Alpine.store('toast').warning(
                    'Selisih kas tidak nol — isi catatan keterangan selisih.',
                );
                return false;
            }

            if (this.actualInput === '') {
                window.Alpine.store('toast').error(
                    'Jumlah uang fisik di laci kasir wajib diisi.',
                );
                return false;
            }

            return true;
        },
    }));
});

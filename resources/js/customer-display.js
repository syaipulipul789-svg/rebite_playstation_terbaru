/**
 * Live Monitor layar TV (Tampilan Pelanggan / Public View).
 *
 * Data awal dirender server dari CustomerDisplayController@liveDisplay,
 * lalu disinkronkan tiap `pollInterval` ms lewat endpoint publik
 * /display/data (CustomerDisplayController@displayData).
 *
 * Countdown per detik dihitung dari `planned_end_timestamp` (epoch ms)
 * + koreksi skew jam server — jadi jam lokal TV yang meleset tidak
 * membuat timer salah, persis pendekatan grid unit kasir.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('liveDisplay', (config) => ({
        units: config.units ?? [],
        apiUrl: config.apiUrl ?? null,
        pollInterval: config.pollInterval ?? 10000,

        serverSkewMs: 0,
        lastSync: null,
        ticker: null,
        poller: null,

        init() {
            this.serverSkewMs = (config.serverTimestamp ?? Date.now()) - Date.now();
            this.lastSync = new Date(config.serverTime ?? new Date());

            // Countdown halus setiap detik di client.
            this.ticker = setInterval(() => this.tick(), 1000);

            // Sinkron status (unit baru, READY <-> BUSY, ekstensi waktu).
            this.poller = setInterval(() => this.poll(), this.pollInterval);
        },

        destroy() {
            clearInterval(this.ticker);
            clearInterval(this.poller);
        },

        async poll() {
            if (!this.apiUrl) return;

            try {
                const { data } = await window.axios.get(this.apiUrl);

                this.units = data.units;
                this.serverSkewMs = data.server_timestamp - Date.now();
                this.lastSync = new Date();
            } catch {
                // Jaringan putus sesaat — poller tetap berjalan dan mencoba lagi.
            }
        },

        tick() {
            const now = Date.now() + this.serverSkewMs;

            this.units.forEach((unit) => {
                if (!unit.session) return;

                unit.session.remaining_seconds = Math.max(
                    0,
                    Math.round((unit.session.planned_end_timestamp - now) / 1000),
                );
                unit.session.is_time_up = unit.session.remaining_seconds <= 0;
            });
        },

        clock(seconds) {
            return window.Rebite.clock(seconds);
        },

        lastSyncLabel() {
            if (!this.lastSync) return '—';

            return this.lastSync.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        },

        cardClasses(unit) {
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
    }));
});
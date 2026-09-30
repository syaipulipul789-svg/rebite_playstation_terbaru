import Alpine from 'alpinejs';
import axios from 'axios';
import { createIcons } from 'lucide';
import * as LucideIcons from './icons';

import './unit-grid';
import './customer-display';
import './customer-booking';
import './customer-order';
import './barcode-labels';
import './reconciliation';
import './charts';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';

window.Alpine = Alpine;

/* ------------------------------------------------------------------ *
 * Toast notification global
 * ------------------------------------------------------------------ */
Alpine.store('toast', {
    items: [],
    nextId: 1,

    push(message, type = 'info', timeout = 4000) {
        const id = this.nextId++;
        this.items.push({ id, message, type });

        setTimeout(() => this.dismiss(id), timeout);
    },

    dismiss(id) {
        this.items = this.items.filter((item) => item.id !== id);
    },

    success(message) {
        this.push(message, 'success');
    },

    error(message) {
        this.push(message, 'error', 6000);
    },

    warning(message) {
        this.push(message, 'warning', 5000);
    },
});

/* ------------------------------------------------------------------ *
 * Sound alert (WebAudio) — tidak perlu file mp3.
 * Dipanggil saat waktu sewa unit habis.
 * ------------------------------------------------------------------ */
Alpine.store('sound', {
    enabled: true,

    toggle() {
        this.enabled = !this.enabled;
    },

    alert() {
        if (!this.enabled) return;

        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;

            const ctx = new Ctx();
            const now = ctx.currentTime;

            [0, 0.3].forEach((offset) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'square';
                osc.frequency.setValueAtTime(932, now + offset);

                gain.gain.setValueAtTime(0.0001, now + offset);
                gain.gain.exponentialRampToValueAtTime(0.16, now + offset + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.24);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(now + offset);
                osc.stop(now + offset + 0.26);
            });

            setTimeout(() => ctx.close(), 1400);
        } catch {
            /* Audio tidak tersedia, abaikan. */
        }
    },
});

/* ------------------------------------------------------------------ *
 * Jam digital topbar
 * ------------------------------------------------------------------ */
Alpine.data('clock', () => ({
    now: new Date(),
    timer: null,

    init() {
        this.timer = setInterval(() => {
            this.now = new Date();
        }, 1000);
    },

    destroy() {
        clearInterval(this.timer);
    },

    get time() {
        return this.now.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        });
    },

    get date() {
        return this.now.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        });
    },
}));

/* ------------------------------------------------------------------ *
 * Flash message session Laravel
 * ------------------------------------------------------------------ */
Alpine.data('flash', (messages) => ({
    messages,

    dismiss(key) {
        this.messages = this.messages.filter((item) => item.key !== key);
    },
}));

/* ------------------------------------------------------------------ *
 * Input kode booking (halaman Cek Status Booking)
 * ------------------------------------------------------------------ */
Alpine.data('bookingCodeInput', (initial = '') => ({
    value: initial,

    /**
     * Agar pengguna cukup mengetik angkanya saja, lalu otomatis dirapikan
     * jadi format 'BK-0004'.
     */
    format() {
        const digits = this.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 10);

        this.value = digits === '' ? '' : 'BK-' + digits.padStart(4, '0');
    },
}));

/* ------------------------------------------------------------------ *
 * Hitung mundur sisa waktu main (halaman Cek Status Booking)
 * ------------------------------------------------------------------ */
function initBookingCountdown() {
    document.querySelectorAll('[data-countdown-seconds]').forEach((el) => {
        let remaining = Number(el.dataset.countdownSeconds) || 0;

        const render = () => {
            const h = Math.floor(remaining / 3600);
            const m = Math.floor((remaining % 3600) / 60);
            const s = remaining % 60;
            const pad = (n) => String(n).padStart(2, '0');

            el.textContent = remaining <= 0 ? 'WAKTU HABIS' : `${pad(h)}:${pad(m)}:${pad(s)}`;
        };

        render();

        setInterval(() => {
            if (remaining <= 0) return;

            remaining -= 1;
            render();
        }, 1000);
    });
}

document.addEventListener('DOMContentLoaded', initBookingCountdown);

/* ------------------------------------------------------------------ *
 * Helper format
 * ------------------------------------------------------------------ */
window.Rebite = {
    rupiah(value) {
        const amount = Number(value) || 0;

        return 'Rp ' + new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0,
        }).format(amount);
    },

    clock(seconds) {
        const s = Math.max(0, Math.floor(seconds));
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;

        return [h, m, sec].map((v) => String(v).padStart(2, '0')).join(':');
    },
};

Alpine.start();

/* ------------------------------------------------------------------ *
 * Render icon Lucide.
 * Observer di-disconnect saat render agar hasil penggantian elemen
 * tidak memicu render lagi (loop tak berujung).
 * ------------------------------------------------------------------ */
let iconTimer = null;
let rendering = false;

const observer = new MutationObserver(() => {
    if (rendering) return;

    clearTimeout(iconTimer);
    iconTimer = setTimeout(() => {
        rendering = true;
        observer.disconnect();
        createIcons({ icons: LucideIcons });
        observer.observe(document.body, { childList: true, subtree: true });
        rendering = false;
    }, 50);
});

createIcons({ icons: LucideIcons });
observer.observe(document.body, { childList: true, subtree: true });

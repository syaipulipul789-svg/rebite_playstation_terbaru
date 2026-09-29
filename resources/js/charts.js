/**
 * Grafik dashboard Owner (Chart.js).
 *
 * Data dikirim dari backend sebagai atribut `data-series` pada elemen canvas
 * agar chart tetap konsisten dengan angka yang tampil di tabel.
 */
import {
    Chart,
    LineController,
    BarController,
    DoughnutController,
    LineElement,
    BarElement,
    ArcElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Legend,
    Tooltip,
} from 'chart.js';

Chart.register(
    LineController,
    BarController,
    DoughnutController,
    LineElement,
    BarElement,
    ArcElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Legend,
    Tooltip,
);

Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = 'rgb(255 255 255 / 0.06)';
Chart.defaults.font.family = "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;

const rupiah = (value) => window.Rebite.rupiah(value);

const gridOptions = {
    grid: { color: 'rgb(255 255 255 / 0.05)', drawTicks: false },
    border: { display: false },
    ticks: { padding: 8 },
};

/* ------------------------------------------------------------------ *
 * Gradient fill untuk area chart
 * ------------------------------------------------------------------ */
function verticalGradient(ctx, area, hex) {
    if (!area) return 'transparent';

    const gradient = ctx.createLinearGradient(0, area.top, 0, area.bottom);
    gradient.addColorStop(0, hex.replace('rgb(', 'rgba(').replace(')', ', 0.35)'));
    gradient.addColorStop(1, hex.replace('rgb(', 'rgba(').replace(')', ', 0)'));

    return gradient;
}

function readSeries(canvas) {
    try {
        return JSON.parse(canvas.dataset.series || '{}');
    } catch {
        return {};
    }
}

/* ------------------------------------------------------------------ *
 * Line chart pendapatan harian (Tunai vs QRIS)
 * ------------------------------------------------------------------ */
export function revenueLineChart(canvasId, dataset) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: dataset.labels || [],
            datasets: [
                {
                    label: 'Tunai',
                    data: dataset.cash || [],
                    borderColor: 'rgb(52, 211, 153)',
                    backgroundColor: (c) => verticalGradient(c.chart.ctx, c.chart.chartArea, 'rgb(52, 211, 153)'),
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: 'rgb(52, 211, 153)',
                },
                {
                    label: 'QRIS',
                    data: dataset.qris || [],
                    borderColor: 'rgb(56, 189, 248)',
                    backgroundColor: (c) => verticalGradient(c.chart.ctx, c.chart.chartArea, 'rgb(56, 189, 248)'),
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: 'rgb(56, 189, 248)',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16 },
                },
                tooltip: {
                    backgroundColor: 'rgb(10 12 20 / 0.95)',
                    borderColor: 'rgb(255 255 255 / 0.1)',
                    borderWidth: 1,
                    padding: 12,
                    titleColor: '#e2e8f0',
                    bodyColor: '#cbd5e1',
                    callbacks: {
                        label: (item) => ` ${item.dataset.label}: ${rupiah(item.parsed.y)}`,
                        footer: (items) => {
                            const total = items.reduce((sum, i) => sum + i.parsed.y, 0);
                            return 'Total: ' + rupiah(total);
                        },
                    },
                },
            },
            scales: {
                x: { ...gridOptions, grid: { display: false } },
                y: {
                    ...gridOptions,
                    beginAtZero: true,
                    ticks: {
                        ...gridOptions.ticks,
                        callback: (value) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value),
                    },
                },
            },
        },
    });
}

/* ------------------------------------------------------------------ *
 * Bar chart pendapatan mingguan
 * ------------------------------------------------------------------ */
export function weeklyBarChart(canvasId, dataset) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: dataset.labels || [],
            datasets: [
                {
                    label: 'Pendapatan',
                    data: dataset.totals || [],
                    backgroundColor: (c) => verticalGradient(c.chart.ctx, c.chart.chartArea, 'rgb(99, 102, 241)'),
                    borderColor: 'rgb(129, 140, 248)',
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 38,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgb(10 12 20 / 0.95)',
                    borderColor: 'rgb(255 255 255 / 0.1)',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: (item) => ` ${rupiah(item.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: { ...gridOptions, grid: { display: false } },
                y: {
                    ...gridOptions,
                    beginAtZero: true,
                    ticks: {
                        ...gridOptions.ticks,
                        callback: (value) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value),
                    },
                },
            },
        },
    });
}

/* ------------------------------------------------------------------ *
 * Donut chart komposisi pendapatan per unit
 * ------------------------------------------------------------------ */
export function unitRevenueChart(canvasId, dataset) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    return new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: dataset.labels || [],
            datasets: [
                {
                    data: dataset.totals || [],
                    backgroundColor: [
                        'rgb(59, 98, 251)',
                        'rgb(34, 211, 238)',
                        'rgb(52, 211, 153)',
                        'rgb(251, 191, 36)',
                        'rgb(244, 63, 94)',
                        'rgb(168, 85, 247)',
                    ],
                    borderColor: 'rgb(10 12 20)',
                    borderWidth: 3,
                    hoverOffset: 8,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14 },
                },
                tooltip: {
                    backgroundColor: 'rgb(10 12 20 / 0.95)',
                    borderColor: 'rgb(255 255 255 / 0.1)',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: (item) => ` ${item.label}: ${rupiah(item.parsed)}`,
                    },
                },
            },
        },
    });
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('chart', (config) => ({
        ready: false,

        init() {
            this.ready = true;
        },

        mount(kind, canvasId) {
            if (this[`chart_${canvasId}`]) return;

            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const dataset = readSeries(canvas);
            let chart = null;

            if (kind === 'line') chart = revenueLineChart(canvasId, dataset);
            if (kind === 'bar') chart = weeklyBarChart(canvasId, dataset);
            if (kind === 'doughnut') chart = unitRevenueChart(canvasId, dataset);

            this[`chart_${canvasId}`] = chart;
        },

        mountAll() {
            this.mount('line', 'chart-revenue-daily');
            this.mount('bar', 'chart-revenue-weekly');
            this.mount('doughnut', 'chart-revenue-unit');
        },
    }));
});

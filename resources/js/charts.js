/**
 * Dashboard charts.
 *
 * Chart.js is registered with only the controllers and elements actually used,
 * so the bundle carries the pie and bar/line pieces rather than the whole
 * library. Data arrives on the canvas as a data-chart attribute, rendered by
 * Blade from real query results — nothing here invents numbers.
 */
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement, BarController, BarElement, CategoryScale, DoughnutController,
    Legend, LineController, LineElement, LinearScale, PointElement, Tooltip,
);

const NAVY = '#0b2545';
const BLUE = '#14487f';
const GREEN = '#198754';
const GRID = 'rgba(11, 37, 69, 0.08)';

const peso = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});

const read = (id) => {
    const canvas = document.getElementById(id);

    if (!canvas || !canvas.dataset.chart) {
        return null;
    }

    try {
        return { canvas, data: JSON.parse(canvas.dataset.chart) };
    } catch {
        // A malformed payload should leave the rest of the dashboard standing.
        return null;
    }
};

const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
};

function revenueChart() {
    const found = read('chart-revenue');
    if (!found) return;

    new Chart(found.canvas, {
        data: {
            labels: found.data.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Revenue',
                    data: found.data.revenue.map(Number),
                    backgroundColor: BLUE,
                    borderRadius: 3,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Payments',
                    data: found.data.payments,
                    borderColor: GREEN,
                    backgroundColor: GREEN,
                    tension: 0.3,
                    yAxisID: 'y1',
                    order: 1,
                },
            ],
        },
        options: {
            ...baseOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: GRID },
                    ticks: { callback: (value) => peso.format(value) },
                },
                // Payment count shares the chart but not the scale; without a
                // second axis a count of 30 would vanish beside pesos.
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { precision: 0 },
                },
                x: { grid: { display: false } },
            },
            plugins: {
                ...baseOptions.plugins,
                tooltip: {
                    callbacks: {
                        label: (item) => (item.dataset.yAxisID === 'y'
                            ? `Revenue: ${peso.format(item.parsed.y)}`
                            : `Payments: ${item.parsed.y}`),
                    },
                },
            },
        },
    });
}

function customerChart() {
    const found = read('chart-customers');
    if (!found) return;

    new Chart(found.canvas, {
        type: 'bar',
        data: {
            labels: found.data.labels,
            datasets: [{
                label: 'New customers',
                data: found.data.customers,
                backgroundColor: NAVY,
                borderRadius: 3,
            }],
        },
        options: {
            ...baseOptions,
            scales: {
                y: { beginAtZero: true, grid: { color: GRID }, ticks: { precision: 0 } },
                x: { grid: { display: false } },
            },
        },
    });
}

function doughnut(id) {
    const found = read(id);
    if (!found) return;

    new Chart(found.canvas, {
        type: 'doughnut',
        data: {
            labels: found.data.labels,
            datasets: [{
                data: found.data.values,
                backgroundColor: found.data.colours,
                borderWidth: 0,
            }],
        },
        options: {
            ...baseOptions,
            cutout: '62%',
            plugins: {
                ...baseOptions.plugins,
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    revenueChart();
    customerChart();
    doughnut('chart-invoices');
    doughnut('chart-services');
});

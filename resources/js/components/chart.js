import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const INK = '#1c2730';
const MUTED = '#5f6f77';
const GRID = '#e6ebe9';

/**
 * Bar chart driven by a definition produced by App\Services\Reports\Report::chart().
 * Thin bars (max 24px) with 4px rounded data ends, recessive hairline grid,
 * a legend only when there is more than one series, and hover tooltips.
 */
export default (definition) => ({
    init() {
        const { labels, datasets, stacked = false, horizontal = false, unit = '' } = definition;
        const valueAxis = horizontal ? 'x' : 'y';
        const categoryAxis = horizontal ? 'y' : 'x';
        const format = (v) => (v === null || v === undefined ? '–' : `${Number(v).toLocaleString()}${unit === '%' ? '%' : ''}`);

        new Chart(this.$refs.canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: datasets.map((d) => ({
                    label: d.label,
                    data: d.data,
                    backgroundColor: d.color,
                    hoverBackgroundColor: d.color,
                    maxBarThickness: 24,
                    borderRadius: stacked ? 0 : 4,
                    borderSkipped: 'start',
                    // 2px surface gap between stacked segments
                    borderWidth: stacked ? { top: horizontal ? 0 : 2, right: horizontal ? 2 : 0 } : 0,
                    borderColor: '#ffffff',
                })),
            },
            options: {
                indexAxis: categoryAxis === 'y' ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: datasets.length > 1,
                        position: 'top',
                        align: 'start',
                        labels: { color: INK, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2, font: { family: 'Public Sans', size: 12 } },
                    },
                    tooltip: {
                        backgroundColor: '#1c2730',
                        padding: 10,
                        titleFont: { family: 'Public Sans', weight: '600' },
                        bodyFont: { family: 'Public Sans' },
                        callbacks: {
                            label: (ctx) => ` ${ctx.dataset.label}: ${format(ctx.raw)}${unit && unit !== '%' ? ` ${unit}` : ''}`,
                        },
                    },
                },
                scales: {
                    [categoryAxis]: {
                        stacked,
                        grid: { display: false },
                        border: { color: GRID },
                        ticks: { color: MUTED, font: { family: 'Public Sans', size: 12 }, autoSkip: true, maxRotation: 0 },
                    },
                    [valueAxis]: {
                        stacked,
                        beginAtZero: true,
                        max: unit === '%' ? 100 : undefined,
                        grid: { color: GRID, lineWidth: 1 },
                        border: { display: false },
                        ticks: { color: MUTED, font: { family: 'Public Sans', size: 12 }, precision: 0, callback: (v) => format(v) },
                    },
                },
            },
        });
    },
});

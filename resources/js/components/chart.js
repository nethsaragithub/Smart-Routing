import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

import { cssVar, isDark, motionEnabled } from '../lib/prefs';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

/** Chart chrome follows the light/night theme tokens. */
const palette = () => ({
    ink: cssVar('--color-ink') || '#1c2730',
    muted: cssVar('--color-muted') || '#5f6f77',
    grid: isDark() ? 'rgba(255, 255, 255, 0.07)' : '#e6ebe9',
    surface: cssVar('--color-panel') || '#ffffff',
    tooltip: isDark() ? '#0a0f12' : '#1c2730',
});

/**
 * Bar chart driven by a definition produced by App\Services\Reports\Report::chart().
 * Thin bars (max 24px) with 4px rounded data ends, recessive hairline grid,
 * a legend only when there is more than one series, and hover tooltips.
 */
export default (definition) => {
    // Kept outside Alpine's reactive data: Chart.js misbehaves behind a Proxy.
    let chart = null;

    return {
        init() {
            // Build when scrolled into view, so the bars visibly grow in.
            const observer = new IntersectionObserver(([entry]) => {
                if (!entry.isIntersecting) return;
                observer.disconnect();
                this.render();
            }, { rootMargin: '0px 0px -10% 0px' });
            observer.observe(this.$el);

            this.onTheme = () => this.restyle();
            window.addEventListener('theme:change', this.onTheme);
        },

        destroy() {
            window.removeEventListener('theme:change', this.onTheme);
            chart?.destroy();
        },

        // Chart.js caches resolved dataset colours, so rebuild (without the grow-in) on a theme change.
        restyle() {
            if (!chart) return;
            chart.destroy();
            this.render({ animate: false });
        },

        render({ animate = motionEnabled() } = {}) {
            const c = palette();
            const { labels, datasets, stacked = false, horizontal = false, unit = '' } = definition;
            const valueAxis = horizontal ? 'x' : 'y';
            const categoryAxis = horizontal ? 'y' : 'x';
            const format = (v) => (v === null || v === undefined ? '–' : `${Number(v).toLocaleString()}${unit === '%' ? '%' : ''}`);

            chart = new Chart(this.$refs.canvas, {
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
                        borderColor: c.surface,
                    })),
                },
                options: {
                    indexAxis: categoryAxis === 'y' ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: animate
                        ? {
                            duration: 900,
                            easing: 'easeOutQuart',
                            // Bars rise one after another on first draw only.
                            delay: (ctx) => (ctx.type === 'data' && ctx.mode === 'default' ? ctx.dataIndex * 70 + ctx.datasetIndex * 140 : 0),
                        }
                        : false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: datasets.length > 1,
                            position: 'top',
                            align: 'start',
                            labels: { color: c.ink, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2, font: { family: 'Public Sans', size: 12 } },
                        },
                        tooltip: {
                            backgroundColor: c.tooltip,
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
                            border: { color: c.grid },
                            ticks: { color: c.muted, font: { family: 'Public Sans', size: 12 }, autoSkip: true, maxRotation: 0 },
                        },
                        [valueAxis]: {
                            stacked,
                            beginAtZero: true,
                            max: unit === '%' ? 100 : undefined,
                            grid: { color: c.grid, lineWidth: 1 },
                            border: { display: false },
                            ticks: { color: c.muted, font: { family: 'Public Sans', size: 12 }, precision: 0, callback: (v) => format(v) },
                        },
                    },
                },
            });
        },
    };
};

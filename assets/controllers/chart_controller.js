import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';
import DataLabels from 'chartjs-plugin-datalabels';

Chart.register(...registerables);

export default class extends Controller {
    static targets = ['canvas'];
    static values = {
        type: String,
        payload: Object,
        horizontal: Boolean,
        minimal: Boolean, // hide axes + rounded bars + top labels
    };

    connect() {
        const ctx = this.canvasTarget.getContext('2d');
        const isBar = (this.typeValue || 'bar') === 'bar';
        const minimal = this.minimalValue && isBar && !this.horizontalValue;

        // rounded pill bars when minimal
        const data = this.payloadValue;
        if (minimal && data?.datasets) {
            data.datasets = data.datasets.map((ds) => ({
                borderRadius: { topLeft: 999, topRight: 999, bottomLeft: 999, bottomRight: 999 },
                borderSkipped: false,
                maxBarThickness: 28,
                ...ds,
            }));
        }

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: minimal ? { top: 24 } : 0 },
            plugins: {
                legend: {
                    display: this.typeValue === 'doughnut' || this.typeValue === 'pie',
                    position: 'bottom',
                },
                tooltip: { enabled: !minimal },
                datalabels: minimal ? {
                    anchor: 'end',
                    align: 'end',
                    offset: 4,
                    font: { weight: 600, size: 11 },
                    color: '#181D27',
                    formatter: (v) => (v >= 1000 ? (v / 1000).toFixed(2).replace(/\.?0+$/, '') + 'k' : v),
                } : false,
            },
            scales: minimal
                ? {
                    x: {
                        position: 'top',
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#94979C', font: { size: 12 }, padding: 6 },
                        border: { display: false },
                    },
                    y: { display: false, grid: { display: false }, border: { display: false }, beginAtZero: true },
                }
                : undefined,
        };
        if (this.horizontalValue) {
            options.indexAxis = 'y';
            options.plugins.legend.display = false;
        }

        this.chart = new Chart(ctx, {
            type: this.typeValue || 'bar',
            data,
            options,
            plugins: minimal ? [DataLabels] : [],
        });
    }

    disconnect() {
        this.chart?.destroy();
    }
}

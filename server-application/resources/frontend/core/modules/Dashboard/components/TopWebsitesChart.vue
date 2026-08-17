<template>
    <div class="donut-chart-card">
        <div class="donut-chart-card__title">TOP WEBSITES (hours)</div>
        <div class="donut-chart-card__body">
            <div class="donut-chart-card__chart">
                <Doughnut :data="chartData" :options="chartOptions" />
            </div>
            <div class="donut-chart-card__legend">
                <div
                    v-for="(item, i) in websites"
                    :key="item.domain"
                    class="legend-row"
                >
                    <span class="legend-color" :style="{ background: colors[i % colors.length] }"></span>
                    <span class="legend-label">{{ item.domain }}</span>
                    <span class="legend-value">{{ toHours(item.total_seconds) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { Doughnut } from 'vue-chartjs';
    import {
        Chart as ChartJS,
        Title,
        Tooltip,
        Legend,
        ArcElement,
    } from 'chart.js';

    ChartJS.register(Title, Tooltip, Legend, ArcElement);

    const COLORS = [
        '#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f',
        '#edc948','#b07aa1','#ff9da7','#9c755f','#bab0ac',
        '#86bcb6','#e9c46a',
    ];

    export default {
        name: 'TopWebsitesChart',
        components: { Doughnut },
        props: {
            websites: {
                type: Array,
                default: () => [],
            },
        },
        data() {
            return { colors: COLORS };
        },
        computed: {
            chartData() {
                return {
                    labels: this.websites.map(w => w.domain),
                    datasets: [
                        {
                            data: this.websites.map(w => +(w.total_seconds / 3600).toFixed(2)),
                            backgroundColor: this.websites.map((_, i) => COLORS[i % COLORS.length]),
                            borderWidth: 1,
                        },
                    ],
                };
            },
            chartOptions() {
                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` ${ctx.label}: ${ctx.parsed.toFixed(2)}h`,
                            },
                        },
                    },
                };
            },
        },
        methods: {
            toHours(seconds) {
                return (seconds / 3600).toFixed(2);
            },
        },
    };
</script>

<style lang="scss" scoped>
    .donut-chart-card {
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 16px 20px;
        flex: 1;
        min-width: 300px;

        &__title {
            font-size: 0.85rem;
            font-weight: 700;
            text-align: center;
            color: #444;
            margin-bottom: 16px;
        }

        &__body {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        &__chart {
            width: 160px;
            height: 160px;
            flex-shrink: 0;
        }

        &__legend {
            flex: 1;
            overflow-y: auto;
            max-height: 200px;
        }
    }

    .legend-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 6px;
        font-size: 0.82rem;
    }

    .legend-color {
        width: 10px;
        height: 10px;
        border-radius: 2px;
        flex-shrink: 0;
    }

    .legend-label {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .legend-value {
        color: #555;
        font-variant-numeric: tabular-nums;
    }
</style>

<template>
    <div class="top-websites-list">
        <table class="site-table">
            <thead>
                <tr>
                    <th>{{ $t('top-websites-report.col_website') }}</th>
                    <th>{{ $t('top-websites-report.col_pageviews') }}</th>
                    <th>{{ $t('top-websites-report.col_duration') }}</th>
                    <th class="chart-col">{{ $t('top-websites-report.col_chart') }}</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="row in reportData">
                    <!-- Domain summary row -->
                    <tr
                        :key="`domain::${row.domain}`"
                        class="domain-row"
                        :class="{ 'domain-row--expanded': isExpanded(row.domain) }"
                        @click="toggle(row.domain)"
                    >
                        <td class="domain-name">
                            <span class="toggle-icon">{{ isExpanded(row.domain) ? '▲' : '▼' }}</span>
                            {{ row.domain }}
                        </td>
                        <td>{{ row.pageview_count }}</td>
                        <td class="duration">{{ formatDurationString(row.total_seconds) }}</td>
                        <td class="chart-col">
                            <div class="bar-chart" :style="{ width: barWidth(row.total_seconds) + '%' }"></div>
                        </td>
                    </tr>

                    <!-- Per-URL detail rows -->
                    <template v-if="isExpanded(row.domain)">
                        <tr
                            v-for="u in row.urls"
                            :key="u.url"
                            class="url-row"
                        >
                            <td class="url-cell">
                                <a :href="u.url" target="_blank" rel="noopener noreferrer" class="url-link">
                                    {{ u.url }}
                                </a>
                            </td>
                            <td>{{ u.pageview_count }}</td>
                            <td class="duration">{{ formatDurationString(u.total_seconds) }}</td>
                            <td></td>
                        </tr>
                    </template>
                </template>
            </tbody>
        </table>
    </div>
</template>

<script>
    import { formatDurationString } from '@/utils/time';

    export default {
        name: 'TopWebsitesList',
        props: {
            reportData: {
                type: Array,
                required: true,
            },
        },
        data() {
            return {
                expandedDomains: new Set(),
            };
        },
        computed: {
            maxSeconds() {
                return this.reportData.reduce((m, r) => Math.max(m, r.total_seconds), 0);
            },
        },
        methods: {
            formatDurationString,
            isExpanded(domain) {
                return this.expandedDomains.has(domain);
            },
            toggle(domain) {
                const next = new Set(this.expandedDomains);
                if (next.has(domain)) {
                    next.delete(domain);
                } else {
                    next.add(domain);
                }
                this.expandedDomains = next;
            },
            barWidth(seconds) {
                if (!this.maxSeconds) return 0;
                return Math.round((seconds / this.maxSeconds) * 100);
            },
        },
    };
</script>

<style lang="scss" scoped>
    .site-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;

        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }

        th {
            background: #f7f7f7;
            font-weight: 600;
        }

        .domain-row {
            cursor: pointer;

            &:hover {
                background: #fafafa;
            }

            &--expanded {
                background: #f0f4ff;
            }
        }

        .url-row {
            background: #fafcff;

            .url-cell {
                padding-left: 36px;
            }
        }

        .domain-name {
            font-weight: 500;
        }

        .url-link {
            color: #2e2ef9;
            word-break: break-all;
        }

        .duration {
            font-family: monospace;
        }

        .toggle-icon {
            margin-right: 6px;
            color: #888;
            font-size: 0.7rem;
        }

        .chart-col {
            width: 200px;
        }

        .bar-chart {
            height: 14px;
            background: #4e79a7;
            border-radius: 3px;
            min-width: 2px;
        }
    }
</style>
